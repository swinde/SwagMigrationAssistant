<?php declare(strict_types=1);

namespace SwagMigrationNext\Test\Profile\Shopware55\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Profile\Shopware55\Converter\SalesChannelConverter;
use SwagMigrationAssistant\Profile\Shopware55\DataSelection\DataSet\SalesChannelDataSet;
use SwagMigrationAssistant\Profile\Shopware55\Shopware55Profile;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Mapping\DummyMappingService;

class SalesChannelConverterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var MigrationContext
     */
    private $migrationContext;

    /**
     * @var SalesChannelConverter
     */
    private $converter;

    /**
     * @var DummyLoggingService
     */
    private $loggingService;

    /**
     * @var DummyMappingService
     */
    private $mappingService;

    protected function setUp(): void
    {
        $paymentMethodRepo = $this->getContainer()->get('payment_method.repository');
        $shippingMethodRepo = $this->getContainer()->get('shipping_method.repository');
        $countryRepo = $this->getContainer()->get('country.repository');

        $this->mappingService = new DummyMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->converter = new SalesChannelConverter(
            $this->mappingService,
            $this->loggingService,
            $paymentMethodRepo,
            $shippingMethodRepo,
            $countryRepo
        );

        $runId = Uuid::randomHex();
        $connection = new SwagMigrationConnectionEntity();
        $connection->setId(Uuid::randomHex());

        $this->migrationContext = new MigrationContext(
            $connection,
            $runId,
            new SaleschannelDataSet(),
            0,
            250
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->converter->supports(Shopware55Profile::PROFILE_NAME, new SaleschannelDataSet());

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $context = Context::createDefaultContext();
        $this->mappingService->createNewUuid($this->migrationContext->getConnection()->getId(), DefaultEntities::CUSTOMER_GROUP, '1', $context);
        $this->mappingService->createNewUuid($this->migrationContext->getConnection()->getId(), DefaultEntities::CATEGORY, '3', $context);
        $this->mappingService->createNewUuid($this->migrationContext->getConnection()->getId(), DefaultEntities::CATEGORY, '39', $context);

        $convertResult = $this->converter->convert($salesChannelData[0], $context, $this->migrationContext);
        $this->converter->writeMapping($context);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertCount(3, $converted['languages']);
        static::assertSame('Deutsch', $converted['name']);

        $convertResult = $this->converter->convert($salesChannelData[1], $context, $this->migrationContext);
        $this->converter->writeMapping($context);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertCount(2, $converted['languages']);
        static::assertSame('Gartensubshop', $converted['name']);
    }
}