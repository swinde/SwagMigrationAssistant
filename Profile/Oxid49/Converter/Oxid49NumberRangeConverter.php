<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid49\Converter;

use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\Converter\NumberRangeConverter;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\NumberRangeDataSet;
use SwagMigrationAssistant\Profile\Oxid49\Oxid49Profile;

class Oxid49NumberRangeConverter extends NumberRangeConverter
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Oxid49Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === NumberRangeDataSet::getEntity();
    }
}
