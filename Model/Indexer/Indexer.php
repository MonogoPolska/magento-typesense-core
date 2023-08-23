<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Indexer;

use Http\Client\Exception;
use JsonException;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Monogo\TypesenseCore\Adapter\IndexManager;
use Monogo\TypesenseCore\Model\Entity\DataProvider as DataProviderCore;
use Monogo\TypesenseCore\Services\ConfigService;
use Monogo\TypesenseCore\Services\LogService;
use Typesense\Exceptions\TypesenseClientError;

abstract class Indexer
{
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var Emulation
     */
    protected Emulation $emulation;

    /**
     * @var LogService
     */
    protected LogService $logger;

    /**
     * @var ScopeCodeResolver
     */
    protected ScopeCodeResolver $scopeCodeResolver;
    /**
     * @var ConfigService
     */
    protected ConfigService $configService;
    /**
     * @var IndexManager
     */
    protected IndexManager $indexManager;
    /**
     * @var DataProviderCore
     */
    protected DataProviderCore $dataProvider;
    /**
     * @var bool
     */
    protected bool $emulationRuns = false;
    /**
     * @var ManagerInterface
     */
    private ManagerInterface $eventManager;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     * @param LogService $logger
     * @param ScopeCodeResolver $scopeCodeResolver
     * @param ManagerInterface $eventManager
     * @param ConfigService $configService
     * @param IndexManager $indexManager
     * @param DataProviderCore $dataProvider
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Emulation             $emulation,
        LogService            $logger,
        ScopeCodeResolver     $scopeCodeResolver,
        ManagerInterface      $eventManager,
        ConfigService         $configService,
        IndexManager          $indexManager,
        DataProviderCore      $dataProvider
    )
    {
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
        $this->logger = $logger;
        $this->scopeCodeResolver = $scopeCodeResolver;
        $this->eventManager = $eventManager;
        $this->configService = $configService;
        $this->indexManager = $indexManager;
        $this->dataProvider = $dataProvider;
    }

    /**
     * @param int|null $storeId
     * @param array|null $dataIds
     * @return void
     * @throws Exception
     * @throws NoSuchEntityException
     * @throws TypesenseClientError
     * @throws JsonException
     */
    public function rebuildIndex(?int $storeId, array $dataIds = null): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->processIndex($storeId, $dataIds);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isIndexingEnabled(?int $storeId = null): bool
    {
        if ($this->configService->isEnabled($storeId) === false) {
            $this->logger->log('INDEXING IS DISABLED FOR ' . $this->logger->getStoreName($storeId));
            return false;
        }
        return true;
    }

    /**
     * @param int|null $storeId
     * @param array|null $dataIds
     * @return void
     * @throws Exception
     * @throws JsonException
     * @throws NoSuchEntityException
     * @throws TypesenseClientError
     */
    public function processIndex(?int $storeId, array $dataIds = null): void
    {
        $aliasName = $this->getBaseIndexName($storeId) . $this->dataProvider->getIndexNameSuffix();
        $toIndexName = $this->getIndexName($this->dataProvider->getIndexNameSuffix(), $storeId, is_null($dataIds));

        $data = $this->getEntityData($storeId, $dataIds);

        $this->eventManager->dispatch(
            'typesense_core_before_process_index',
            ['data' => $data]
        );

        $isFullReindex = (!$dataIds);

        if (isset($data['toIndex']) && count($data['toIndex'])) {
            $dataToIndex = $data['toIndex'];

            foreach (array_chunk($dataToIndex, 100) as $chunk) {
                try {
                    $this->indexManager->addObjects($chunk, $toIndexName);
                } catch (Exception $e) {
                    $this->logger->log($e->getMessage());
                    continue;
                }
            }

        }

        if (!$isFullReindex && isset($data['toRemove']) && count($data['toRemove'])) {
            $dataToRemove = $data['toRemove'];
            foreach (array_chunk($dataToRemove, 100) as $chunk) {
                try {
                    $this->indexManager->deleteObjects($chunk, $aliasName);
                } catch (Exception $e) {
                    $this->logger->log($e->getMessage());
                    continue;
                }
            }
        }

        if (is_null($dataIds)) {
            $this->indexManager->addAlias($toIndexName, $aliasName);
            $this->eventManager->dispatch(
                'typesense_core_after_add_alias',
                ['store_id'=> $storeId, 'alias' => $aliasName, 'collection' => $toIndexName]
            );
        }

        $this->eventManager->dispatch(
            'typesense_core_after_process_index',
            ['data' => $data]
        );
    }

    /**
     * @param int|null $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getBaseIndexName(?int $storeId = null): string
    {
        return $this->configService->getIndexPrefix($storeId) . $this->storeManager->getStore($storeId)->getCode();
    }

    /**
     * @param string $indexSuffix
     * @param int|null $storeId
     * @param bool $tmp
     * @return string
     * @throws Exception
     * @throws NoSuchEntityException
     */
    public function getIndexName(string $indexSuffix, ?int $storeId = null, bool $tmp = false): string
    {
        if ($tmp) {
            $indexName = $this->getBaseIndexName($storeId) . $indexSuffix . '_' . time();
        } else {
            $indexName = $this->indexManager->getIndexNameByAlias($this->getBaseIndexName($storeId) . $indexSuffix);
        }
        $this->indexManager->getIndex($indexName);
        return $indexName;
    }

    /**
     * @param int|null $storeId
     * @param array|null $dataIds
     * @return array
     * @throws \Exception
     */
    public function getEntityData(?int $storeId, array $dataIds = null): array
    {
        $this->startEmulation($storeId);
        $data = $this->dataProvider->getData($storeId, $dataIds);
        $this->stopEmulation();
        return $data;
    }

    /**
     * @param $storeId
     * @return void
     * @throws \Exception
     */
    public function startEmulation($storeId): void
    {
        if ($this->emulationRuns === true) {
            return;
        }

        $this->logger->start('START EMULATION');
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $this->scopeCodeResolver->clean();
        $this->emulationRuns = true;
        $this->logger->stop('START EMULATION');
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function stopEmulation(): void
    {
        $this->logger->start('STOP EMULATION');
        $this->emulation->stopEnvironmentEmulation();
        $this->emulationRuns = false;
        $this->logger->stop('STOP EMULATION');
    }
}
