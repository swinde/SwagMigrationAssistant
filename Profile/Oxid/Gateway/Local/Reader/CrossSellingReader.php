<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Local\Reader;

use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\OxidLocalGateway;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class CrossSellingReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CROSS_SELLING;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);

        $fetchedCrossSelling = $this->fetchData($migrationContext);
        $this->enrichWithPositionData($fetchedCrossSelling, $migrationContext->getOffset());

        return $this->cleanupResultSet($fetchedCrossSelling);
    }

    public function readTotal(MigrationContextInterface $migrationContext): TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT
    COUNT(*)
FROM
    (
        SELECT 'accessory' AS type, accessory.* FROM s_articles_relationships AS accessory
        UNION
        SELECT 'similar' AS type, similar.* FROM s_articles_similar AS similar
    ) AS result
SQL;

        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::CROSS_SELLING, $total);
    }

    protected function fetchData(MigrationContextInterface $migrationContext): array
    {
        $sql = <<<SQL
SELECT * FROM (
    SELECT
           :accessory AS type,
           acce.*
    FROM s_articles_relationships AS acce

    UNION

    SELECT
           :similar AS type,
           similar.*
    FROM s_articles_similar AS similar
) cross_selling
ORDER BY cross_selling.type, cross_selling.articleID LIMIT :limit OFFSET :offset
SQL;

        $statement = $this->connection->prepare($sql);
        $statement->bindValue('accessory', DefaultEntities::CROSS_SELLING_ACCESSORY, \PDO::PARAM_STR);
        $statement->bindValue('similar', DefaultEntities::CROSS_SELLING_SIMILAR, \PDO::PARAM_STR);
        $statement->bindValue('limit', $migrationContext->getLimit(), \PDO::PARAM_INT);
        $statement->bindValue('offset', $migrationContext->getOffset(), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAllAssociative();
    }

    private function enrichWithPositionData(array &$fetchedCrossSelling, int $offset): void
    {
        foreach ($fetchedCrossSelling as &$item) {
            $item['position'] = $offset++;
        }
    }
}
