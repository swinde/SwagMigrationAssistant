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
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\CrossSellingDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\MainVariantRelationDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ManufacturerAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\MediaFolderDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ProductAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ProductDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ProductOptionRelationDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ProductPriceAttributeDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ProductPropertyRelationDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\PropertyGroupOptionDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\SeoUrlDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\TranslationDataSet;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class SeoUrlDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'seoUrls';

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
            'swag-migration.index.selectDataCard.dataSelection.seoUrls',
            220,
            true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSets(): array
    {
        return [
            new MediaFolderDataSet(),
            new ProductAttributeDataSet(),
            new ProductPriceAttributeDataSet(),
            new ManufacturerAttributeDataSet(),
            new ProductDataSet(),
            new PropertyGroupOptionDataSet(),
            new ProductOptionRelationDataSet(),
            new ProductPropertyRelationDataSet(),
            new TranslationDataSet(),
            new CrossSellingDataSet(),
            new MainVariantRelationDataSet(),
            new SeoUrlDataSet(),
        ];
    }

    public function getDataSetsRequiredForCount(): array
    {
        return [
            new SeoUrlDataSet(),
        ];
    }
}
