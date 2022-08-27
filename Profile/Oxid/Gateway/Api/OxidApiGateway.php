<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Gateway\Api;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use SwagMigrationAssistant\Migration\DisplayWarning;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\Reader\EnvironmentReaderInterface;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Oxid\Gateway\OxidGatewayInterface;
use SwagMigrationAssistant\Profile\Oxid\Gateway\TableCountReaderInterface;
use SwagMigrationAssistant\Profile\Oxid\Gateway\TableReaderInterface;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class OxidApiGateway implements OxidGatewayInterface
{
    public const GATEWAY_NAME = 'api';

    /**
     * @var ReaderRegistryInterface
     */
    private $readerRegistry;

    /**
     * @var EnvironmentReaderInterface
     */
    private $environmentReader;

    /**
     * @var TableReaderInterface
     */
    private $tableReader;

    /**
     * @var TableCountReaderInterface
     */
    private $tableCountReader;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    public function __construct(
        ReaderRegistryInterface $readerRegistry,
        EnvironmentReaderInterface $environmentReader,
        TableReaderInterface $tableReader,
        TableCountReaderInterface $tableCountReader,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->readerRegistry = $readerRegistry;
        $this->environmentReader = $environmentReader;
        $this->tableReader = $tableReader;
        $this->tableCountReader = $tableCountReader;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
    }

    public function getName(): string
    {
        return self::GATEWAY_NAME;
    }

    public function getSnippetName(): string
    {
        return 'swag-migration.wizard.pages.connectionCreate.gateways.shopwareApi';
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $reader = $this->readerRegistry->getReader($migrationContext);

        return $reader->read($migrationContext);
    }

    public function readEnvironmentInformation(MigrationContextInterface $migrationContext, Context $context): EnvironmentInformation
    {
        $environmentData = $this->environmentReader->read($migrationContext);
        $environmentDataArray = $environmentData['environmentInformation'];
        $profile = $migrationContext->getProfile();

        if (empty($environmentDataArray)) {
            return new EnvironmentInformation(
                $profile->getSourceSystemName(),
                $profile->getVersion(),
                '',
                [],
                [],
                $environmentData['requestStatus']
            );
        }

        if (!isset($environmentDataArray['translations'])) {
            $environmentDataArray['translations'] = 0;
        }

        $updateAvailable = false;
        if (isset($environmentData['environmentInformation']['updateAvailable'])) {
            $updateAvailable = $environmentData['environmentInformation']['updateAvailable'];
        }

        /** @var CurrencyEntity $targetSystemCurrency */
        $targetSystemCurrency = $this->currencyRepository->search(new Criteria([Defaults::CURRENCY]), $context)->get(Defaults::CURRENCY);
        if (!isset($environmentDataArray['defaultCurrency'])) {
            $environmentDataArray['defaultCurrency'] = $targetSystemCurrency->getIsoCode();
        }

        $criteria = new Criteria([Defaults::LANGUAGE_SYSTEM]);
        $criteria->addAssociation('locale');
        /** @var LanguageEntity $targetSystemLanguage */
        $targetSystemLanguage = $this->languageRepository->search($criteria, $context)->get(Defaults::LANGUAGE_SYSTEM);

        /** @var LocaleEntity $targetSystemLocale */
        $targetSystemLocale = $targetSystemLanguage->getLocale();

        if (!isset($environmentData['defaultShopLanguage'])) {
            $environmentData['defaultShopLanguage'] = $targetSystemLocale->getCode();
        }
        $environmentData['defaultShopLanguage'] = \str_replace('_', '-', $environmentData['defaultShopLanguage']);

        $totals = $this->readTotals($migrationContext, $context);

        $connection = $migrationContext->getConnection();

        if ($connection === null) {
            return new EnvironmentInformation(
                $profile->getSourceSystemName(),
                $profile->getVersion(),
                '',
                [],
                [],
                null
            );
        }

        $credentials = $connection->getCredentialFields();

        if ($credentials === null) {
            return new EnvironmentInformation(
                $profile->getSourceSystemName(),
                $profile->getVersion(),
                '',
                [],
                [],
                null
            );
        }

        $displayWarnings = [];
        if ($updateAvailable) {
            $displayWarnings[] = new DisplayWarning('swag-migration.index.pluginVersionText', [
                'sourceSystem' => 'Shopware 5',
                'pluginName' => 'Migration Connector',
            ]);
        }

        return new EnvironmentInformation(
            $profile->getSourceSystemName(),
            $environmentDataArray['shopwareVersion'],
            $credentials['endpoint'],
            $totals,
            $environmentDataArray['additionalData'],
            $environmentData['requestStatus'],
            $updateAvailable,
            $displayWarnings,
            $targetSystemCurrency->getIsoCode(),
            $environmentDataArray['defaultCurrency'],
            $environmentDataArray['defaultShopLanguage'],
            $targetSystemLocale->getCode()
        );
    }

    public function readTotals(MigrationContextInterface $migrationContext, Context $context): array
    {
        return $this->tableCountReader->readTotals($migrationContext, $context);
    }

    public function readTable(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array
    {
        return $this->tableReader->read($migrationContext, $tableName, $filter);
    }
}
