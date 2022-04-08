<?php
declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Infrastructure\Subscriber\Product;

use Akeneo\Pim\Automation\DataQualityInsights\Application\ProductEntityIdFactoryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Application\ProductEvaluation\CreateCriteriaEvaluations;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Platform\Bundle\FeatureFlagBundle\FeatureFlag;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class InitializeEvaluationOfAProductSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FeatureFlag                     $dataQualityInsightsFeature,
        private CreateCriteriaEvaluations       $createProductsCriteriaEvaluations,
        private LoggerInterface                 $logger,
        private ProductEntityIdFactoryInterface $idFactory,
        private Connection $connection
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            // Priority greater than zero to ensure that the evaluation is done prior to the re-indexation of the product in ES
            StorageEvents::POST_SAVE => ['onPostSave', 10],
        ];
    }

    public function onPostSave(GenericEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof ProductInterface) {
            return;
        }

        if (!$event->hasArgument('unitary') || false === $event->getArgument('unitary')) {
            return;
        }

        if (!$this->dataQualityInsightsFeature->isEnabled()) {
            return;
        }

        // @TODO CPM-576 remove to use the uuid
        $id = $this->connection->executeQuery(
            'SELECT id FROM pim_catalog_product WHERE uuid = ?',
            [$subject->getUuid()->getBytes()]
        )->fetchOne();
        if (null === $id) {
            return;
        }

        $this->initializeCriteria(intval($id));
    }

    private function initializeCriteria(int $productId): void
    {
        try {
            $productIdCollection = $this->idFactory->createCollection([(string)$productId]);
            $this->createProductsCriteriaEvaluations->createAll($productIdCollection);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Unable to create product criteria evaluation',
                [
                    'error_code' => 'unable_to_create_product_criteria_evaluation',
                    'error_message' => $e->getMessage(),
                ]
            );
        }
    }
}
