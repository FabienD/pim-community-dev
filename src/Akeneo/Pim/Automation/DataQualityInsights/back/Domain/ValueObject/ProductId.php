<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject;

use Ramsey\Uuid\UuidInterface;
use Webmozart\Assert\Assert;

/**
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class ProductId implements \Stringable
{
    /** @var int|UuidInterface */
    private $id;

    public function __construct($id)
    {
        if (\is_int($id)) {
            Assert::greaterThan($id, 0, 'Product id should be a positive integer');
        } else {
            Assert::implementsInterface($id, UuidInterface::class);
        }

        $this->id = $id;
    }

    public function __toString(): string
    {
        return $this->id instanceof UuidInterface ? $this->id->toString() : (string) $this->id;
    }

    public function toBinary(): string
    {
        Assert::implementsInterface($this->id, UuidInterface::class);

        return $this->id->getBytes();
    }
}
