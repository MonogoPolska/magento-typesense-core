<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Services;

use Exception;
use Magento\Store\Model\StoreManagerInterface;
use Monogo\TypesenseCore\Logger\Logger;

class LogService
{
    /**
     * @var bool|null
     */
    private bool $enabled;

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var array
     */
    private array $timers = [];

    /**
     * @var array
     */
    private array $stores = [];

    /**
     * @param StoreManagerInterface $storeManager
     * @param ConfigService $configService
     * @param Logger $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigService         $configService,
        Logger                $logger
    )
    {
        $this->configService = $configService;
        $this->enabled = $this->configService->getDebug();
        $this->logger = $logger;

        foreach ($storeManager->getStores() as $store) {
            $this->stores[$store->getId()] = $store->getName();
        }
    }

    /**
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enabled;
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getStoreName(?int $storeId): string
    {
        if ($storeId === null) {
            return 'undefined store';
        }

        return $storeId . ' (' . $this->stores[$storeId] . ')';
    }

    /**
     * @param string $action
     * @return void
     */
    public function start(string $action): void
    {
        if ($this->enabled === false) {
            return;
        }

        $this->log('');
        $this->log('');
        $this->log('>>>>> BEGIN ' . $action);
        $this->timers[$action] = microtime(true);
    }

    /**
     * @param string $message
     * @return void
     */
    public function log(string $message): void
    {
        if ($this->enabled) {
            $this->logger->info($message);
        }
    }

    /**
     * @param string $action
     * @return void
     * @throws Exception
     */
    public function stop(string $action): void
    {
        if ($this->enabled === false) {
            return;
        }

        if (false === isset($this->timers[$action])) {
            throw new \Exception('Typesense Logger => non existing action');
        }

        $this->log('<<<<< END ' . $action . ' (' . $this->formatTime($this->timers[$action], microtime(true)) . ')');
    }

    /**
     * @param string|float $begin
     * @param string|float $end
     * @return string
     */
    private function formatTime(string|float $begin, string|float $end): string
    {
        return ($end - $begin) . 'sec';
    }
}
