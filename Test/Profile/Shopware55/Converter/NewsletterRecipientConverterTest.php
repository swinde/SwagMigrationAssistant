<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Test\Profile\Shopware55\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\NewsletterRecipientDataSet;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ShopwareLocalGateway;
use SwagMigrationAssistant\Profile\Shopware\Premapping\SalutationReader;
use SwagMigrationAssistant\Profile\Shopware55\Converter\Shopware55NewsletterRecipientConverter;
use SwagMigrationAssistant\Profile\Shopware55\Shopware55Profile;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Mapping\DummyMappingService;

class NewsletterRecipientConverterTest extends TestCase
{
    /**
     * @var DummyMappingService
     */
    private $mappingService;

    /**
     * @var DummyLoggingService
     */
    private $loggingService;

    /**
     * @var Shopware55NewsletterRecipientConverter
     */
    private $newsletterReceiverConverter;

    /**
     * @var string
     */
    private $runId;

    /**
     * @var SwagMigrationConnectionEntity
     */
    private $connection;

    /**
     * @var string
     */
    private $connectionId;

    /**
     * @var MigrationContext
     */
    private $context;

    protected function setUp(): void
    {
        $this->mappingService = new DummyMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->newsletterReceiverConverter = new Shopware55NewsletterRecipientConverter($this->mappingService, $this->loggingService);

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connectionId = Uuid::randomHex();
        $this->connection->setId($this->connectionId);
        $this->connection->setProfileName(Shopware55Profile::PROFILE_NAME);
        $this->connection->setGatewayName(ShopwareLocalGateway::GATEWAY_NAME);

        $this->context = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runId,
            new NewsletterRecipientDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $this->mappingService->createNewUuid($this->connectionId, SalutationReader::getMappingName(), 'mr',
            $context, [], Uuid::randomHex());
        $this->mappingService->createNewUuid($this->connectionId, SalutationReader::getMappingName(), 'ms',
            $context, [], Uuid::randomHex());
        $this->mappingService->createNewUuid($this->connectionId, SalesChannelDefinition::ENTITY_NAME, '1',
            $context, [], Uuid::randomHex());
    }

    public function testConvertWithoutDoubleOptinConfirmed(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/invalid/newsletter_recipient_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->newsletterReceiverConverter->convert(
            $customerData[0],
            $context,
            $this->context
        );

        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        $description = sprintf('NewsletterRecipient-Entity could not be converted cause of empty necessary field(s): %s.', 'status');
        static::assertSame($description, $logs[0]['logEntry']['description']);
    }

    public function testConvertWithNotExistingSalutation(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/invalid/newsletter_recipient_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->newsletterReceiverConverter->convert(
            $customerData[1],
            $context,
            $this->context
        );

        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        $description = sprintf('NewsletterRecipient-Entity could not be converted cause of unknown salutation');
        static::assertSame($description, $logs[0]['logEntry']['description']);
    }

    public function testConvert(): void
    {
        $data = require __DIR__ . '/../../../_fixtures/newsletter_recipient_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->newsletterReceiverConverter->convert(
            $data[0],
            $context,
            $this->context
        );
        $converted = $convertResult->getConverted();
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('email', $converted);
        static::assertArrayHasKey('salutationId', $converted);
        static::assertArrayHasKey('languageId', $converted);
    }
}