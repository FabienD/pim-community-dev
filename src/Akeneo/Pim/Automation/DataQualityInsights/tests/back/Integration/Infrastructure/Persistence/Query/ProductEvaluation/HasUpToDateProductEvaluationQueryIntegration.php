<?php

declare(strict_types=1);

namespace Akeneo\Test\Pim\Automation\DataQualityInsights\Integration\Infrastructure\Persistence\Query\ProductEvaluation;

use Akeneo\Pim\Automation\DataQualityInsights\Application\Clock;
use Akeneo\Pim\Automation\DataQualityInsights\Application\ProductIdFactory;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductUuid;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductUuidCollection;
use Akeneo\Pim\Automation\DataQualityInsights\Infrastructure\Persistence\Query\ProductEvaluation\HasUpToDateProductEvaluationQuery;
use Akeneo\Test\Integration\TestCase;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

/**
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class HasUpToDateProductEvaluationQueryIntegration extends TestCase
{
    /** @var Connection */
    private $db;

    /** @var HasUpToDateProductEvaluationQuery */
    private $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->get('database_connection');
        $this->query = $this->get(HasUpToDateProductEvaluationQuery::class);
    }

    protected function getConfiguration()
    {
        return $this->catalog->useTechnicalCatalog();
    }

    public function test_it_returns_true_if_a_product_has_an_up_to_date_evaluation()
    {
        $today = new \DateTimeImmutable('2020-03-02 11:34:27');

        $productId = $this->givenAProductWithAnUpToDateEvaluation($today);
        $this->givenAnUpdatedProductWithAnOutdatedEvaluation($today);

        $productHasUpToDateEvaluation = $this->query->forProductId($productId);
        $this->assertTrue($productHasUpToDateEvaluation);

        $productVariantId = $this->givenAProductVariantWithAnUpToDateEvaluation($today);
        $productVariantHasUpToDateEvaluation = $this->query->forProductId($productVariantId);
        $this->assertTrue($productVariantHasUpToDateEvaluation);
    }

    public function test_it_returns_false_if_a_product_has_outdated_evaluations()
    {
        $today = new \DateTimeImmutable('2020-03-02 11:34:27');

        $productId = $this->givenAnUpdatedProductWithAnOutdatedEvaluation($today);
        $this->givenAProductWithAnUpToDateEvaluation($today);

        $productHasUpToDateEvaluation = $this->query->forProductId($productId);
        $this->assertFalse($productHasUpToDateEvaluation);

        $levelOneProductVariantId = $this->givenAProductVariantWithAnOutdatedEvaluationComparedToItsParent($today);
        $levelOneProductVariantHasUpToDateEvaluation = $this->query->forProductId($levelOneProductVariantId);
        $this->assertFalse($levelOneProductVariantHasUpToDateEvaluation);

        $levelTwoProductVariantId = $this->givenAProductVariantWithAnOutdatedEvaluationComparedToItsGrandParent($today);
        $levelTwoProductVariantHasUpToDateEvaluation = $this->query->forProductId($levelTwoProductVariantId);
        $this->assertFalse($levelTwoProductVariantHasUpToDateEvaluation);
    }

    public function test_it_returns_the_ids_of_the_products_that_have_up_to_date_evaluation()
    {
        $today = new \DateTimeImmutable('2020-03-02 11:34:27');
        $expectedProductIdA = $this->givenAProductWithAnUpToDateEvaluation($today);
        $expectedProductIdB = $this->givenAProductWithAnUpToDateEvaluation($today);
        $outdatedProductId = $this->givenAnUpdatedProductWithAnOutdatedEvaluation($today);
        $outdatedProductVariantId = $this->givenAProductVariantWithAnOutdatedEvaluationComparedToItsParent($today);
        $this->givenAProductWithAnUpToDateEvaluation($today);


        $productIdsWithUpToDateEvaluation = $this->query->forProductIdCollection(ProductUuidCollection::fromProductUuids(
            [$outdatedProductId, $outdatedProductVariantId, $expectedProductIdA, $expectedProductIdB]
        ));
        $this->assertEqualsCanonicalizing(
            ProductUuidCollection::fromProductUuids([$expectedProductIdA, $expectedProductIdB]),
            $productIdsWithUpToDateEvaluation
        );
    }

    public function test_it_returns_null_if_no_product_has_up_to_date_evaluation()
    {
        $today = new \DateTimeImmutable('2020-03-02 11:34:27');
        $outdatedProductId = $this->givenAnUpdatedProductWithAnOutdatedEvaluation($today);
        $productIdCollection = $this->get(ProductIdFactory::class)->createCollection([(string)$outdatedProductId]);

        $this->assertNull($this->query->forProductIdCollection($productIdCollection));
    }

    private function createProduct(): ProductUuid
    {
        $product = $this->get('akeneo_integration_tests.catalog.product.builder')
            ->withIdentifier(strval(Uuid::uuid4()))
            ->build();

        $this->get('pim_catalog.saver.product')->save($product);

        return $this->get(ProductIdFactory::class)->create((string)$product->getId());
    }

    private function createProductVariant(string $parentCode): ProductUuid
    {
        $product = $this->get('akeneo_integration_tests.catalog.product.builder')
            ->withIdentifier(strval(Uuid::uuid4()))
            ->withFamily('familyA')
            ->build();

        $this->get('pim_catalog.updater.product')->update($product, ['parent' => $parentCode]);
        $this->get('pim_catalog.saver.product')->save($product);

        return $this->get(ProductIdFactory::class)->create((string)$product->getId());
    }

    private function givenAProductWithAnUpToDateEvaluation(\DateTimeImmutable $today): ProductUuid
    {
        $productId = $this->createProduct();
        $this->updateProductAt($productId, $today);
        $this->updateProductEvaluationsAt($productId, $today->modify('+1 SECOND'));

        return $productId;
    }

    private function givenAnUpdatedProductWithAnOutdatedEvaluation(\DateTimeImmutable $updatedAt): ProductUuid
    {
        $productId = $this->createProduct();
        $this->updateProductAt($productId, $updatedAt);
        $this->updateProductEvaluationsAt($productId, $updatedAt->modify('-1 SECOND'));

        return $productId;
    }

    private function givenAProductVariantWithAnUpToDateEvaluation(\DateTimeImmutable $parentUpdatedAt): ProductUuid
    {
        $this->givenAProductModel('a_product_model', 'familyVariantA2', $parentUpdatedAt);
        $productId = $this->createProductVariant('a_product_model');
        $this->updateProductAt($productId, $parentUpdatedAt->modify('-1 DAY'));
        $this->updateProductEvaluationsAt($productId, $parentUpdatedAt->modify('+1 SECOND'));

        return $productId;
    }

    private function givenAProductVariantWithAnOutdatedEvaluationComparedToItsParent(\DateTimeImmutable $parentUpdatedAt): ProductUuid
    {
        $this->givenAProductModel('a_product_model', 'familyVariantA2', $parentUpdatedAt);
        $productId = $this->createProductVariant('a_product_model');
        $this->updateProductAt($productId, $parentUpdatedAt->modify('-1 DAY'));
        $this->updateProductEvaluationsAt($productId, $parentUpdatedAt->modify('-1 SECOND'));

        return $productId;
    }

    private function givenAProductVariantWithAnOutdatedEvaluationComparedToItsGrandParent(\DateTimeImmutable $grandParentUpdatedAt): ProductUuid
    {
        $this->givenAProductModel('a_product_model_with_two_variant_levels', 'familyVariantA1', $grandParentUpdatedAt);
        $this->givenASubProductModel('a_recently_updated_sub_product_model', 'familyVariantA1', 'a_product_model_with_two_variant_levels', $grandParentUpdatedAt->modify('-2 HOUR'));

        $productId = $this->createProductVariant('a_recently_updated_sub_product_model');
        $this->updateProductAt($productId, $grandParentUpdatedAt->modify('-1 HOUR'));
        $this->updateProductEvaluationsAt($productId, $grandParentUpdatedAt->modify('-1 SECOND'));

        return $productId;
    }

    private function givenAProductModel(string $productModelCode, string $familyVariant, \DateTimeImmutable $updatedAt)
    {
        $productModel = $this->get('akeneo_integration_tests.catalog.product_model.builder')
            ->withCode($productModelCode)
            ->withFamilyVariant($familyVariant)
            ->build();

        $this->get('pim_catalog.saver.product_model')->save($productModel);
        $this->updateProductModelAt($productModelCode, $updatedAt);
    }

    private function givenASubProductModel(string $productModelCode, string $familyVariant, string $parentCode, \DateTimeImmutable $updatedAt)
    {
        $productModel = $this->get('akeneo_integration_tests.catalog.product_model.builder')
            ->withCode($productModelCode)
            ->withFamilyVariant($familyVariant)
            ->withParent($parentCode)
            ->build();

        $this->get('pim_catalog.saver.product_model')->save($productModel);

        $this->updateProductModelAt($productModelCode, $updatedAt);
    }

    private function updateProductAt(ProductUuid $productId, \DateTimeImmutable $updatedAt): void
    {
        $query = <<<SQL
UPDATE pim_catalog_product SET updated = :updated WHERE id = :product_id;
SQL;

        $this->db->executeQuery($query, [
            'updated' => $updatedAt->format('Y-m-d H:i:s'),
            'product_id' => $productId->toInt(),
        ]);
    }

    private function updateProductModelAt(string $productModelCode, \DateTimeImmutable $updatedAt)
    {
        $query = <<<SQL
UPDATE pim_catalog_product_model SET updated = :updated WHERE code = :code;
SQL;

        $this->db->executeQuery($query, [
            'updated' => $updatedAt->format('Y-m-d H:i:s'),
            'code' => $productModelCode,
        ]);
    }

    private function updateProductEvaluationsAt(ProductUuid $productId, \DateTimeImmutable $evaluatedAt): void
    {
        $query = <<<SQL
UPDATE pim_data_quality_insights_product_criteria_evaluation e, pim_catalog_product p
SET e.evaluated_at = :evaluated_at 
WHERE p.id = :product_id AND p.uuid = e.product_uuid;
SQL;

        $this->db->executeQuery($query, [
            'evaluated_at' => $evaluatedAt->format(Clock::TIME_FORMAT),
            'product_id' => $productId->toInt(),
        ]);
    }
}
