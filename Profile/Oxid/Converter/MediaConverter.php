<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\MediaDataSet;

abstract class MediaConverter extends OxidConverter
{
    /**
     * @var MediaFileServiceInterface
     */
    protected $mediaFileService;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var string
     */
    protected $connectionId;

    public function __construct(
        MappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        MediaFileServiceInterface $mediaFileService
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->mediaFileService = $mediaFileService;
    }

    public function getMediaUuids(array $converted): ?array
    {
        return \array_column($converted, 'id');
    }

    public function writeMapping(Context $context): void
    {
        $this->mappingService->writeMapping($context);
    }

    public function convert(
        array $data,
        Context $context,
        MigrationContextInterface $migrationContext
    ): ConvertStruct {
        $this->generateChecksum($data);
        $this->context = $context;
        $this->locale = $data['_locale'];
        unset($data['_locale']);

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $converted = [];
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA,
            $data['id'],
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

        if (empty($data['name'])) {
            $data['name'] = $converted['id'];
        }

        $this->mediaFileService->saveMediaFile(
            [
                'runId' => $migrationContext->getRunUuid(),
                'entity' => MediaDataSet::getEntity(),
                'uri' => $data['uri'] ?? $data['path'],
                'fileName' => $data['name'],
                'fileSize' => (int) $data['file_size'],
                'mediaId' => $converted['id'],
            ]
        );
        unset($data['uri'], $data['file_size']);

        $this->getMediaTranslation($converted, $data);
        $this->convertValue($converted, 'title', $data, 'name');
        $this->convertValue($converted, 'alt', $data, 'description');

        $albumMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::MEDIA_FOLDER,
            $data['albumID'],
            $this->context
        );

        if ($albumMapping !== null) {
            $converted['mediaFolderId'] = $albumMapping['entityUuid'];
            $this->mappingIds[] = $albumMapping['id'];
        }

        unset(
            $data['id'],
            $data['albumID'],

            // Legacy data which don't need a mapping or there is no equivalent field
            $data['path'],
            $data['type'],
            $data['extension'],
            $data['file_size'],
            $data['width'],
            $data['height'],
            $data['userID'],
            $data['created']
        );

        $returnData = $data;
        if (empty($returnData)) {
            $returnData = null;
        }
        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $returnData, $this->mainMapping['id']);
    }

    protected function getMediaTranslation(array &$media, array $data): void
    {
        $language = $this->mappingService->getDefaultLanguage($this->context);
        if ($language === null) {
            return;
        }

        $locale = $language->getLocale();
        if ($locale === null || $locale->getCode() === $this->locale) {
            return;
        }

        $localeTranslation = [];

        $this->convertValue($localeTranslation, 'title', $data, 'name');
        $this->convertValue($localeTranslation, 'alt', $data, 'description');

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA_TRANSLATION,
            $data['id'] . ':' . $this->locale,
            $this->context
        );
        $localeTranslation['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $languageUuid = $this->mappingService->getLanguageUuid($this->connectionId, $this->locale, $this->context);

        if ($languageUuid !== null) {
            $localeTranslation['languageId'] = $languageUuid;
            $media['translations'][$languageUuid] = $localeTranslation;
        }
    }
}
