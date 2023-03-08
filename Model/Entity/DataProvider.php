<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Entity;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monogo\TypesenseCore\Traits\StripTrait;

class DataProvider
{
    use StripTrait;

    /**
     * @var mixed
     */
    private mixed $configService;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param mixed $configService
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        mixed                 $configService,
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
    public function getStores(int $storeId = null)
    {
        if ($storeId !== null) {
            return [$storeId];
        }
        $storeIds = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ($this->configService->isEnabled($store->getId()) === false) {
                continue;
            }

            if ($store->getData('is_active')) {
                $storeIds[] = $store->getId();
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
