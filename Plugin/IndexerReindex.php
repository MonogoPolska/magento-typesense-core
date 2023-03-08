<?php
declare(strict_types=1);

namespace Plugin;

use InvalidArgumentException;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Indexer\Console\Command\IndexerReindexCommand;
use Model\Indexer\All;
use Services\LogService;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexerReindex
{
    /**
     * @var IndexerInterface
     */
    private IndexerInterface $indexer;

    /**
     * @var LogService
     */
    private LogService $logService;

    /**
     * @param IndexerInterface $indexer
     * @param LogService $logService
     */
    public function __construct(IndexerInterface $indexer, LogService $logService)
    {
        $this->indexer = $indexer;
        $this->logService = $logService;
    }

    /**
     * Invalidate all dependent indexes before typesense_all index
     * @param IndexerReindexCommand $subject
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     */
    public function beforeRun(IndexerReindexCommand $subject, InputInterface $input, OutputInterface $output): array
    {
        $checkInput = $input;
        try {
            $command = explode(' ', $checkInput->__toString());
            if (in_array(All::INDEXER_ID, $command)) {
                $this->invalidateIndex();
            }
        } catch (ExceptionInterface $e) {
            $this->logService->log($e->getMessage());
        }

        return [$input, $output];
    }

    /**
     * @return bool
     */
    public function invalidateIndex(): bool
    {
        try {
            $indexer = $this->getIndexerById(All::INDEXER_ID);
            $indexer->invalidate();
            return true;
        } catch (InvalidArgumentException $e) {
            $this->logService->log($e->getMessage());
        }
        return false;
    }

    /**
     * @param string $indexerKey
     * @return IndexerInterface
     */
    private function getIndexerById(string $indexerKey): IndexerInterface
    {
        return $this->indexer->load($indexerKey);
    }

    /**
     * @param string $indexerKey
     * @return bool
     */
    public function invalidateIndexById(string $indexerKey): bool
    {
        if (!$indexerKey) {
            return false;
        }
        try {
            $indexer = $this->getIndexerById($indexerKey);
            $indexer->invalidate();
            return true;
        } catch (InvalidArgumentException $e) {
            $this->logService->log($e->getMessage());
        }
        return false;
    }
}
