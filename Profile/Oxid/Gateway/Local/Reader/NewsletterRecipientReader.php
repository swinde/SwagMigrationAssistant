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

class NewsletterRecipientReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::NEWSLETTER_RECIPIENT;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);

        $ids = $this->fetchIdentifiers('s_campaigns_mailaddresses', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedRecipients = $this->fetchData($ids);

        $newsletterData = $this->mapData($fetchedRecipients, [], ['recipient']);

        $shopsByCustomer = $this->getShopsAndLocalesByCustomer($ids);
        $defaultShop = $this->getDefaultShopAndLocaleByGroupId();
        $shops = $this->getShopsAndLocalesByGroupId();

        foreach ($newsletterData as &$item) {
            if (\is_array($item) && isset($item['customer'], $shopsByCustomer[$item['id']][0]['shopId']) && $item['customer'] === '1') {
                $this->addShopAndLocaleByCustomer($item, $shopsByCustomer[$item['id']][0]);

                continue;
            }

            $this->addShopAndLocaleByGroupId($item, $defaultShop, $shops);
        }
        unset($item);

        return $this->cleanupResultSet($newsletterData);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $query = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('s_campaigns_mailaddresses')
            ->execute();

        $total = 0;
        if ($query instanceof ResultStatement) {
            $total = (int) $query->fetchColumn();
        }

        return new TotalStruct(DefaultEntities::NEWSLETTER_RECIPIENT, $total);
    }

    private function addShopAndLocaleByGroupId(array &$item, array $defaultShop, array $shops): void
    {
        if (isset($defaultShop[$item['groupID']][0])) {
            $item['shopId'] = $defaultShop[$item['groupID']][0]['shopId'];
            $item['_locale'] = \str_replace('_', '-', $defaultShop[$item['groupID']][0]['locale']);

            return;
        }

        if (isset($shops[$item['groupID']])) {
            $shop = $shops[$item['groupID']][0];
            $shopId = $shop['mainId'] ?? $shop['shopId'];

            $item['shopId'] = $shopId;
            $item['_locale'] = \str_replace('_', '-', $shop['locale']);
        }
    }

    private function addShopAndLocaleByCustomer(array &$item, array $shop): void
    {
        $shopId = $shop['mainId'] ?? $shop['shopId'];

        $item['shopId'] = $shopId;
        $item['_locale'] = \str_replace('_', '-', $shop['locale']);
    }

    private function fetchData(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_campaigns_mailaddresses', 'recipient');
        $this->addTableSelection($query, 's_campaigns_mailaddresses', 'recipient');

        $query->leftJoin('recipient', 's_campaigns_maildata', 'recipient_address', 'recipient.email = recipient_address.email');
        $this->addTableSelection($query, 's_campaigns_maildata', 'recipient_address');

        $query->where('recipient.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll();
    }

    private function getDefaultShopAndLocaleByGroupId(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('config.value as groupID');
        $query->addSelect('shop.id as shopId');
        $query->addSelect('locale.locale as locale');

        $query->from('s_core_config_elements', 'config');
        $query->innerJoin('config', 's_core_shops', 'shop', 'shop.default = true');
        $query->innerJoin('shop', 's_core_locales', 'locale', 'locale.id = shop.locale_id');
        $query->where('config.name = \'newsletterdefaultgroup\'');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $shops = $query->fetchAll();

        return $this->getGroupedResult($shops);
    }

    private function getShopsAndLocalesByGroupId(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('config_values.value as groupID');
        $query->addSelect('shop.id as shopId');
        $query->addSelect('shop.main_id as mainId');
        $query->addSelect('locale.locale as locale');

        $query->from('s_core_config_elements', 'config');
        $query->innerJoin('config', 's_core_config_values', 'config_values', 'config.id = config_values.element_id');
        $query->innerJoin('config', 's_core_shops', 'shop', 'shop.id = config_values.shop_id');
        $query->innerJoin('shop', 's_core_locales', 'locale', 'locale.id = shop.locale_id');
        $query->where('config.name = \'newsletterdefaultgroup\'');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $shops = $query->fetchAll();

        return $this->getGroupedResult($shops);
    }

    private function getShopsAndLocalesByCustomer(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('addresses.id as addressId');
        $query->addSelect('shop.id as shopId');
        $query->addSelect('shop.main_id as mainId');
        $query->addSelect('locale.locale as locale');

        $query->from('s_campaigns_mailaddresses', 'addresses');
        $query->innerJoin('addresses', 's_user', 'users', 'users.email = addresses.email and users.accountmode = 0');
        $query->innerJoin('users', 's_core_shops', 'shop', 'shop.id = users.subshopID');
        $query->innerJoin('users', 's_core_shops', 'language', 'language.id = users.language');
        $query->innerJoin('language', 's_core_locales', 'locale', 'locale.id = language.locale_id');
        $query->where('addresses.customer = 1');
        $query->andWhere('addresses.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP);
    }

    private function getGroupedResult(array $shops): array
    {
        $resultSet = [];

        foreach ($shops as $shop) {
            $groupId = \unserialize($shop['groupID'], ['allowed_classes' => false]);
            if (!isset($resultSet[$groupId])) {
                $resultSet[$groupId] = [];
            }
            $resultSet[$groupId][] = $shop;
        }

        return $resultSet;
    }
}
