<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Infrastructure\Persistence\Query\ProductEvaluation;

use Akeneo\Pim\Automation\DataQualityInsights\Domain\Model\ChannelLocaleRateCollection;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Query\ProductEvaluation\GetProductScoresQueryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductUuid;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductUuidCollection;

/**
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class GetUpToDateProductScoresQuery implements GetProductScoresQueryInterface
{
    public function __construct(
        private HasUpToDateProductEvaluationQuery $hasUpToDateEvaluationQuery,
        private GetProductScoresQueryInterface $getProductScoresQuery
    ) {
    }

    public function byProductUuid(ProductUuid $productUuid): ChannelLocaleRateCollection
    {
        if ($this->hasUpToDateEvaluationQuery->forEntityId($productUuid)) {
            return $this->getProductScoresQuery->byProductUuid($productUuid);
        }

        return new ChannelLocaleRateCollection();
    }

    public function byProductUuidCollection(ProductUuidCollection $productUuidCollection): array
    {
        $upToDateProducts = $this->hasUpToDateEvaluationQuery->forEntityIdCollection($productUuidCollection);

        return is_null($upToDateProducts) ? [] : $this->getProductScoresQuery->byProductUuidCollection($upToDateProducts);
    }
}
