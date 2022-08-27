<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Logging\Log;

use SwagMigrationAssistant\Migration\Logging\Log\BaseRunLogEntry;

class UnsupportedShippingCalculationType extends BaseRunLogEntry
{
    /**
     * @var string
     */
    private $type;

    public function __construct(string $runId, string $entity, string $sourceId, string $type)
    {
        parent::__construct($runId, $entity, $sourceId);
        $this->type = $type;
    }

    public function getLevel(): string
    {
        return self::LOG_LEVEL_WARNING;
    }

    public function getCode(): string
    {
        return 'SWAG_MIGRATION__SHOPWARE_UNSUPPORTED_SHIPPING_CALCULATION_TYPE';
    }

    public function getTitle(): string
    {
        return 'Unsupported shipping calculation type';
    }

    public function getParameters(): array
    {
        return [
            'entity' => $this->getEntity(),
            'sourceId' => $this->getSourceId(),
            'type' => $this->type,
        ];
    }

    public function getDescription(): string
    {
        $args = $this->getParameters();

        return \sprintf(
            'ShippingMethod-Entity with source id "%s" could not be converted because of unsupported calculation type "%s".',
            $args['sourceId'],
            $args['type']
        );
    }
}
