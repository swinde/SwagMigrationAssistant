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
use SwagMigrationAssistant\Migration\Logging\Log\CannotConvertChildEntity;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\MediaDataSet;

abstract class PropertyGroupOptionConverter extends OxidConverter
{
    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $runId;

    /**
     * @var MediaFileServiceInterface
     */
    protected $mediaFileService;

    /**
     * @var string
     */
    protected $locale;

    public function __construct(
        MappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        MediaFileServiceInterface $mediaFileService
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->mediaFileService = $mediaFileService;
    }

    public function getSourceIdentifier(array $data): string
    {
        $group = 'unknown';
        if (isset($data['group'])) {
            $group = $data['group']['name'];
        }

        return \hash('md5', \mb_strtolower($data['name'] . '_' . $group . '_' . $data['type']));
    }

    public function getMediaUuids(array $converted): ?array
    {
        $mediaUuids = [];
        foreach ($converted as $data) {
            if (!isset($data['media']['id'])) {
                continue;
            }

            $mediaUuids[] = $data['media']['id'];
        }

        return $mediaUuids;
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->context = $context;
        $this->locale = $data['_locale'];
        $this->runId = $migrationContext->getRunUuid();

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        if (!isset($data['group']['name'])) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::PROPERTY_GROUP_OPTION,
                $data['id'],
                'group'
            ));

            return new ConvertStruct(null, $data);
        }

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PROPERTY_GROUP_OPTION,
            \hash('md5', \mb_strtolower($data['name'] . '_' . $data['group']['name'])),
            $context
        );
        $this->mappingIds[] = $mapping['id'];

        $propertyGroupMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PROPERTY_GROUP,
            \hash('md5', \mb_strtolower($data['group']['name'])),
            $context
        );
        $this->mappingIds[] = $propertyGroupMapping['id'];

        $converted = [
            'id' => $mapping['entityUuid'],
            'group' => [
                'id' => $propertyGroupMapping['entityUuid'],
            ],
        ];

        $this->createAndDeleteNecessaryMappings($data, $converted);

        if (isset($data['media'])) {
            $this->getMedia($converted, $data);
        }

        $this->getTranslation($data, $converted);
        $this->updateMainMapping($migrationContext, $context);

        $mainMapping = $this->mainMapping['id'] ?? null;

        return new ConvertStruct($converted, null, $mainMapping);
    }

    /**
     * @psalm-suppress PossiblyInvalidArgument
     */
    protected function getMedia(array &$converted, array $data): void
    {
        if (!isset($data['media']['id'])) {
            $this->loggingService->addLogEntry(new CannotConvertChildEntity(
                $this->runId,
                'property_group_option_media',
                DefaultEntities::PROPERTY_GROUP_OPTION,
                $data['id']
            ));

            return;
        }

        $newMedia = [];
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA,
            $data['media']['id'],
            $this->context
        );
        $newMedia['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        if (empty($data['media']['name'])) {
            $data['media']['name'] = $newMedia['id'];
        }

        $this->mediaFileService->saveMediaFile(
            [
                'runId' => $this->runId,
                'entity' => MediaDataSet::getEntity(),
                'uri' => $data['media']['uri'] ?? $data['media']['path'],
                'fileName' => $data['media']['name'],
                'fileSize' => (int) $data['media']['file_size'],
                'mediaId' => $newMedia['id'],
            ]
        );

        $this->getMediaTranslation($newMedia, $data);
        $this->convertValue($newMedia, 'title', $data['media'], 'name');
        $this->convertValue($newMedia, 'alt', $data['media'], 'description');

        $albumMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::MEDIA_FOLDER,
            $data['media']['albumID'],
            $this->context
        );

        if ($albumMapping !== null) {
            $newMedia['mediaFolderId'] = $albumMapping['entityUuid'];
            $this->mappingIds[] = $albumMapping['id'];
        }

        $converted['media'] = $newMedia;
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

        $this->convertValue($localeTranslation, 'title', $data['media'], 'name');
        $this->convertValue($localeTranslation, 'alt', $data['media'], 'description');

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA_TRANSLATION,
            $data['media']['id'] . ':' . $this->locale,
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

    protected function createAndDeleteNecessaryMappings(array $data, array $converted): void
    {
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PROPERTY_GROUP_OPTION . '_' . $data['type'],
            $data['id'],
            $this->context,
            null,
            null,
            $converted['id']
        );
        $this->mappingIds[] = $mapping['id'];

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PROPERTY_GROUP . '_' . $data['type'],
            $data['group']['id'],
            $this->context,
            null,
            null,
            $converted['group']['id']
        );
        $this->mappingIds[] = $mapping['id'];

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PROPERTY_GROUP_OPTION,
            \hash('md5', \mb_strtolower($data['name'] . '_' . $data['group']['name'] . '_' . $data['type'])),
            $this->context,
            $this->checksum
        );

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PROPERTY_GROUP_OPTION,
            \hash('md5', \mb_strtolower($data['group']['name'] . '_' . $data['type'])),
            $this->context
        );
        $this->mappingIds[] = $mapping['id'];
    }

    /**
     * @psalm-suppress TypeDoesNotContainType
     */
    protected function getTranslation(array &$data, array &$converted): void
    {
        $language = $this->mappingService->getDefaultLanguage($this->context);
        if ($language === null) {
            return;
        }

        $defaultLanguageUuid = $language->getId();
        $converted['translations'][$defaultLanguageUuid] = [];
        $this->convertValue($converted['translations'][$defaultLanguageUuid], 'name', $data, 'name', self::TYPE_STRING);
        $this->convertValue($converted['translations'][$defaultLanguageUuid], 'position', $data, 'position', self::TYPE_INTEGER);

        if (!isset($converted['translations'][$defaultLanguageUuid]['name'])) {
            unset($converted['translations'][$defaultLanguageUuid]);
        }

        $converted['group']['translations'][$defaultLanguageUuid] = [];
        $this->convertValue($converted['group']['translations'][$defaultLanguageUuid], 'name', $data['group'], 'name', self::TYPE_STRING);
        $this->convertValue($converted['group']['translations'][$defaultLanguageUuid], 'description', $data['group'], 'description', self::TYPE_STRING);

        if (!isset($converted['group']['translations'][$defaultLanguageUuid]['name'])) {
            unset($converted['group']['translations'][$defaultLanguageUuid]);
        }

        if ($converted['translations'] === []) {
            unset($converted['translations']);
        }
    }
}
