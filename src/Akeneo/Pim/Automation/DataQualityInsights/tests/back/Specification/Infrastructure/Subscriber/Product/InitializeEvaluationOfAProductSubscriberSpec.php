<?php

declare(strict_types=1);

namespace Specification\Akeneo\Pim\Automation\DataQualityInsights\Infrastructure\Subscriber\Product;

use Akeneo\Pim\Automation\DataQualityInsights\Application\ProductEntityIdFactoryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Application\ProductEvaluation\CreateCriteriaEvaluations;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductIdCollection;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Platform\Bundle\FeatureFlagBundle\FeatureFlag;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class InitializeEvaluationOfAProductSubscriberSpec extends ObjectBehavior
{
    public function let(
        FeatureFlag                     $dataQualityInsightsFeature,
        CreateCriteriaEvaluations       $createProductsCriteriaEvaluations,
        LoggerInterface                 $logger,
        ProductEntityIdFactoryInterface $idFactory,
        Connection $connection
    ) {
        $this->beConstructedWith(
            $dataQualityInsightsFeature,
            $createProductsCriteriaEvaluations,
            $logger,
            $connection
        );
    }

    public function it_is_an_event_subscriber(): void
    {
        $this->shouldImplement(EventSubscriberInterface::class);
    }

    public function it_subscribes_to_several_events(): void
    {
        $this::getSubscribedEvents()->shouldHaveKey(StorageEvents::POST_SAVE);
    }

    public function it_does_nothing_when_the_entity_is_not_a_product($dataQualityInsightsFeature, $createProductsCriteriaEvaluations)
    {
        $dataQualityInsightsFeature->isEnabled()->shouldNotBeCalled();
        $createProductsCriteriaEvaluations->createAll(Argument::any())->shouldNotBeCalled();
        $this->onPostSave(new GenericEvent(new \stdClass()));
    }

    public function it_does_nothing_when_data_quality_insights_feature_is_not_active(
        $dataQualityInsightsFeature,
        $createProductsCriteriaEvaluations,
        ProductInterface $product
    ) {
        $dataQualityInsightsFeature->isEnabled()->willReturn(false);
        $createProductsCriteriaEvaluations->createAll(Argument::any())->shouldNotBeCalled();

        $this->onPostSave(new GenericEvent($product->getWrappedObject()));
    }

    public function it_does_nothing_on_non_unitary_post_save(
        $dataQualityInsightsFeature,
        $createProductsCriteriaEvaluations,
        ProductInterface $product
    ) {
        $dataQualityInsightsFeature->isEnabled()->shouldNotBeCalled();
        $createProductsCriteriaEvaluations->createAll(Argument::any())->shouldNotBeCalled();

        $this->onPostSave(new GenericEvent($product->getWrappedObject(), ['unitary' => false]));
        $this->onPostSave(new GenericEvent($product->getWrappedObject(), []));
    }

    public function it_creates_criteria_on_unitary_product_post_save(
        $dataQualityInsightsFeature,
        $createProductsCriteriaEvaluations,
        $idFactory,
        ProductInterface $product,
        Connection $connection,
        Result $result
    )
    {
        $product->getUuid()->willReturn(Uuid::fromString('54162e35-ff81-48f1-96d5-5febd3f00fd5'));
        $productIdCollection = ProductIdCollection::fromStrings(['12345']);

        $product->getId()->willReturn(12345);
        $dataQualityInsightsFeature->isEnabled()->willReturn(true);
        $idFactory->createCollection(['12345'])->willReturn($productIdCollection);
        $createProductsCriteriaEvaluations->createAll($productIdCollection)->shouldBeCalled();

        $connection->executeQuery(
            Argument::any(),
            [Uuid::fromString('54162e35-ff81-48f1-96d5-5febd3f00fd5')->getBytes()]
        )->shouldBeCalled()->willReturn($result);
        $result->fetchOne()->willReturn(12345);

        $this->onPostSave(new GenericEvent($product->getWrappedObject(), ['unitary' => true]));
    }

    public function it_does_not_stop_the_process_if_something_goes_wrong(
        $dataQualityInsightsFeature,
        $createProductsCriteriaEvaluations,
        $logger,
        $idFactory,
        ProductInterface $product,
        Connection $connection,
        Result $result
    ) {
        $product->getUuid()->willReturn(Uuid::fromString('54162e35-ff81-48f1-96d5-5febd3f00fd5'));
        $productIdCollection = ProductIdCollection::fromStrings(['12345']);

        $product->getId()->willReturn(12345);
        $dataQualityInsightsFeature->isEnabled()->willReturn(true);
        $idFactory->createCollection(['12345'])->willReturn($productIdCollection);

        $createProductsCriteriaEvaluations
            ->createAll($productIdCollection)
            ->willThrow(\Exception::class);

        $logger->error('Unable to create product criteria evaluation', Argument::any())->shouldBeCalledOnce();

        $connection->executeQuery(
            Argument::any(),
            [Uuid::fromString('54162e35-ff81-48f1-96d5-5febd3f00fd5')->getBytes()]
        )->shouldBeCalled()->willReturn($result);
        $result->fetchOne()->willReturn(12345);

        $this->onPostSave(new GenericEvent($product->getWrappedObject(), ['unitary' => true]));
    }
}
