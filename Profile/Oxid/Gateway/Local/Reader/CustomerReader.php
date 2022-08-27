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

class CustomerReader extends AbstractReader
{
    /**
     * @var int
     */
    private const MAX_ADDRESS_COUNT = 100;

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CUSTOMER;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && $migrationContext->getGateway()->getName() === OxidLocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);

        $fetchedCustomers = $this->fetchCustomers($migrationContext);
        $ids = \array_column($fetchedCustomers, 'customer.id');

        $customers = $this->mapData($fetchedCustomers, [], ['customer', 'customerGroupId']);
        $resultSet = $this->assignAssociatedData($customers, $ids);

        return $this->cleanupResultSet($resultSet);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $query = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('s_user')
            ->execute();

        $total = 0;
        if ($query instanceof ResultStatement) {
            $total = (int) $query->fetchColumn();
        }

        return new TotalStruct(DefaultEntities::CUSTOMER, $total);
    }

    private function fetchCustomers(MigrationContextInterface $migrationContext): array
    {
        $ids = $this->fetchIdentifiers('s_user', $migrationContext->getOffset(), $migrationContext->getLimit());

        $query = $this->connection->createQueryBuilder();

        $query->from('s_user', 'customer');
        $this->addTableSelection($query, 's_user', 'customer');

        $query->leftJoin('customer', 's_user_attributes', 'attributes', 'customer.id = attributes.userID');
        $this->addTableSelection($query, 's_user_attributes', 'attributes');

        $query->leftJoin('customer', 's_core_customergroups', 'customer_group', 'customer.customergroup = customer_group.groupkey');
        $query->addSelect('customer_group.id as customerGroupId');

        $query->leftJoin('customer', 's_core_paymentmeans', 'defaultpayment', 'customer.paymentID = defaultpayment.id');
        $this->addTableSelection($query, 's_core_paymentmeans', 'defaultpayment');

        $query->leftJoin('defaultpayment', 's_core_paymentmeans_attributes', 'defaultpayment_attributes', 'defaultpayment.id = defaultpayment_attributes.paymentmeanID');
        $this->addTableSelection($query, 's_core_paymentmeans_attributes', 'defaultpayment_attributes');

        $query->leftJoin('customer', 's_core_locales', 'customerlanguage', 'customer.language = customerlanguage.id');
        $this->addTableSelection($query, 's_core_locales', 'customerlanguage');

        $query->where('customer.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query->addOrderBy('customer.id');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll();
    }

    private function assignAssociatedData(array $customers, array $ids): array
    {
        $customerAddresses = $this->fetchCustomerAdresses($ids);
        $addresses = $this->mapData($customerAddresses, [], ['address']);

        $fetchedPaymentData = $this->fetchPaymentData($ids);
        $paymentData = $this->mapData($fetchedPaymentData, [], ['paymentdata']);

        // represents the main language of the migrated shop
        $locale = $this->getDefaultShopLocale();

        foreach ($customers as &$customer) {
            $customer['_locale'] = \str_replace('_', '-', $locale);
            if (isset($addresses[$customer['id']])) {
                $customer['addresses'] = \array_slice($addresses[$customer['id']], 0, self::MAX_ADDRESS_COUNT);
            }
            if (isset($paymentData[$customer['id']])) {
                $customer['paymentdata'] = $paymentData[$customer['id']];
            }
            if (isset($customer['customerlanguage']['locale'])) {
                $customer['customerlanguage']['locale'] = \str_replace('_', '-', $customer['customerlanguage']['locale']);
            }
        }
        unset($customer);

        return $customers;
    }

    private function fetchCustomerAdresses(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_user_addresses', 'address');
        $query->addSelect('address.user_id');
        $this->addTableSelection($query, 's_user_addresses', 'address');

        $query->leftJoin('address', 's_user_addresses_attributes', 'address_attributes', 'address.id = address_attributes.address_id');
        $this->addTableSelection($query, 's_user_addresses_attributes', 'address_attributes');

        $query->leftJoin('address', 's_core_countries', 'country', 'address.country_id = country.id');
        $this->addTableSelection($query, 's_core_countries', 'country');

        $query->leftJoin('address', 's_core_countries_states', 'state', 'address.state_id = state.id');
        $this->addTableSelection($query, 's_core_countries_states', 'state');

        $query->where('address.user_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP);
    }

    private function fetchPaymentData(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_core_payment_data', 'paymentdata');
        $query->addSelect('paymentdata.user_id');
        $this->addTableSelection($query, 's_core_payment_data', 'paymentdata');

        $query->where('paymentdata.user_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP);
    }
}
