<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Test\Profile\Oxid\Gateway;

use Doctrine\DBAL\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use SwagMigrationAssistant\Exception\ReaderNotFoundException;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\Profile\ProfileInterface;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\DataSet\ProductDataSet;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Connection\ConnectionFactory;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\Reader\EnvironmentReader;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\Reader\TableReader;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\OxidLocalGateway;
use SwagMigrationAssistant\Profile\Oxid49\Oxid49Profile;
use SwagMigrationAssistant\Profile\Oxid6\Oxid6Profile;
use SwagMigrationAssistant\Test\Profile\Oxid\DataSet\FooDataSet;

class LocalGatewayTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider profileProvider
     */
    public function testReadFailedNoCredentials(string $profileName, ProfileInterface $profile): void
    {
        $connection = new SwagMigrationConnectionEntity();
        $connection->setProfileName($profileName);
        $connection->setGatewayName(OxidLocalGateway::GATEWAY_NAME);
        $connection->setCredentialFields(
            [
                'dbName' => '',
                'dbUser' => '',
                'dbPassword' => '',
                'dbHost' => '',
                'dbPort' => '',
            ]
        );

        $migrationContext = new MigrationContext(
            $profile,
            $connection,
            '',
            new ProductDataSet()
        );

        $connectionFactory = new ConnectionFactory();
        $readerRegistry = $this->getContainer()->get(ReaderRegistry::class);
        $localEnvironmentReader = new EnvironmentReader($connectionFactory);
        $localTableReader = new TableReader($connectionFactory);
        /** @var EntityRepositoryInterface $currencyRepository */
        $currencyRepository = $this->getContainer()->get('currency.repository');

        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');

        $gatewaySource = new OxidLocalGateway(
            $readerRegistry,
            $localEnvironmentReader,
            $localTableReader,
            $connectionFactory,
            $currencyRepository,
            $languageRepository
        );
        $migrationContext->setGateway($gatewaySource);
        $gatewayRegistry = new GatewayRegistry([
            $gatewaySource,
        ]);
        $gateway = $gatewayRegistry->getGateway($migrationContext);

        $this->expectException(ConnectionException::class);
        $gateway->read($migrationContext);
    }

    /**
     * @dataProvider profileProvider
     */
    public function testReadWithUnknownEntityThrowsException(string $profileName, ProfileInterface $profile): void
    {
        $connection = new SwagMigrationConnectionEntity();
        $connection->setProfileName($profileName);
        $connection->setGatewayName(OxidLocalGateway::GATEWAY_NAME);
        $connection->setCredentialFields(
            [
                'dbName' => '',
                'dbUser' => '',
                'dbPassword' => '',
                'dbHost' => '',
                'dbPort' => '',
            ]
        );

        $migrationContext = new MigrationContext(
            $profile,
            $connection,
            '',
            new FooDataSet()
        );

        $connectionFactory = new ConnectionFactory();
        $readerRegistry = $this->getContainer()->get(ReaderRegistry::class);
        $localEnvironmentReader = new EnvironmentReader($connectionFactory);
        $localTableReader = new TableReader($connectionFactory);
        /** @var EntityRepositoryInterface $currencyRepository */
        $currencyRepository = $this->getContainer()->get('currency.repository');

        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');

        $gatewaySource = new OxidLocalGateway(
            $readerRegistry,
            $localEnvironmentReader,
            $localTableReader,
            $connectionFactory,
            $currencyRepository,
            $languageRepository
        );
        $migrationContext->setGateway($gatewaySource);
        $gatewayRegistry = new GatewayRegistry([
            $gatewaySource,
        ]);

        $gateway = $gatewayRegistry->getGateway($migrationContext);

        $this->expectException(ReaderNotFoundException::class);
        $gateway->read($migrationContext);
    }

    /**
     * @dataProvider profileProvider
     */
    public function testReadEnvironmentInformationHasEmptyResult(string $profileName, ProfileInterface $profile): void
    {
        $connection = new SwagMigrationConnectionEntity();
        $connection->setProfileName($profileName);
        $connection->setGatewayName(OxidLocalGateway::GATEWAY_NAME);
        $connection->setCredentialFields([]);

        $migrationContext = new MigrationContext(
            $profile,
            $connection
        );

        $readerRegistry = $this->getContainer()->get(ReaderRegistry::class);
        $connectionFactory = new ConnectionFactory();
        $localEnvironmentReader = new EnvironmentReader($connectionFactory);
        $localTableReader = new TableReader($connectionFactory);
        /** @var EntityRepositoryInterface $currencyRepository */
        $currencyRepository = $this->getContainer()->get('currency.repository');

        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');

        $gateway = new OxidLocalGateway(
            $readerRegistry,
            $localEnvironmentReader,
            $localTableReader,
            $connectionFactory,
            $currencyRepository,
            $languageRepository
        );
        $migrationContext->setGateway($gateway);
        $response = $gateway->readEnvironmentInformation($migrationContext, Context::createDefaultContext());

        static::assertSame($response->getTotals(), []);
    }

    public function profileProvider()
    {
        return [
            [
				Oxid49Profile::PROFILE_NAME,
				new Oxid49Profile(),
            ],
            [
                Oxid6Profile::PROFILE_NAME,
                new Oxid6Profile(),
            ],
        ];
    }
}
