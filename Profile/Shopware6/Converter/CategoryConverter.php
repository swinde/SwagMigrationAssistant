<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Shopware6\Converter;

use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

abstract class CategoryConverter extends ShopwareConverter
{
    public function getSourceIdentifier(array $data): string
    {
        return $data['id'];
    }

    protected function convertData(array $data): ConvertStruct
    {
        $converted = $data;

        $this->mainMapping = $this->getOrCreateMappingMainCompleteFacade(
            DefaultEntities::CATEGORY,
            $data['id'],
            $converted['id']
        );

        $this->updateAssociationIds(
            $converted['translations'],
            DefaultEntities::LANGUAGE,
            'languageId',
            DefaultEntities::CATEGORY
        );

        if (isset($converted['media'])) {
            $this->updateAssociationIds(
                $converted['media']['translations'],
                DefaultEntities::LANGUAGE,
                'languageId',
                DefaultEntities::MEDIA
            );
            $converted['media']['hasFile'] = false;
        }

        unset(
            // ToDo implement if these associations are migrated
            $converted['cmsPageId']
        );

        return new ConvertStruct($converted, null, $this->mainMapping['id'] ?? null);
    }
}
