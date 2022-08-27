<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Premapping;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Oxid\DataSelection\CustomerAndOrderDataSelection;
use SwagMigrationAssistant\Profile\Oxid\Gateway\ShopwareGatewayInterface;
use SwagMigrationAssistant\Profile\Oxid\OxidProfileInterface;

class TransactionStateReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'transaction_state';

    /**
     * @var EntityRepositoryInterface
     */
    protected $stateMachineRepo;

    /**
     * @var EntityRepositoryInterface
     */
    protected $stateMachineStateRepo;

    /**
     * @var string[]
     */
    protected $preselectionDictionary = [];

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var string[]
     */
    private $choiceUuids;

    public function __construct(
        EntityRepositoryInterface $stateMachineRepo,
        EntityRepositoryInterface $stateMachineStateRepo,
        GatewayRegistryInterface $gatewayRegistry
    ) {
        $this->stateMachineRepo = $stateMachineRepo;
        $this->stateMachineStateRepo = $stateMachineStateRepo;
        $this->gatewayRegistry = $gatewayRegistry;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof OxidProfileInterface
            && \in_array(CustomerAndOrderDataSelection::IDENTIFIER, $entityGroupNames, true);
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

        $preMappingData = $gateway->readTable($migrationContext, 's_core_states');

        $entityData = [];
        foreach ($preMappingData as $data) {
            if ($data['group'] === 'payment') {
                $uuid = '';
                if (isset($this->connectionPremappingDictionary[$data['id']])) {
                    $uuid = $this->connectionPremappingDictionary[$data['id']]['destinationUuid'];

                    if (!isset($this->choiceUuids[$uuid])) {
                        $uuid = '';
                    }
                }
                if (!empty($data['description'])) {
                    $description = $data['description'];
                } elseif (!empty($data['name'])) {
                    $description = $data['name'];
                } else {
                    $description = $data['id'];
                }

                $entityData[] = new PremappingEntityStruct($data['id'], $description, $uuid);
            }
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
        $criteria->addFilter(new EqualsFilter('technicalName', OrderTransactionStates::STATE_MACHINE));

        /** @var StateMachineEntity $stateMachine */
        $stateMachine = $this->stateMachineRepo->search($criteria, $context)->first();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachine->getId()));
        $criteria->addSorting(new FieldSorting('name'));
        $states = $this->stateMachineStateRepo->search($criteria, $context);

        $choices = [];
        /** @var StateMachineStateEntity $state */
        foreach ($states as $state) {
            $id = $state->getId();
            $this->preselectionDictionary[$state->getTechnicalName()] = $id;
            $choices[] = new PremappingChoiceStruct($id, $state->getName());
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
            if ($item->getDestinationUuid() !== '') {
                continue;
            }

            $preselectionValue = $this->getPreselectionValue($item->getSourceId());

            if ($preselectionValue !== null) {
                $item->setDestinationUuid($preselectionValue);
            }
        }
    }

    private function getPreselectionValue(string $sourceId): ?string
    {
        $preselectionValue = null;
        switch ($sourceId) {
            case 9: // partially_invoiced
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 10: // completely_invoiced
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 11: // partially_paid
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_PARTIALLY_PAID] ?? null;

                break;
            case 12: // completely_paid
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_PAID] ?? null;

                break;
            case 13: // 1st_reminder
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_REMINDED] ?? null;

                break;
            case 14: // 2nd_reminder
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_REMINDED] ?? null;

                break;
            case 15: // 3rd_reminder
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_REMINDED] ?? null;

                break;
            case 16: // encashment
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_REFUNDED] ?? null;

                break;
            case 17: // open
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 18: // reserved
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 19: // delayed
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 20: // re_crediting
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_REFUNDED] ?? null;

                break;
            case 21: // review_necessary
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 30: // no_credit_approved
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 31: // the_credit_has_been_preliminarily_accepted
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 32: // the_credit_has_been_accepted
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 33: // the_payment_has_been_ordered
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 34: // a_time_extension_has_been_registered
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_OPEN] ?? null;

                break;
            case 35: // the_process_has_been_cancelled
            case 0: // Cancelled order without payment state
                $preselectionValue = $this->preselectionDictionary[OrderTransactionStates::STATE_CANCELLED] ?? null;

                break;
        }

        return $preselectionValue;
    }
}
