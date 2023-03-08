<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Monogo\TypesenseCore\Model\Queue;
use Monogo\TypesenseCore\Services\ConfigService;
use Symfony\Component\Console\Output\ConsoleOutput;
use Zend_Db_Statement_Exception;

class QueueRunner implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'typesense_queue_runner';

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var Queue
     */
    private Queue $queue;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var ConsoleOutput
     */
    private ConsoleOutput $output;

    /**
     * @param ConfigService $configService
     * @param Queue $queue
     * @param ManagerInterface $messageManager
     * @param ConsoleOutput $output
     */
    public function __construct(
        ConfigService    $configService,
        Queue            $queue,
        ManagerInterface $messageManager,
        ConsoleOutput    $output
    )
    {
        $this->configService = $configService;
        $this->queue = $queue;
        $this->messageManager = $messageManager;
        $this->output = $output;
    }

    /**
     * @param $ids
     * @return $this|void
     */
    public function execute($ids)
    {
        return $this;
    }

    /**
     * @return void
     * @throws Zend_Db_Statement_Exception
     */
    public function executeFull()
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
        $this->queue->runCron();
    }

    /**
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids)
    {

    }

    /**
     * @param $id
     * @return void
     */
    public function executeRow($id)
    {

    }
}
