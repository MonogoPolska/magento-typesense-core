<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Adapter;

use DateTime;
use DateTimeZone;
use Http\Client\Exception;
use JsonException;
use Monogo\TypesenseCore\Exceptions\ConnectionException;
use Monogo\TypesenseCore\Services\LogService;
use Monogo\TypesenseCore\Traits\CastTrait;
use Typesense\Client as TypeSenseClient;
use Typesense\Collection;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\TypesenseClientError;

abstract class IndexManager
{
    use CastTrait;

    /**
     * @var TypeSenseClient|null
     */
    protected ?TypeSenseClient $client;

    /**
     * @var LogService
     */
    protected LogService $logService;

    /**
     * @var array
     */
    protected array $indexes = [];

    /**
     * @param Client $client
     * @param LogService $logService
     * @throws ConfigError
     * @throws ConnectionException
     * @throws Exception
     */
    public function __construct(Client $client, LogService $logService)
    {
        $this->client = $client->getClient();
        $this->logService = $logService;
    }

    /**
     * @param array $objects
     * @param string $indexName
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     * @throws JsonException
     */
    public function addObjects(array $objects, string $indexName): void
    {
        try {
            $this->prepareRecords($objects);
            $index = $this->getIndex($indexName);
            $result = $index->getDocuments()->import($objects, ['action' => 'upsert']);
            if (is_array($result)) {
                $this->logResult($result);
            }

        } catch (\Exception $e) {
            $this->logService->log('Error during adding objects: ' . $indexName . ' message: ' . $e->getMessage());

        }
    }

    /**
     * @param array $objects
     * @return void
     * @throws \Exception
     */
    private function prepareRecords(array &$objects): void
    {
        $currentCET = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $currentCET = $currentCET->format('Y-m-d H:i:s');

        foreach ($objects as &$object) {
            $object['typesenseLastUpdateAtCET'] = $currentCET;

            $object = $this->castRecord($object);
        }
    }

    /**
     * @param string $name
     * @return Collection
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function getIndex(string $name): Collection
    {
        if (!isset($this->indexes[$name])) {
            $collections = $this->getCollectionTable();

            if (key_exists($name, $collections)) {
                $index = $this->client->getCollections()->offsetGet($name);
                try {
                    $index->update($this->getIndexSchema($name));
                } catch (\Exception $e) {
                    $this->logService->log('Index update error: ' . $e->getMessage());
                }
                $this->indexes[$name] = $index;
            } else {

                $this->client->getCollections()->create($this->getIndexSchema($name));
                $this->indexes[$name] = $this->client->getCollections()->offsetGet($name);
            }
        }
        return $this->indexes[$name];
    }

    /**
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function getCollectionTable(): array
    {
        $collectionTable = [];
        $collections = $this->getCollections();
        foreach ($collections as $collection) {
            $collectionTable[$collection['name']] = 1;
        }
        return $collectionTable;
    }

    /**
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function getCollections(): array
    {
        return $this->client->getCollections()->retrieve();
    }

    /**
     * @param string $name
     * @return array
     */
    abstract public function getIndexSchema(string $name): array;

    /**
     * @param array $result
     * @return void
     */
    private function logResult(array $result)
    {
        foreach ($result as $item) {
            if (isset($item['error'])) {
                $this->logService->log('Adding objects error: ' . $item['error']);
            }
        }
    }

    /**
     * @param array $objects
     * @param string $indexName
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function deleteObjects(array $objects, string $indexName): void
    {
        $indexName = $this->getIndexNameByAlias($indexName);
        $index = $this->getIndex($indexName);
        foreach ($objects as $object) {
            try {
                $index->documents[$object]->delete();
            } catch (\Exception $e) {
                $this->logService->log('Error during Object removal: index: ' . $indexName . ' object ID: ' . $object . ' Message: ' . $e->getMessage());
            }
        }

    }

    /**
     * @param string $alias
     * @return string
     * @throws Exception
     */
    public function getIndexNameByAlias(string $alias): string
    {
        try {
            $aliases = $this->client->aliases[$alias]->retrieve();
            return $aliases['collection_name'];
        } catch (\Exception $e) {
            return $alias;
        }
    }

    /**
     * @param string $currentIndex
     * @param string $alias
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function addAlias(string $currentIndex, string $alias): void
    {
        $aliases = $this->getAliasesTable();
        try {
            $collectionToRemove = null;
            if (key_exists($alias, $aliases)) {
                $collectionToRemove = $currentIndex != $aliases[$alias] ? $aliases[$alias] : null;
            }

            $this->client->getAliases()->upsert($alias, ['collection_name' => $currentIndex]);

            if ($collectionToRemove) {
                $this->client->collections[$collectionToRemove]->delete();
            }
        } catch (\Exception $e) {
            $this->logService->log('Error during adding alias: index: ' . $currentIndex . ' alias: ' . $alias);
            $this->logService->log('Response from server: ' . $e->getMessage());
        }
    }

    /**
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function getAliasesTable(): array
    {
        $aliasTable = [];
        $aliases = $this->getAliases()['aliases'];
        foreach ($aliases as $alias) {
            $aliasTable[$alias['name']] = $alias['collection_name'];
        }
        return $aliasTable;
    }

    /**
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function getAliases(): array
    {
        return $this->client->getAliases()->retrieve();
    }
}
