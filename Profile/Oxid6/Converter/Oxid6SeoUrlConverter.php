<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid6\Converter;

use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\Converter\SeoUrlConverter;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\SeoUrlDataSet;
use SwagMigrationAssistant\Profile\Oxid6\Oxid6Profile;

class Oxid6SeoUrlConverter extends SeoUrlConverter
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Oxid6Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === SeoUrlDataSet::getEntity();
    }
}
