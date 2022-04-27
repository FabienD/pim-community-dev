<?php

namespace AkeneoTest\Pim\Enrichment\Integration\Doctrine\Common\Saver;

use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Pim\Enrichment\Product\API\Command\UpsertProductCommand;
use Akeneo\Test\Integration\TestCase;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use PHPUnit\Framework\Assert;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Test products have been correctly removed from the index after a product has been removed.
 * They should be removed from 2 indexes:
 *      - pim_catalog_product
 *      - pim_catalog_product_and_product_model
 */
class RemovingProductIntegration extends TestCase
{
    private const DOCUMENT_TYPE = 'pim_catalog_product';

    /** @var Client */
    private $esProductAndProductModelClient;
    private ProductRepositoryInterface $productRepository;
    protected MessageBusInterface $messageBus;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->esProductAndProductModelClient = $this->get('akeneo_elasticsearch.client.product_and_product_model');
        $this->messageBus = $this->get('pim_enrich.product.message_bus');
        $this->productRepository = $this->get('pim_catalog.repository.product');
    }

    public function testRemovingProductOnUnitaryRemove()
    {
        $command = UpsertProductCommand::createFromCollection(
            userId: $this->getUserId('admin'),
            productIdentifier: 'bat',
            userIntents: []
        );
        $this->messageBus->dispatch($command);
        $this->getContainer()->get('pim_catalog.validator.unique_value_set')->reset();
        $this->clearDoctrineUoW();

        $product = $this->productRepository->findOneByIdentifier('bat');
        $productId = $product->getId();

        $this->get('pim_catalog.remover.product')->remove($product);

        $this->assertNotFoundInProductAndProductModelIndex($productId);
    }

    public function testRemovingProductsOnBulkRemove()
    {
        $products = [];
        foreach (['foo', 'bar', 'baz'] as $identifier) {
            $products[] = $this->get('pim_catalog.builder.product')->createProduct($identifier);
        }

        $this->get('pim_catalog.saver.product')->saveAll($products);

        $productFoo = $this->get('pim_catalog.repository.product')->findOneByIdentifier('foo');
        $productBar = $this->get('pim_catalog.repository.product')->findOneByIdentifier('bar');
        $productBaz = $this->get('pim_catalog.repository.product')->findOneByIdentifier('baz');
        $productFooId = $productFoo->getId();
        $productBarId = $productBar->getId();
        $productBazId = $productBaz->getId();

        $this->get('pim_catalog.remover.product')->removeAll([$productFoo, $productBar, $productBaz]);

        $this->assertNotFoundInProductAndProductModelIndex($productFooId);
        $this->assertNotFoundInProductAndProductModelIndex($productBarId);
        $this->assertNotFoundInProductAndProductModelIndex($productBazId);
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfiguration()
    {
        return $this->catalog->useTechnicalCatalog();
    }

    /**
     * Asserts the given productId does not exists in the product and product model Index.
     *
     * @param string $productId
     */
    private function assertNotFoundInProductAndProductModelIndex(string $productId): void
    {
        $found = true;
        try {
            $this->esProductAndProductModelClient->get(self::DOCUMENT_TYPE, 'product_' . $productId);
        } catch (Missing404Exception $e) {
            $found = false;
        }
        $this->assertFalse($found);
    }
}
