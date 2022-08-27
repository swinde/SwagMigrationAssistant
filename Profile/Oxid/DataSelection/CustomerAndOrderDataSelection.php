<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\DataSelection;

use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CustomerAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CustomerDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\OrderAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\OrderDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\OrderDocumentAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\OrderDocumentDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ShippingMethodDataSet;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class CustomerAndOrderDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'customersOrders';

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface;
    }

    public function getData(): DataSelectionStruct
    {
        return new DataSelectionStruct(
            self::IDENTIFIER,
            $this->getDataSets(),
            $this->getDataSetsRequiredForCount(),
            'swag-migration.index.selectDataCard.dataSelection.customersOrders',
            200,
            true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSets(): array
    {
        return [
            new CustomerAttributeDataSet(),
            new CustomerDataSet(),
            new ShippingMethodDataSet(),
            new OrderAttributeDataSet(),
            new OrderDataSet(),
            new OrderDocumentAttributeDataSet(),
            new OrderDocumentDataSet(),
        ];
    }

    public function getDataSetsRequiredForCount(): array
    {
        return [
            new CustomerDataSet(),
            new OrderDataSet(),
        ];
    }
}
