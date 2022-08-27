<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Test\Profile\Oxid\Premapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Oxid\Gateway\Local\ShopwareLocalGateway;
use SwagMigrationAssistant\Profile\Oxid\Premapping\TransactionStateReader;
use SwagMigrationAssistant\Profile\Shopware55\Shopware55Profile;

class TransactionStateReaderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext;

    /**
     * @var TransactionStateReader
     */
    private $reader;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var StateMachineStateEntity
     */
    private $stateOpen;

    /**
     * @var StateMachineStateEntity
     */
    private $stateClosed;

    public function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $smRepoMock = $this->createMock(EntityRepository::class);
        $stateMachine = new StateMachineEntity();
        $stateMachine->setId(Uuid::randomHex());
        $stateMachine->setName('Payment state');
        $smRepoMock->method('search')->willReturn(new EntitySearchResult(StateMachineDefinition::ENTITY_NAME, 1, new EntityCollection([$stateMachine]), null, new Criteria(), $this->context));

        $smsRepoMock = $this->createMock(EntityRepository::class);
        $this->stateOpen = new StateMachineStateEntity();
        $this->stateOpen->setId(Uuid::randomHex());
        $this->stateOpen->setName('Open');
        $this->stateOpen->setTechnicalName('open');

        $this->stateClosed = new StateMachineStateEntity();
        $this->stateClosed->setId(Uuid::randomHex());
        $this->stateClosed->setName('Paid');
        $this->stateClosed->setTechnicalName('paid');

        $smsRepoMock->method('search')->willReturn(new EntitySearchResult(StateMachineStateDefinition::ENTITY_NAME, 2, new EntityCollection([$this->stateOpen, $this->stateClosed]), null, new Criteria(), $this->context));

        $connection = new SwagMigrationConnectionEntity();
        $connection->setId(Uuid::randomHex());
        $connection->setProfileName(Shopware55Profile::PROFILE_NAME);
        $connection->setGatewayName(ShopwareLocalGateway::GATEWAY_NAME);
        $connection->setCredentialFields([]);
        $premapping = [[
            'entity' => 'transaction_state',
            'mapping' => [
                0 => [
                    'sourceId' => '1',
                    'description' => 'open',
                    'destinationUuid' => $this->stateOpen->getId(),
                ],
                1 => [
                    'sourceId' => '2',
                    'description' => 'in_process',
                    'destinationUuid' => $this->stateClosed->getId(),
                ],

                2 => [
                    'sourceId' => '300',
                    'description' => 'payment-invalid',
                    'destinationUuid' => Uuid::randomHex(),
                ],
            ],
        ]];
        $connection->setPremapping($premapping);

        $this->migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $connection
        );

        $gatewayMock = $this->createMock(ShopwareLocalGateway::class);
        $gatewayMock->method('readTable')->willReturn([
            ['id' => '1', 'name' => 'open', 'description' => 'Open', 'group' => 'payment', 'mail' => 0],
            ['id' => '2', 'name' => 'completely_paid', 'description' => 'Completely paid', 'group' => 'payment', 'mail' => 0],
            ['id' => '200', 'name' => 'no_description', 'description' => '', 'group' => 'payment', 'mail' => 0],
            ['id' => '300', 'name' => 'payment-invalid', 'description' => 'payment-invalid', 'group' => 'payment', 'mail' => 0],
        ]);

        $gatewayRegistryMock = $this->createMock(GatewayRegistry::class);
        $gatewayRegistryMock->method('getGateway')->willReturn($gatewayMock);

        $this->reader = new TransactionStateReader($smRepoMock, $smsRepoMock, $gatewayRegistryMock);
    }

    public function testGetPremapping(): void
    {
        $result = $this->reader->getPremapping($this->context, $this->migrationContext);

        static::assertInstanceOf(PremappingStruct::class, $result);
        static::assertCount(4, $result->getMapping());
        static::assertCount(2, $result->getChoices());

        $choices = $result->getChoices();
        static::assertSame('Open', $choices[0]->getDescription());
        static::assertSame('Paid', $choices[1]->getDescription());
        static::assertSame('no_description', $result->getMapping()[2]->getDescription());
        static::assertSame($this->stateClosed->getId(), $result->getMapping()[0]->getDestinationUuid());
        static::assertSame($this->stateOpen->getId(), $result->getMapping()[1]->getDestinationUuid());
        static::assertEmpty($result->getMapping()[2]->getDestinationUuid());
        static::assertEmpty($result->getMapping()[3]->getDestinationUuid());
    }
}
