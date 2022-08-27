<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Oxid\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class CrossSellingConverter extends OxidConverter
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var string
     */
    protected $runId;

    public function getSourceIdentifier(array $data): string
    {
        return $data['id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->context = $context;
        $this->runId = $migrationContext->getRunUuid();

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $converted = [];
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $data['type'],
            $data['articleID'],
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

        $sourceProductMapping = $this->getProductMapping($data['articleID']);
        if ($sourceProductMapping === null) {
            $this->loggingService->addLogEntry(new AssociationRequiredMissingLog(
                $this->runId,
                DefaultEntities::PRODUCT,
                $data['articleID'],
                $data['type']
            ));

            return new ConvertStruct(null, $data);
        }
        $this->mappingIds[] = $sourceProductMapping['id'];

        $relatedProductMapping = $this->getProductMapping($data['relatedarticle']);
        if ($relatedProductMapping === null) {
            $this->loggingService->addLogEntry(new AssociationRequiredMissingLog(
                $this->runId,
                DefaultEntities::PRODUCT,
                $data['relatedarticle'],
                $data['type']
            ));

            return new ConvertStruct(null, $data);
        }
        $this->mappingIds[] = $relatedProductMapping['id'];

        if ($data['type'] === DefaultEntities::CROSS_SELLING_SIMILAR) {
            $converted['name'] = 'Similar Items';
        } else {
            $converted['name'] = 'Accessory Items';
        }

        $relationMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $data['type'] . '_relation',
            $data['articleID'] . '_' . $data['relatedarticle'],
            $context
        );

        $converted['type'] = 'productList';
        $converted['active'] = true;
        $converted['productId'] = $sourceProductMapping['entityUuid'];
        $converted['assignedProducts'] = [
            [
                'id' => $relationMapping['entityUuid'],
                'position' => $data['position'],
                'productId' => $relatedProductMapping['entityUuid'],
            ],
        ];

        unset(
            $data['type'],
            $data['id'],
            $data['articleID'],
            $data['relatedarticle'],
            $data['position']
        );

        $returnData = $data;
        if (empty($returnData)) {
            $returnData = null;
        }
        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $returnData, $this->mainMapping['id']);
    }

    private function getProductMapping(string $identifier): ?array
    {
        $productMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_MAIN,
            $identifier,
            $this->context
        );

        if ($productMapping === null) {
            $productMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::PRODUCT_CONTAINER,
                $identifier,
                $this->context
            );
        }

        return $productMapping;
    }
}
