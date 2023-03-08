<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use Monogo\TypesenseCore\Model\ResourceModel\Job\Collection;
use Monogo\TypesenseCore\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Monogo\TypesenseCore\Services\ConfigService;
use Monogo\TypesenseCore\Services\LogService;
use PDO;
use Symfony\Component\Console\Output\ConsoleOutput;
use Zend_Db_Expr;
use Zend_Db_Statement_Exception;

class Queue
{
    const DATE_FORMAT = 'Y-m-d H:i:s';
    const FULL_REINDEX_TO_REALTIME_JOBS_RATIO = 0.33;
    const UNLOCK_STACKED_JOBS_AFTER_MINUTES = 15;
    const CLEAR_ARCHIVE_LOGS_AFTER_DAYS = 30;

    const SUCCESS_LOG = 'typesense_queue_log.txt';
    const ERROR_LOG = 'typesense_queue_errors.log';

    /** @var AdapterInterface */
    protected AdapterInterface $db;

    /** @var string */
    protected string $table;

    /** @var string */
    protected string $logTable;

    /** @var string */
    protected string $archiveTable;

    /** @var ObjectManagerInterface */
    protected ObjectManagerInterface $objectManager;

    /** @var ConsoleOutput */
    protected ConsoleOutput $output;

    /** @var int */
    protected int $elementsPerPage;

    /** @var ConfigService */
    protected ConfigService $configService;

    /** @var LogService */
    protected LogService $logger;

    /**
     * @var JobCollectionFactory
     */
    protected JobCollectionFactory $jobCollectionFactory;

    /** @var int */
    protected int $maxSingleJobDataSize;

    /** @var int */
    protected int $noOfFailedJobs = 0;

    /** @var array */
    protected array $staticJobMethods = [
        'saveConfigurationToTypesense',
        'moveIndexWithSetSettings',
        'deleteObjects',
    ];

    /** @var array */
    protected array $logRecord;

    /**
     * @param ConfigService $configService
     * @param LogService $logger
     * @param JobCollectionFactory $jobCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param ObjectManagerInterface $objectManager
     * @param ConsoleOutput $output
     */
    public function __construct(
        ConfigService          $configService,
        LogService             $logger,
        JobCollectionFactory   $jobCollectionFactory,
        ResourceConnection     $resourceConnection,
        ObjectManagerInterface $objectManager,
        ConsoleOutput          $output
    )
    {
        $this->configService = $configService;
        $this->logger = $logger;
        $this->jobCollectionFactory = $jobCollectionFactory;
        $this->table = $resourceConnection->getTableName('typesense_queue');
        $this->logTable = $resourceConnection->getTableName('typesense_queue_log');
        $this->archiveTable = $resourceConnection->getTableName('typesense_queue_archive');
        $this->objectManager = $objectManager;
        $this->db = $objectManager->create(ResourceConnection::class)->getConnection('core_write');
        $this->output = $output;
        $this->elementsPerPage = $this->configService->getNumberOfElementByPage();
        $this->maxSingleJobDataSize = $this->configService->getNumberOfElementByPage();
    }

    /**
     * @param string|object $className
     * @param string $method
     * @param array $data
     * @param int $dataSize
     * @param bool $isFullReindex
     * @return void
     */
    public function addToQueue(
        string|object $className,
        string        $method,
        array         $data,
        int           $dataSize = 1,
        bool          $isFullReindex = false
    ): void
    {
        if (is_object($className)) {
            $className = get_class($className);
        }

        if ($this->configService->isQueueActive()) {
            $this->db->insert($this->table, [
                'created' => date(self::DATE_FORMAT),
                'class' => $className,
                'method' => $method,
                'data' => json_encode($data),
                'data_size' => $dataSize,
                'pid' => null,
                'max_retries' => $this->configService->getRetryLimit(),
                'is_full_reindex' => $isFullReindex ? 1 : 0,
            ]);
        } else {
            $object = $this->objectManager->get($className);
            call_user_func_array([$object, $method], $data);
        }
    }

    /**
     * Return the average processing time for the 2 last two days
     * (null if there was less than 100 runs with processed jobs)
     *
     * @return float|null
     * @throws Zend_Db_Statement_Exception
     *
     */
    public function getAverageProcessingTime(): ?float
    {
        $select = $this->db->select()
            ->from($this->logTable, ['number_of_runs' => 'COUNT(duration)', 'average_time' => 'AVG(duration)'])
            ->where('processed_jobs > 0 AND with_empty_queue = 0 AND started >= (CURDATE() - INTERVAL 2 DAY)');

        $data = $this->db->query($select)->fetch();

        return (int)$data['number_of_runs'] >= 100 && isset($data['average_time']) ?
            (float)$data['average_time'] :
            null;
    }

    /**
     * @param int|null $nbJobs
     * @param bool $force
     * @return void
     * @throws Zend_Db_Statement_Exception
     */
    public function runCron(?int $nbJobs = null, bool $force = false): void
    {
        if (!$this->configService->isQueueActive() && $force === false) {
            return;
        }

        $this->clearOldLogRecords();
        $this->clearOldArchiveRecords();
        $this->unlockStackedJobs();

        $this->logRecord = [
            'started' => date(self::DATE_FORMAT),
            'processed_jobs' => 0,
            'with_empty_queue' => 0,
        ];

        $started = time();

        if ($nbJobs === null) {
            $nbJobs = $this->configService->getNumberOfJobToRun();
            if ($this->shouldEmptyQueue() === true) {
                $nbJobs = -1;

                $this->logRecord['with_empty_queue'] = 1;
            }
        }
        $this->run($nbJobs);
        $this->logRecord['duration'] = time() - $started;
        if (php_sapi_name() === 'cli') {
            $this->output->writeln(
                $this->logRecord['processed_jobs'] . ' jobs processed in ' . $this->logRecord['duration'] . ' seconds.'
            );
        }
        $this->db->insert($this->logTable, $this->logRecord);
    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    protected function clearOldLogRecords(): void
    {
        $select = $this->db->select()
            ->from($this->logTable, ['id'])
            ->order(['started DESC', 'id DESC'])
            ->limit(PHP_INT_MAX, 25000);

        $idsToDelete = $this->db->query($select)->fetchAll(PDO::FETCH_COLUMN, 0);

        if ($idsToDelete) {
            $this->db->delete($this->logTable, ['id IN (?)' => $idsToDelete]);
        }
    }

    /**
     * @return void
     */
    protected function clearOldArchiveRecords(): void
    {
        $archiveLogClearLimit = $this->configService->getArchiveLogClearLimit();
        if ($archiveLogClearLimit < 1) {
            $archiveLogClearLimit = self::CLEAR_ARCHIVE_LOGS_AFTER_DAYS;
        }

        $this->db->delete(
            $this->archiveTable,
            'created_at < (NOW() - INTERVAL ' . $archiveLogClearLimit . ' DAY)'
        );
    }

    /**
     * @return void
     */
    protected function unlockStackedJobs(): void
    {
        $this->db->update($this->table, [
            'locked_at' => null,
            'pid' => null,
        ], ['locked_at < (NOW() - INTERVAL ' . self::UNLOCK_STACKED_JOBS_AFTER_MINUTES . ' MINUTE)']);
    }

    /**
     * @return bool
     */
    protected function shouldEmptyQueue(): bool
    {
        if (getenv('PROCESS_FULL_QUEUE') && getenv('PROCESS_FULL_QUEUE') === '1') {
            return true;
        }

        if (getenv('EMPTY_QUEUE') && getenv('EMPTY_QUEUE') === '1') {
            return true;
        }

        return false;
    }

    /**
     * @param int $maxJobs
     *
     * @throws Exception
     */
    public function run(int $maxJobs): void
    {
        $this->clearOldFailingJobs();
        $jobs = $this->getJobs($maxJobs);

        if ($jobs === []) {
            return;
        }

        foreach ($jobs as $job) {
            if ($job->getMethod() === 'moveIndex' && $this->noOfFailedJobs > 0) {
                $this->db->update($this->table, ['pid' => null], ['job_id = ?' => $job->getId()]);
                continue;
            }

            try {
                $job->execute();
                $this->db->delete($this->table, ['job_id IN (?)' => $job->getMergedIds()]);

                $this->logRecord['processed_jobs'] += count($job->getMergedIds());
            } catch (Exception $e) {
                $this->noOfFailedJobs++;
                $logMessage = 'Queue processing ' . $job->getPid() . ' [KO]:
                    Class: ' . $job->getClass() . ',
                    Method: ' . $job->getMethod() . ',
                    Parameters: ' . json_encode($job->getDecodedData());
                $this->logger->log($logMessage);

                $logMessage = date('c') . ' ERROR: ' . get_class($e) . ':
                    ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() .
                    "\nStack trace:\n" . $e->getTraceAsString();
                $this->logger->log($logMessage);

                $this->db->update($this->table, [
                    'pid' => null,
                    'retries' => new Zend_Db_Expr('retries + 1'),
                    'error_log' => $logMessage,
                ], ['job_id IN (?)' => $job->getMergedIds()]);

                if (php_sapi_name() === 'cli') {
                    $this->output->writeln($logMessage);
                }
            }
        }

        $isFullReindex = ($maxJobs === -1);
        if ($isFullReindex) {
            $this->run(-1);

            return;
        }
    }

    /**
     * @return void
     */
    protected function clearOldFailingJobs(): void
    {
        $this->archiveFailedJobs('retries > max_retries');
        $this->db->delete($this->table, 'retries > max_retries');
    }

    /**
     * @param string $whereClause
     * @return void
     */
    protected function archiveFailedJobs(string $whereClause): void
    {
        $select = $this->db->select()
            ->from($this->table, ['pid', 'class', 'method', 'data', 'error_log', 'data_size', 'NOW()'])
            ->where($whereClause);

        $query = $this->db->insertFromSelect(
            $select,
            $this->archiveTable,
            ['pid', 'class', 'method', 'data', 'error_log', 'data_size', 'created_at']
        );

        $this->db->query($query);
    }

    /**
     * @param int $maxJobs
     * @return array
     * @throws Exception
     */
    protected function getJobs(int $maxJobs): array
    {
        $maxJobs = ($maxJobs === -1) ? $this->configService->getNumberOfJobToRun() : $maxJobs;

        $fullReindexJobsLimit = (int)ceil(self::FULL_REINDEX_TO_REALTIME_JOBS_RATIO * $maxJobs);

        try {
            $this->db->beginTransaction();

            $fullReindexJobs = $this->fetchJobs($fullReindexJobsLimit, true);
            $fullReindexJobsCount = count($fullReindexJobs);

            $realtimeJobsLimit = (int)$maxJobs - $fullReindexJobsCount;

            $realtimeJobs = $this->fetchJobs($realtimeJobsLimit);

            $jobs = array_merge($fullReindexJobs, $realtimeJobs);
            $jobsCount = count($jobs);

            if ($jobsCount > 0 && $jobsCount < $maxJobs) {
                $restLimit = (int)$maxJobs - $jobsCount;
                $lastFullReindexJobId = (int)max($this->getJobsIdsFromMergedJobs($jobs));

                $restFullReindexJobs = $this->fetchJobs($restLimit, true, $lastFullReindexJobId);

                $jobs = array_merge($jobs, $restFullReindexJobs);
            }

            $this->lockJobs($jobs);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();

            throw $e;
        }

        return $jobs;
    }

    /**
     * @param int $jobsLimit
     * @param bool $fetchFullReindexJobs
     * @param int|null $lastJobId
     * @return array|Job[]
     */
    protected function fetchJobs(int $jobsLimit, bool $fetchFullReindexJobs = false, ?int $lastJobId = null): array
    {
        $jobs = [];

        $actualBatchSize = 0;
        $maxBatchSize = $this->configService->getNumberOfElementByPage() * $jobsLimit;

        $limit = $maxJobs = $jobsLimit;
        $offset = 0;

        $fetchFullReindexJobs = $fetchFullReindexJobs ? 1 : 0;

        while ($actualBatchSize < $maxBatchSize) {
            $jobsCollection = $this->jobCollectionFactory->create();
            $jobsCollection
                ->addFieldToFilter('pid', ['null' => true])
                ->addFieldToFilter('is_full_reindex', $fetchFullReindexJobs)
                ->setOrder('job_id', Collection::SORT_ORDER_ASC)
                ->getSelect()
                ->limit($limit, $offset)
                ->forUpdate();

            if ($lastJobId !== null) {
                $jobsCollection->addFieldToFilter('job_id', ['gt' => $lastJobId]);
            }

            $rawJobs = $jobsCollection->getItems();

            if ($rawJobs === []) {
                break;
            }

            $rawJobs = array_merge($jobs, $rawJobs);
            $rawJobs = $this->mergeJobs($rawJobs);

            $rawJobsCount = count($rawJobs);

            $offset += $limit;
            $limit = max(0, $maxJobs - $rawJobsCount);

            $jobs = [];

            if (count($rawJobs) === $maxJobs) {
                $jobs = $rawJobs;

                break;
            }

            foreach ($rawJobs as $job) {
                $jobSize = (int)$job->getDataSize();

                if ($actualBatchSize + $jobSize <= $maxBatchSize || !$jobs) {
                    $jobs[] = $job;
                    $actualBatchSize += $jobSize;
                } else {
                    break 2;
                }
            }
        }

        return $jobs;
    }

    /**
     * @param array $unmergedJobs
     * @return array
     */
    protected function mergeJobs(array $unmergedJobs): array
    {
        $unmergedJobs = $this->sortJobs($unmergedJobs);
        $jobs = [];

        /** @var Job $currentJob */
        $currentJob = array_shift($unmergedJobs);

        while ($currentJob !== null) {
            if (!empty($unmergedJobs)) {
                $nextJob = array_shift($unmergedJobs);

                if ($currentJob->canMerge($nextJob, $this->maxSingleJobDataSize)) {
                    $currentJob->merge($nextJob);

                    continue;
                }
            } else {
                $nextJob = null;
            }

            $jobs[] = $currentJob;
            $currentJob = $nextJob;
        }

        return $jobs;
    }

    /**
     * @param array $jobs
     * @return array
     */
    protected function sortJobs(array $jobs): array
    {
        $sortedJobs = [];
        $tempSortableJobs = [];

        /** @var Job $job */
        foreach ($jobs as $job) {
            $job->prepare();

            if (in_array($job->getMethod(), $this->staticJobMethods, true)) {
                $sortedJobs = $this->stackSortedJobs($sortedJobs, $tempSortableJobs, $job);
                $tempSortableJobs = [];
                continue;
            }
            $tempSortableJobs[] = $job;
        }
        return $this->stackSortedJobs($sortedJobs, $tempSortableJobs);
    }

    /**
     * @param array $sortedJobs
     * @param array $tempSortableJobs
     * @param Job|null $job
     * @return array
     */
    protected function stackSortedJobs(array $sortedJobs, array $tempSortableJobs, ?Job $job = null): array
    {
        if ($tempSortableJobs && $tempSortableJobs !== []) {
            $tempSortableJobs = $this->jobSort(
                $tempSortableJobs,
                'class',
                SORT_ASC,
                'method',
                SORT_ASC,
                'store_id',
                SORT_ASC,
                'job_id',
                SORT_ASC
            );
        }

        $sortedJobs = array_merge($sortedJobs, $tempSortableJobs);

        if ($job !== null) {
            $sortedJobs = array_merge($sortedJobs, [$job]);
        }
        return $sortedJobs;
    }

    /**
     * @return array
     */
    protected function jobSort(): array
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = [];

                /**
                 * @var int $key
                 * @var Job $row
                 */
                foreach ($data as $key => $row) {
                    $tmp[$key] = $row->getData($field);
                }
                $args[$n] = $tmp;
            }
        }

        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }

    /**
     * @param array $mergedJobs
     * @return array
     */
    protected function getJobsIdsFromMergedJobs(array $mergedJobs): array
    {
        $jobsIds = [];
        foreach ($mergedJobs as $job) {
            $jobsIds = array_merge($jobsIds, $job->getMergedIds());
        }

        return $jobsIds;
    }

    /**
     * @param array $jobs
     * @return void
     */
    protected function lockJobs(array $jobs): void
    {
        $jobsIds = $this->getJobsIdsFromMergedJobs($jobs);

        if ($jobsIds !== []) {
            $pid = getmypid();
            $this->db->update($this->table, [
                'locked_at' => date(self::DATE_FORMAT),
                'pid' => $pid,
            ], ['job_id IN (?)' => $jobsIds]);
        }
    }
}
