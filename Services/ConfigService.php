<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Services;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as ScopeConfig;

class ConfigService
{
    /**
     * Config paths
     */
    const TYPESENSE_ENABLED = 'typesense_general/settings/enabled';
    const TYPESENSE_CLOUD_ID = 'typesense_general/settings/cloud_id';
    const TYPESENSE_API_KEY = 'typesense_general/settings/admin_api_key';
    const TYPESENSE_SEARCH_ONLY_KEY_KEY = 'typesense_general/settings/search_only_key';
    const TYPESENSE_NODES = 'typesense_general/settings/nodes';
    const TYPESENSE_PORT = 'typesense_general/settings/port';
    const TYPESENSE_PROTOCOL = 'typesense_general/settings/protocol';
    const TYPESENSE_INDEX_PREFIX = 'typesense_general/settings/index_prefix';
    const TYPESENSE_DEBUG = 'typesense_general/settings/debug';

    const TYPESENSE_NUMBER_OF_ELEMENT_BY_PAGE = 'typesense_advanced/advanced/number_of_element_by_page';
    const TYPESENSE_ARCHIVE_LOG_CLEAR_LIMIT = 'typesense_advanced/advanced/archive_clear_limit';
    const TYPESENSE_AUTOCOMPLETE_SECTIONS = 'typesense_advanced/advanced/sections';

    const TYPESENSE_IS_QUEUE_ACTIVE = 'typesense_queue/queue/active';
    const TYPESENSE_NUMBER_OF_JOB_TO_RUN = 'typesense_queue/queue/number_of_job_to_run';
    const TYPESENSE_RETRY_LIMIT = 'typesense_queue/queue/number_of_retries';

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @var Manager
     */
    protected Manager $moduleManager;

    /**
     * @var SerializerInterface
     */
    protected SerializerInterface $serializer;

    /**
     * @var string
     */
    protected string $idColumn;

    /**
     * @param ProductMetadataInterface $productMetadata
     * @param EncryptorInterface $encryptor
     * @param ScopeConfigInterface $scopeConfig
     * @param Manager $moduleManager
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        EncryptorInterface       $encryptor,
        ScopeConfigInterface     $scopeConfig,
        Manager                  $moduleManager,
        SerializerInterface      $serializer
    )
    {
        $this->productMetadata = $productMetadata;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->moduleManager = $moduleManager;
        $this->serializer = $serializer;
    }

    /**
     * @return string
     */
    public function getCorrectIdColumn(): string
    {
        if (isset($this->idColumn)) {
            return $this->idColumn;
        }

        $this->idColumn = 'entity_id';

        $edition = $this->getMagentoEdition();
        $version = $this->getMagentoVersion();
        if (
            $edition !== 'Community'
            && version_compare($version, '2.1.0', '>=')
            && $this->moduleManager->isEnabled('Magento_Staging')
        ) {
            $this->idColumn = 'row_id';
        }
        return $this->idColumn;
    }

    /**
     * @return string
     */
    public function getMagentoEdition()
    {
        return $this->productMetadata->getEdition();
    }

    /**
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * @param ?int $storeId
     * @return bool|null
     */
    public function isEnabled(?int $storeId = null): ?bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::TYPESENSE_ENABLED,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return string|null
     */
    public function getCloudId(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::TYPESENSE_CLOUD_ID,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return string|null
     */
    public function getSearchOnlyKey(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::TYPESENSE_SEARCH_ONLY_KEY_KEY,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
        return $this->encryptor->decrypt($value);
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getIndexPrefix(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::TYPESENSE_INDEX_PREFIX,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
        return !empty($value) ? $value .= '_' : $value;
    }

    /**
     * @param ?int $storeId
     * @return bool|null
     */
    public function getDebug(?int $storeId = null): ?bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::TYPESENSE_DEBUG,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return int
     */
    public function getNumberOfElementByPage(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::TYPESENSE_NUMBER_OF_ELEMENT_BY_PAGE,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return bool
     */
    public function isQueueActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::TYPESENSE_IS_QUEUE_ACTIVE,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return int
     */
    public function getRetryLimit(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::TYPESENSE_RETRY_LIMIT,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return int
     */
    public function getNumberOfJobToRun(?int $storeId = null): int
    {
        $nbJobs = (int)$this->scopeConfig->getValue(
            self::TYPESENSE_NUMBER_OF_JOB_TO_RUN,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );

        return max($nbJobs, 1);
    }

    /**
     * @param ?int $storeId
     * @return int
     */
    public function getArchiveLogClearLimit(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::TYPESENSE_ARCHIVE_LOG_CLEAR_LIMIT,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getAutocompleteSections(int $storeId = null): array
    {
        $attrs = $this->unserialize($this->scopeConfig->getValue(
            self::TYPESENSE_AUTOCOMPLETE_SECTIONS,
            ScopeConfig::SCOPE_STORE,
            $storeId
        ));

        if (is_array($attrs)) {
            return array_values($attrs);
        }
        return [];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function unserialize(mixed $value): mixed
    {
        if (false === $value || null === $value || '' === $value) {
            return false;
        }
        $unserialized = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $unserialized;
        }
        return $this->serializer->unserialize($value);
    }

    /**
     * @return bool
     */
    public function isConfigurationValid(): bool
    {
        if (!$this->getPort()
            || !$this->getApiKey()
            || !$this->getNodes()
            || !$this->getProtocol()) {
            return false;
        }
        return true;
    }

    /**
     * @param ?int $storeId
     * @return string|null
     */
    public function getPort(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::TYPESENSE_PORT,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return string|null
     */
    public function getApiKey(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::TYPESENSE_API_KEY,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
        return $this->encryptor->decrypt($value);
    }

    /**
     * @param ?int $storeId
     * @return string|null
     */
    public function getNodes(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::TYPESENSE_NODES,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param ?int $storeId
     * @return string|null
     */
    public function getProtocol(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::TYPESENSE_PROTOCOL,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    public function getFacets(): array
    {
        return [];
    }
}
