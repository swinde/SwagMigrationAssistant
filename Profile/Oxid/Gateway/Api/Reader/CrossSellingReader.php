<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Api\Reader;

use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Api\OxidApiGateway;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class CrossSellingReader extends ApiReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidApiGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CROSS_SELLING;
    }

    protected function getApiRoute(): string
    {
        return 'SwagMigrationCrossSelling';
    }
}
