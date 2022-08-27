<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Local\Reader;

use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\OxidLocalGateway;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class MainVariantRelationReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME
            && $this->getDataSetEntity($migrationContext) === DefaultEntities::MAIN_VARIANT_RELATION;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);

        return $this->fetchMainVariantRelations($migrationContext);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $query = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('s_articles')
            ->where('main_detail_id IS NOT NULL')
            ->andWhere('configurator_set_id IS NOT NULL')
            ->execute();

        $total = 0;
        if ($query instanceof ResultStatement) {
            $total = (int) $query->fetchColumn();
        }

        return new TotalStruct(DefaultEntities::MAIN_VARIANT_RELATION, $total);
    }

    private function fetchMainVariantRelations(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder()
            ->addSelect('articles.id, details.ordernumber')
            ->from('s_articles', 'articles')
            ->innerJoin('articles', 's_articles_details', 'details', 'details.id = articles.main_detail_id')
            ->where('main_detail_id IS NOT NULL')
            ->andWhere('configurator_set_id IS NOT NULL')
            ->setFirstResult($migrationContext->getOffset())
            ->setMaxResults($migrationContext->getLimit())
            ->execute();

        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }
}
