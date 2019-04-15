<?php declare(strict_types=1);

namespace SwagMigrationNext\Test\Mock\Migration\Mapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use SwagMigrationNext\Migration\Mapping\MappingService;

class DummyMappingService extends MappingService
{
    public const DEFAULT_LANGUAGE_UUID = '20080911ffff4fffafffffff19830531';
    public const DEFAULT_LOCAL_UUID = '20080911ffff4fffafffffff19830531';

    public function __construct()
    {
    }

    public function readExistingMappings(Context $context): void
    {
    }

    public function createNewUuid(
        string $connectionId,
        string $entityName,
        string $oldId,
        Context $context,
        ?array $additionalData = null,
        ?string $newUuid = null
    ): string {
        $uuid = $this->getUuid($connectionId, $entityName, $oldId, $context);
        if ($uuid !== null) {
            return $uuid;
        }

        $uuid = Uuid::randomHex();
        if ($newUuid !== null) {
            $uuid = $newUuid;
        }

        $this->uuids[$connectionId][$entityName][$oldId] = $uuid;

        return $uuid;
    }

    public function saveMapping(array $mapping): void
    {
    }

    public function setProfile(string $profileName): void
    {
    }

    public function getUuid(string $connectionId, string $entityName, string $oldId, Context $context): ?string
    {
        return $this->uuids[$connectionId][$entityName][$oldId] ?? null;
    }

    public function getUuidList(string $connectionId, string $entityName, string $identifier, Context $context): array
    {
        return $this->uuids[$connectionId][$entityName][$identifier] ?? [];
    }

    public function deleteMapping(string $entityUuid, string $connectionId, Context $context): void
    {
        foreach ($this->writeArray as $writeMapping) {
            if ($writeMapping['profile'] === $connectionId && $writeMapping['entityUuid'] === $entityUuid) {
                unset($writeMapping);
                break;
            }
        }
    }

    public function pushMapping(string $connectionId, string $entity, string $oldIdentifier, string $uuid)
    {
        $this->uuids[$connectionId][$entity][$oldIdentifier] = $uuid;
    }

    public function bulkDeleteMapping(array $mappingUuids, Context $context): void
    {
    }

    public function getLanguageUuid(string $connectionId, string $localeCode, Context $context): array
    {
        return [
            'uuid' => self::DEFAULT_LANGUAGE_UUID,
            'createData' => [
                'localeId' => self::DEFAULT_LOCAL_UUID,
                'localeCode' => 'de_DE',
            ],
        ];
    }

    public function getCountryUuid(string $oldId, string $iso, string $iso3, string $connectionId, Context $context): ?string
    {
        return null;
    }

    public function getCurrencyUuid(string $oldShortName, Context $context): ?string
    {
        return null;
    }

    public function getTaxUuid(float $taxRate, Context $context): ?string
    {
        return null;
    }

    public function getPrivateUuidArray(): array
    {
        return $this->uuids;
    }

    public function getDefaultLanguageUuid(Context $context): array
    {
        return [
            'uuid' => self::DEFAULT_LANGUAGE_UUID,
            'createData' => [
                'localeId' => Uuid::randomHex(),
                'localeCode' => 'en_GB',
            ],
        ];
    }
}
