<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Premapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Salutation\SalutationEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\CustomerAndOrderDataSelection;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\NewsletterRecipientDataSelection;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\ProductReviewDataSelection;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\PromotionDataSelection;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\WishlistDataSelection;
use SwagMigrationAssistant\Profile\Oxid\Gateway\ShopwareGatewayInterface;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class SalutationReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'salutation';

    /**
     * @var string[]
     */
    protected $preselectionDictionary = [];

    /**
     * @var EntityRepositoryInterface
     */
    private $salutationRepo;

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var string[]
     */
    private $choiceUuids;

    public function __construct(
        EntityRepositoryInterface $salutationRepo,
        GatewayRegistryInterface $gatewayRegistry
    ) {
        $this->salutationRepo = $salutationRepo;
        $this->gatewayRegistry = $gatewayRegistry;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && (\in_array(CustomerAndOrderDataSelection::IDENTIFIER, $entityGroupNames, true)
            || \in_array(NewsletterRecipientDataSelection::IDENTIFIER, $entityGroupNames, true)
            || \in_array(ProductReviewDataSelection::IDENTIFIER, $entityGroupNames, true)
            || \in_array(WishlistDataSelection::IDENTIFIER, $entityGroupNames, true)
            || \in_array(PromotionDataSelection::IDENTIFIER, $entityGroupNames, true));
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $choices = $this->getChoices($context);
        $mapping = $this->getMapping($migrationContext);
        $this->setPreselection($mapping);

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * @return PremappingEntityStruct[]
     */
    private function getMapping(MigrationContextInterface $migrationContext): array
    {
        /** @var ShopwareGatewayInterface $gateway */
        $gateway = $this->gatewayRegistry->getGateway($migrationContext);

        $result = $gateway->readTable($migrationContext, 's_core_config_elements', ['name' => 'shopsalutations']);
        if (empty($result)) {
            return [];
        }

        $salutations = [];
        $salutations[] = \explode(',', \unserialize($result[0]['value'], ['allowed_classes' => false]));
        $salutations = \array_filter($salutations);

        if (empty($salutations)) {
            return [];
        }

        $configuredSalutations = $gateway->readTable($migrationContext, 's_core_config_values', ['element_id' => $result[0]['id']]);

        if (!empty($configuredSalutations)) {
            foreach ($configuredSalutations as $configuredSalutation) {
                $salutations[] = \explode(
                    ',',
                    \unserialize($configuredSalutation['value'], ['allowed_classes' => false])
                );
            }
        }

        $salutations = \array_values(\array_unique(\array_merge(...$salutations)));
        $entityData = [];

        foreach ($salutations as $salutation) {
            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$salutation])) {
                $uuid = $this->connectionPremappingDictionary[$salutation]['destinationUuid'];

                if (!isset($this->choiceUuids[$uuid])) {
                    $uuid = '';
                }
            }

            $entityData[] = new PremappingEntityStruct($salutation, $salutation, $uuid);
        }
        \usort($entityData, function (PremappingEntityStruct $item1, PremappingEntityStruct $item2) {
            return \strcmp($item1->getDescription(), $item2->getDescription());
        });

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('salutationKey'));
        $salutations = $this->salutationRepo->search($criteria, $context);

        $choices = [];
        /** @var SalutationEntity $salutation */
        foreach ($salutations as $salutation) {
            $key = $salutation->getSalutationKey() ?? '';

            $id = $salutation->getId();
            $this->preselectionDictionary[$key] = $id;
            $choices[] = new PremappingChoiceStruct($id, $key);
            $this->choiceUuids[$id] = $id;
        }

        return $choices;
    }

    /**
     * @param PremappingEntityStruct[] $mapping
     */
    private function setPreselection(array $mapping): void
    {
        foreach ($mapping as $item) {
            if ($item->getDestinationUuid() !== '' || !isset($this->preselectionDictionary[$item->getSourceId()])) {
                continue;
            }

            $item->setDestinationUuid($this->preselectionDictionary[$item->getSourceId()]);
        }
    }
}
