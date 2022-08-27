<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Logging\Log;

use SwagMigrationAssistant\Migration\Logging\Log\BaseRunLogEntry;

class UnsupportedShippingPriceLog extends BaseRunLogEntry
{
    /**
     * @var string
     */
    private $shippingMethodId;

    public function __construct(string $runId, string $entity, string $sourceId, string $shippingMethodId)
    {
        parent::__construct($runId, $entity, $sourceId);
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getLevel(): string
    {
        return self::LOG_LEVEL_INFO;
    }

    public function getCode(): string
    {
        return 'SWAG_MIGRATION__SHOPWARE_UNSUPPORTED_SHIPPING_PRICE';
    }

    public function getTitle(): string
    {
        return 'Unsupported shipping factor price calculation';
    }

    public function getParameters(): array
    {
        return [
            'entity' => $this->getEntity(),
            'sourceId' => $this->getSourceId(),
            'shippingMethodId' => $this->shippingMethodId,
        ];
    }

    public function getDescription(): string
    {
        $args = $this->getParameters();

        return \sprintf(
            'ShippingPrice-Entity with source id "%s" of shipping method "%s" could not be converted because of unsupported factor price calculation.',
            $args['sourceId'],
            $args['shippingMethodId']
        );
    }
}
