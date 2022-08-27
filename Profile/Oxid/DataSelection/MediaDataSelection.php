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
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\MediaDataSet;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\MediaFolderDataSet;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class MediaDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'media';

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
            'swag-migration.index.selectDataCard.dataSelection.media',
            300,
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
            new MediaDataSet(),
        ];
    }

    public function getDataSetsRequiredForCount(): array
    {
        return [
            new MediaDataSet(),
        ];
    }
}
