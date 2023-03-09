<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Monogo\TypesenseCore\Model\Entity\DataProvider;
use Monogo\TypesenseCore\Model\Indexer\Indexer as IndexerRunner;
use Monogo\TypesenseCore\Model\Queue;
use Monogo\TypesenseCore\Services\ConfigService;
use Symfony\Component\Console\Output\ConsoleOutput;

abstract class DataIndexer implements IndexerActionInterface, MviewActionInterface
{
    /**
     * @var Queue
     */
    private Queue $queue;
    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var DataProvider
     */
    private DataProvider $dataProvider;

    /**
     * @var IndexerRunner
     */
    private IndexerRunner $indexerRunner;

    /**
     * @var ConsoleOutput
     */
    private ConsoleOutput $output;

    /**
     * @param DataProvider $dataProvider
     * @param IndexerRunner $indexerRunner
     * @param Queue $queue
     * @param ConfigService $configService
     * @param ManagerInterface $messageManager
     * @param ConsoleOutput $output
     */
    public function __construct(
        DataProvider     $dataProvider,
        IndexerRunner    $indexerRunner,
        Queue            $queue,
        ConfigService    $configService,
        ManagerInterface $messageManager,
        ConsoleOutput    $output
    )
    {
        $this->dataProvider = $dataProvider;
        $this->queue = $queue;
        $this->configService = $configService;
        $this->messageManager = $messageManager;
        $this->indexerRunner = $indexerRunner;
        $this->output = $output;
    }

    /**
     * @return void
     */
    public function executeFull()
    {
        $this->execute([]);
    }

    /**
     * @param array $ids
     * @return void
     */
    public function execute($ids)
    {
        if (!$this->configService->isConfigurationValid()) {
            $errorMessage = 'Typesense reindexing failed:
                You need to configure your Typesense credentials in Stores > Configuration > Typesense -> General';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }
            $this->messageManager->addErrorMessage($errorMessage);
            return;
        }
        $storeIds = $this->dataProvider->getStores();

        foreach ($storeIds as $storeId) {
            if ($this->configService->isEnabled($storeId) === false) {
                continue;
            }
            $data = ['storeId' => $storeId];
            if (is_array($ids) && count($ids) > 0) {
                $data['dataIds'] = $ids;
            }
            $this->queue->addToQueue(
                $this->indexerRunner,
                'rebuildIndex',
                $data,
                count($ids),
                is_array($ids) && count($ids)
            );
        }
    }

    /**
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @param int $id
     * @return void
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }
}
