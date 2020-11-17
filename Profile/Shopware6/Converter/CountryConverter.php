<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Shopware6\Converter;

use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

abstract class CountryConverter extends ShopwareConverter
{
    public function getSourceIdentifier(array $data): string
    {
        return $data['id'];
    }

    protected function convertData(array $data): ConvertStruct
    {
        $converted = $data;

        $countryUuid = $this->mappingService->getCountryUuid($data['id'], $data['iso'], $data['iso3'], $this->connectionId, $this->context);

        if ($countryUuid !== null) {
            // the mapping is still needed for dependencies
            $this->mainMapping = $this->getOrCreateMappingMainCompleteFacade(
                DefaultEntities::COUNTRY,
                $data['id'],
                $countryUuid
            );

            return new ConvertStruct(null, $data, $this->mainMapping['id']);
        }

        $this->mainMapping = $this->getOrCreateMappingMainCompleteFacade(
            DefaultEntities::COUNTRY,
            $data['id'],
            $converted['id']
        );

        $this->updateAssociationIds(
            $converted['translations'],
            DefaultEntities::LANGUAGE,
            'languageId',
            DefaultEntities::COUNTRY
        );

        return new ConvertStruct($converted, null, $this->mainMapping['id'] ?? null);
    }
}
