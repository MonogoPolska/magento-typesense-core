<?php
declare(strict_types=1);

namespace Adapter;

use Exceptions\ConnectionException;
use Http\Client\Exception;
use Services\ConfigService;
use Typesense\Client as TypeSenseClient;
use Typesense\Exceptions\ConfigError;

class Client
{
    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var TypeSenseClient|null
     */
    private ?TypeSenseClient $typeSenseClient = null;

    /**
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * @param bool $newInstance
     * @return TypeSenseClient
     * @throws ConfigError
     * @throws ConnectionException
     * @throws Exception
     */
    public function getClient(bool $newInstance = false): TypeSenseClient
    {
        if ($newInstance) {
            $this->typeSenseClient = null;
        }
        if (is_null($this->typeSenseClient)) {
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
            try {
                $health = $this->typeSenseClient->getHealth()->retrieve();
                if (!isset($health['ok']) || $health['ok'] != 1) {
                    throw new ConnectionException();
                }
            } catch (\Exception $e) {
                throw new ConnectionException($e->getMessage());
            }
        }
        return $this->typeSenseClient;
    }
}


