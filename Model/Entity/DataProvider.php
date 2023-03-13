<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Entity;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monogo\TypesenseCore\Services\ConfigService;
use Monogo\TypesenseCore\Traits\StripTrait;

class DataProvider
{
    use StripTrait;

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param ConfigService $configService
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ConfigService         $configService,
        StoreManagerInterface $storeManager,
    )
    {
        $this->configService = $configService;
        $this->storeManager = $storeManager;
    }

    /**
     * @return string
     */
    public function getIndexNameSuffix(): string
    {
        return '';
    }

    /**
     * @param int|null $storeId
     * @return array|int[]
     */
    public function getStores(int $storeId = null):array
    {
        if ($storeId !== null) {
            return [$storeId];
        }
        $storeIds = [];

        foreach ($this->storeManager->getStores() as $store) {
            $storeIdCollected = (int)$store->getId();
            if ($this->configService->isEnabled($storeIdCollected) === false) {
                continue;
            }

            if ($store->getData('is_active')) {
                $storeIds[] = $storeIdCollected;
            }
        }

        return $storeIds;
    }

    /**
     * @param int|null $storeId
     * @param array|null $dataIds
     * @return array
     * @throws Exception
     */
    public function getData(?int $storeId, array $dataIds = null): array
    {
        return [];
    }

    /**
     * @param int|null $storeId
     * @return StoreInterface|null
     * @throws NoSuchEntityException
     */
    public function getStore(?int $storeId): ?StoreInterface
    {
        return $this->storeManager->getStore($storeId);
    }
}
