<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Query\QueryBuilder;
use SwagMigrationAssistant\Migration\Gateway\Reader\EnvironmentReaderInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Connection\ConnectionFactoryInterface;

class EnvironmentReader implements EnvironmentReaderInterface
{
    /**
     * @var ConnectionFactoryInterface
     */
    protected $connectionFactory;

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);
        $locale = $this->getDefaultShopLocale();

        $resultSet = [
            'defaultShopLanguage' => $locale,
            'host' => $this->getHost(),
            'additionalData' => $this->getAdditionalData(),
            'defaultCurrency' => $this->getDefaultCurrency(),
        ];

        return $resultSet;
    }

    protected function setConnection(MigrationContextInterface $migrationContext): void
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);

        if ($connection === null) {
            return;
        }

        $this->connection = $connection;
    }

    protected function addTableSelection(QueryBuilder $query, string $table, string $tableAlias): void
    {
        $columns = $this->connection->getSchemaManager()->listTableColumns($table);

        foreach ($columns as $column) {
            $selection = \str_replace(
                ['#tableAlias#', '#column#'],
                [$tableAlias, $column->getName()],
                '`#tableAlias#`.`#column#` as `#tableAlias#.#column#`'
            );

            $query->addSelect($selection);
        }
    }

    protected function getDefaultCurrency(): string
    {
        $query = $this->connection->createQueryBuilder()
            ->select('currency')
            ->from('s_core_currencies')
            ->where('standard = 1')
            ->execute();

        $defaultCurrency = '';
        if ($query instanceof ResultStatement) {
            $defaultCurrency = (string) $query->fetchColumn();
        }

        return $defaultCurrency;
    }

    protected function getDefaultShopLocale(): string
    {
        $query = $this->connection->createQueryBuilder()
            ->select('locale.locale')
            ->from('s_core_locales', 'locale')
            ->innerJoin('locale', 's_core_shops', 'shop', 'locale.id = shop.locale_id')
            ->where('shop.default = 1')
            ->andWhere('shop.active = 1')
            ->execute();

        $defaultShopLocale = '';
        if ($query instanceof ResultStatement) {
            $defaultShopLocale = (string) $query->fetchColumn();
        }

        return $defaultShopLocale;
    }

    protected function mapData(array $data, array $result = [], array $pathsToRemove = []): array
    {
        foreach ($data as $key => $value) {
            if (\is_numeric($key)) {
                $result[$key] = $this->mapData($value, [], $pathsToRemove);
            } else {
                $paths = \explode('.', $key);
                $fieldKey = $paths[\count($paths) - 1];
                $chunks = \explode('_', $paths[0]);

                if (!empty($pathsToRemove)) {
                    $chunks = \array_diff($chunks, $pathsToRemove);
                }
                $this->buildArrayFromChunks($result, $chunks, $fieldKey, $value);
            }
        }

        return $result;
    }

    /**
     * @psalm-suppress MissingParamType
     */
    private function buildArrayFromChunks(array &$array, array $path, string $fieldKey, $value): void
    {
        $key = \array_shift($path);

        if (empty($key)) {
            $array[$fieldKey] = $value;
        } elseif (empty($path)) {
            $array[$key][$fieldKey] = $value;
        } else {
            if (!isset($array[$key]) || !\is_array($array[$key])) {
                $array[$key] = [];
            }
            $this->buildArrayFromChunks($array[$key], $path, $fieldKey, $value);
        }
    }

    private function getHost(): string
    {
        $query = $this->connection->createQueryBuilder()
            ->select('shop.host')
            ->from('s_core_shops', 'shop')
            ->where('shop.default = 1')
            ->andWhere('shop.active = 1')
            ->execute();

        $host = '';
        if ($query instanceof ResultStatement) {
            $host = (string) $query->fetchColumn();
        }

        return $host;
    }

    private function getAdditionalData(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_core_shops', 'shop');
        $query->addSelect('shop.id as identifier');
        $this->addTableSelection($query, 's_core_shops', 'shop');

        $query->leftJoin('shop', 's_core_locales', 'locale', 'shop.locale_id = locale.id');
        $this->addTableSelection($query, 's_core_locales', 'locale');

        $query->orderBy('shop.main_id');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $fetchedShops = $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
        $shops = $this->mapData($fetchedShops, [], ['shop']);

        foreach ($shops as $key => &$shop) {
            if (!empty($shop['main_id'])) {
                $shops[$shop['main_id']]['children'][] = $shop;
                unset($shops[$key]);
            }
        }

        return \array_values($shops);
    }
}
