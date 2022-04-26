<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Infrastructure\Persistence\Query\ProductEvaluation;

use Akeneo\Pim\Automation\DataQualityInsights\Domain\Model\Read\ProductEvaluation;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Query\ProductEvaluation\GetCriteriaEvaluationsByProductIdQueryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Query\ProductEvaluation\GetProductEvaluationQueryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Query\ProductEvaluation\GetProductScoresQueryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductUuid;

final class GetUpToDateProductEvaluationQuery implements GetProductEvaluationQueryInterface
{
    public function __construct(
        private GetCriteriaEvaluationsByProductIdQueryInterface $getCriteriaEvaluationsByProductIdQuery,
        private GetProductScoresQueryInterface $getProductScoresQuery
    ) {
    }

    public function execute(ProductUuid $productUuid): ProductEvaluation
    {
        $productScores = $this->getProductScoresQuery->byProductUuid($productUuid);
        $productCriteriaEvaluations = $this->getCriteriaEvaluationsByProductIdQuery->execute($productUuid);

        return new ProductEvaluation($productUuid, $productScores, $productCriteriaEvaluations);
    }
}
