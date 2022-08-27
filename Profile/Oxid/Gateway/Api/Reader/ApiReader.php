<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Api\Reader;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use SwagMigrationAssistant\Exception\GatewayReadException;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Connection\ConnectionFactoryInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

abstract class ApiReader implements ReaderInterface
{
    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    public function __construct(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return false;
    }

    /**
     * @throws GatewayReadException
     */
    public function read(MigrationContextInterface $migrationContext): array
    {
        $queryParams = [
            'offset' => $migrationContext->getOffset(),
            'limit' => $migrationContext->getLimit(),
        ];

        $queryParams = \array_merge($queryParams, $this->getExtraParameters());
        $client = $this->connectionFactory->createApiClient($migrationContext);

        if ($client === null) {
            return [];
        }

        /** @var GuzzleResponse $result */
        $result = $client->get(
            $this->getApiRoute(),
            [
                'query' => $queryParams,
            ]
        );

        if ($result->getStatusCode() !== SymfonyResponse::HTTP_OK) {
            throw new GatewayReadException('Oxid Api ' . $migrationContext->getDataSet()::getEntity());
        }

        $arrayResult = \json_decode($result->getBody()->getContents(), true);

        return $arrayResult['data'];
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        return null;
    }

    abstract protected function getApiRoute(): string;

    /**
     * @return string[]
     */
    protected function getExtraParameters(): array
    {
        return [];
    }

    protected function getDataSetEntity(MigrationContextInterface $migrationContext): ?string
    {
        $dataSet = $migrationContext->getDataSet();
        if ($dataSet === null) {
            return null;
        }

        return $dataSet::getEntity();
    }
}
