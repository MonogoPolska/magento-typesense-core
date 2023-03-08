<?php
declare(strict_types=1);

namespace Model;

use Api\Data\JobInterface;
use Exception;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Traits\AdditionalDataTrait;

/**
 * @api
 *
 * @method int getPid()
 * @method int getStoreId()
 * @method int getDataSize()
 * @method int getRetries()
 * @method int getMaxRetries()
 * @method array getDecodedData()
 * @method array getMergedIds()
 * @method $this setErrorLog(string $message)
 * @method $this setPid($pid)
 * @method $this setRetries($retries)
 * @method $this setStoreId($storeId)
 * @method $this setDataSize($dataSize)
 * @method $this setDecodedData($decodedData)
 * @method $this setMergedIds($mergedIds)
 */
class Job extends AbstractModel implements JobInterface
{
    use AdditionalDataTrait;

    protected $_eventPrefix = 'typesense_queue_job';

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ObjectManagerInterface $objectManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param array $additionalData
     */
    public function __construct(
        Context                $context,
        Registry               $registry,
        ObjectManagerInterface $objectManager,
        AbstractResource       $resource = null,
        AbstractDb             $resourceCollection = null,
        array                  $data = [],
        array                  $additionalData = []
    )
    {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->objectManager = $objectManager;
        $this->additionalData = $additionalData;
    }

    /**
     * @return $this
     * @throws AlreadyExistsException
     *
     */
    public function execute()
    {
        $model = $this->objectManager->get($this->getClass());
        $method = $this->getMethod();
        $data = $this->getDecodedData();

        $this->setRetries((int)$this->getRetries() + 1);

        call_user_func_array([$model, $method], $data);

        $this->getResource()->save($this);

        $this->save();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getClass(): string
    {
        return $this->getData(self::FIELD_CLASS);
    }

    /**
     * @inheritdoc
     */
    public function getMethod(): string
    {
        return $this->getData(self::FIELD_METHOD);
    }

    /**
     * @return $this
     */
    public function prepare(): self
    {
        if ($this->getMergedIds() === null) {
            $this->setMergedIds([$this->getId()]);
        }

        if ($this->getDecodedData() === null) {
            $decodedData = json_decode($this->getData('data'), true);

            $this->setDecodedData($decodedData);

            if (isset($decodedData['store_id'])) {
                $this->setStoreId($decodedData['store_id']);
            }
        }

        return $this;
    }

    /**
     * @param Job $job
     * @param int $maxJobDataSize
     * @return bool
     */
    public function canMerge(Job $job, int $maxJobDataSize): bool
    {

        if ($this->getClass() !== $job->getClass()) {
            return false;
        }

        if ($this->getMethod() !== $job->getMethod()) {
            return false;
        }

        if ($this->getStoreId() !== $job->getStoreId()) {
            return false;
        }
        $decodedData = $this->getDecodedData();
        $candidateDecodedData = $job->getDecodedData();

        $decodedDataStatus = null;
        $candidateDecodedDataStatus = null;

        foreach ($this->getAdditionalData() as $recordType) {
            $recordType = $recordType['name'];

            if (is_null($decodedDataStatus)) {
                $decodedDataStatus = !isset($decodedData[$recordType]) || count($decodedData[$recordType]) <= 0;
            } else {
                $decodedDataStatus = $decodedDataStatus
                    && !isset($decodedData[$recordType])
                    || count($decodedData[$recordType]) <= 0;
            }

            if (is_null($candidateDecodedDataStatus)) {
                $candidateDecodedDataStatus = !isset($candidateDecodedDataStatus[$recordType])
                    || count($candidateDecodedDataStatus[$recordType]) <= 0;
            } else {
                $candidateDecodedDataStatus = $candidateDecodedDataStatus
                    && !isset($candidateDecodedDataStatus[$recordType])
                    || count($candidateDecodedDataStatus[$recordType]) <= 0;
            }
        }

        if ($decodedDataStatus || $candidateDecodedDataStatus) {
            return false;
        }

        foreach ($this->getAdditionalData() as $recordType) {
            if (isset($decodedData[$recordType])
                && count($decodedData[$recordType]) + count($candidateDecodedData[$recordType]) > $maxJobDataSize) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Job $mergedJob
     *
     * @return Job
     */
    public function merge(Job $mergedJob): self
    {
        $mergedIds = $this->getMergedIds();
        $mergedIds[] = $mergedJob->getId();

        $this->setMergedIds($mergedIds);

        $decodedData = $this->getDecodedData();
        $mergedJobDecodedData = $mergedJob->getDecodedData();

        $dataSize = $this->getDataSize();

        if (isset($decodedData['product_ids'])) {
            $decodedData['product_ids'] = array_unique(array_merge(
                $decodedData['product_ids'],
                $mergedJobDecodedData['product_ids']
            ));

            $dataSize = count($decodedData['product_ids']);
        } elseif (isset($decodedData['category_ids'])) {
            $decodedData['category_ids'] = array_unique(array_merge(
                $decodedData['category_ids'],
                $mergedJobDecodedData['category_ids']
            ));

            $dataSize = count($decodedData['category_ids']);
        } elseif (isset($decodedData['page_ids'])) {
            $decodedData['page_ids'] = array_unique(array_merge(
                $decodedData['page_ids'],
                $mergedJobDecodedData['page_ids']
            ));

            $dataSize = count($decodedData['page_ids']);
        }

        $this->setDecodedData($decodedData);
        $this->setDataSize($dataSize);

        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultValues(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        $status = JobInterface::STATUS_PROCESSING;

        if (is_null($this->getPid())) {
            $status = JobInterface::STATUS_NEW;
        }

        if ((int)$this->getRetries() >= $this->getMaxRetries()) {
            $status = JobInterface::STATUS_ERROR;
        }

        return $status;
    }

    /**
     * @param Exception $e
     *
     * @return Job
     * @throws AlreadyExistsException
     *
     */
    public function saveError(Exception $e): self
    {
        $this->setErrorLog($e->getMessage());
        $this->getResource()->save($this);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setClass(string $class): JobInterface
    {
        return $this->setData(self::FIELD_CLASS, $class);
    }

    /**
     * @inheritdoc
     */
    public function setMethod(string $method): JobInterface
    {
        return $this->setData(self::FIELD_METHOD, $method);
    }

    /**
     * @inheritdoc
     */
    public function getBody(): string
    {
        return $this->getData(self::FIELD_DATA);
    }

    /**
     * @inheritdoc
     */
    public function setBody(string $data): JobInterface
    {
        return $this->setData(self::FIELD_DATA, $data);
    }

    /**
     * @inheritdoc
     */
    public function getBodySize(): int
    {
        return $this->getData(self::FIELD_DATA_SIZE);
    }

    /**
     * @inheritdoc
     */
    public function setBodySize(int $size): JobInterface
    {
        return $this->setData(self::FIELD_DATA_SIZE, $size);
    }

    /**
     * Magento Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Model\ResourceModel\Job::class);
    }
}
