<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Converter;

use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

abstract class CategoryAttributeConverter extends AttributeConverter
{
    protected function getCustomFieldEntityName(): string
    {
        return DefaultEntities::CATEGORY;
    }
}
