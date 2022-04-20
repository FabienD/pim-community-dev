<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class ProductUuid implements ProductEntityIdInterface
{
    private function __construct(private UuidInterface $uuid)
    {
    }

    public static function fromString(string $uuid): self
    {
        if (!Uuid::isValid($uuid)) {
            throw new \InvalidArgumentException('TODO Put the message back that was in the construct');
        }

        return new self(Uuid::fromString($uuid));
    }

    public function __toString(): string
    {
        return strval($this->uuid);
    }

    public function toInt(): int
    {
        // TODO Update interface
        throw new \Exception('This method can not be called');
    }

    public function toBytes(): string
    {
        return $this->uuid->getBytes();
    }
}
