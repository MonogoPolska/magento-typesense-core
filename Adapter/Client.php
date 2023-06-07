<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Adapter;

use Http\Client\Exception;
use Monogo\TypesenseCore\Exceptions\ConnectionException;
use Monogo\TypesenseCore\Services\ConfigService;
use Monogo\TypesenseCore\Services\LogService;
use Typesense\Client as TypeSenseClient;
use Typesense\Exceptions\ConfigError;

class Client
{
    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var LogService
     */
    private LogService $logService;

    /**
     * @var TypeSenseClient|null
     */
    private ?TypeSenseClient $typeSenseClient = null;

    /**
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService,LogService $logService)
    {
        $this->configService = $configService;
        $this->logService = $logService;
    }

    /**
     * @param bool $newInstance
     * @return TypeSenseClient|null
     * @throws ConfigError
     * @throws ConnectionException
     * @throws Exception
     */
    public function getClient(bool $newInstance = false): ?TypeSenseClient
    {
        if ($newInstance) {
            $this->typeSenseClient = null;
        }
        if (is_null($this->typeSenseClient) && $this->configService->isConfigurationValid()) {
            try {
                $client = new TypeSenseClient(
                    [
                        "api_key" => $this->configService->getApiKey(),
                        "nodes" => [
                            [
                                "host" => $this->configService->getNodes(),
                                "port" => $this->configService->getPort(),
                                "protocol" => $this->configService->getProtocol(),
                                "api_key" => $this->configService->getApiKey()
                            ]
                        ]
                    ]
                );

                $this->typeSenseClient = $client;

                $health = $this->typeSenseClient->getHealth()->retrieve();
                if (!isset($health['ok']) || $health['ok'] != 1) {
                    throw new ConnectionException();
                }
            } catch (\Exception $e) {
                $this->logService->log($e->getMessage());
            }
        }
        return $this->typeSenseClient;
    }
}
