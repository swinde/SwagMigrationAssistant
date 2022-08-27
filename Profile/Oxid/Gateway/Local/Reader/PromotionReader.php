<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\OxidLocalGateway;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class PromotionReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME
            && $this->getDataSetEntity($migrationContext) === DefaultEntities::PROMOTION;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);
        $ids = $this->fetchIdentifiers('s_emarketing_vouchers', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedPromotions = $this->fetchPromotions($ids);
        $fetchedCodes = $this->fetchIndividualCodes($ids);
        $fetchedPromotions = $this->mapData($fetchedPromotions, [], ['vouchers']);

        foreach ($fetchedPromotions as &$promotion) {
            $promotionId = $promotion['id'];

            if (isset($fetchedCodes[$promotionId])) {
                $promotion['individualCodes'] = $fetchedCodes[$promotionId];
            }
        }

        return $this->cleanupResultSet($fetchedPromotions);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $query = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('s_emarketing_vouchers')
            ->execute();

        $total = 0;
        if ($query instanceof ResultStatement) {
            $total = (int) $query->fetchColumn();
        }

        return new TotalStruct(DefaultEntities::PROMOTION, $total);
    }

    private function fetchIndividualCodes(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_emarketing_voucher_codes', 'codes');
        $query->addSelect('codes.voucherID');
        $this->addTableSelection($query, 's_emarketing_voucher_codes', 'codes');

        $query->leftJoin('codes', 's_user', 'user', 'codes.userID = user.id');
        $query->addSelect('user.firstname AS `codes.firstname`, user.lastname AS `codes.lastname`');

        $query->where('codes.voucherID IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $fetchedCodes = $query->fetchAll(\PDO::FETCH_GROUP);

        return $this->mapData($fetchedCodes, [], ['codes']);
    }

    private function fetchPromotions(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_emarketing_vouchers', 'vouchers');
        $this->addTableSelection($query, 's_emarketing_vouchers', 'vouchers');

        $query->where('vouchers.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll();
    }
}
