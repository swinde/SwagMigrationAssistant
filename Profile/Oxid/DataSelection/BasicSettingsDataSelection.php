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
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CategoryAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CategoryDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CurrencyDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CustomerGroupAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CustomerGroupDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\LanguageDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\NumberRangeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\SalesChannelDataSet;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class BasicSettingsDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'basicSettings';

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
            'swag-migration.index.selectDataCard.dataSelection.basicSettings',
            -100,
            true,
            DataSelectionStruct::BASIC_DATA_TYPE,
            true
        );
    }

    public function getDataSets(): array
    {
        return [
            new LanguageDataSet(),
            new CategoryAttributeDataSet(),
            new CategoryDataSet(),
            new CustomerGroupAttributeDataSet(),
            new CustomerGroupDataSet(),
            new CurrencyDataSet(),
            new SalesChannelDataSet(),
            new NumberRangeDataSet(),
        ];
    }

    public function getDataSetsRequiredForCount(): array
    {
        return $this->getDataSets();
    }
}
