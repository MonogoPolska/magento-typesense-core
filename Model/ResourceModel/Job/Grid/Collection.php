<?php
declare(strict_types=1);

namespace Model\ResourceModel\Job\Grid;

use Api\Data\JobInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface as SearchCriteriaInterfaceAlias;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Model\ResourceModel\Job\Collection as JobCollection;
use Psr\Log\LoggerInterface;

class Collection extends JobCollection implements SearchResultInterface
{
    /** @var AggregationInterface */
    protected AggregationInterface $aggregations;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param mixed|null $mainTable
     * @param AbstractDb $eventPrefix
     * @param mixed $eventObject
     * @param mixed $resourceModel
     * @param string $model
     * @param null $connection
     * @param AbstractDb|null $resource
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface        $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface       $eventManager,
                               $mainTable,
        AbstractDb             $eventPrefix,
                               $eventObject,
                               $resourceModel,
        string                 $model = 'Magento\Framework\View\Element\UiComponent\DataProvider\Document',
                               $connection = null,
        AbstractDb             $resource = null
    )
    {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $connection,
            $resource
        );
        $this->_eventPrefix = $eventPrefix;
        $this->_eventObject = $eventObject;
        $this->_init($model, $resourceModel);
        $this->setMainTable($mainTable);

        $this->addStatusToCollection();
    }

    /**
     * @return void
     */
    private function addStatusToCollection(): void
    {
        $this->addExpressionFieldToSelect(
            'status', "IF({{retries}} >= {{max_retries}}, '{{error}}', IF({{pid}} IS NULL, '{{new}}', '{{progress}}'))",
            [
                'pid' => JobInterface::FIELD_PID,
                'retries' => JobInterface::FIELD_RETRIES,
                'max_retries' => JobInterface::FIELD_MAX_RETRIES,
                'new' => JobInterface::STATUS_NEW,
                'error' => JobInterface::STATUS_ERROR,
                'progress' => JobInterface::STATUS_PROCESSING,
            ]);
    }

    /**
     * @return AggregationInterface
     */
    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    /**
     * @param AggregationInterface $aggregations
     * @return $this
     */
    public function setAggregations($aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    /**
     * @return null
     */
    public function getSearchCriteria()
    {
        return null;
    }

    /**
     * @param SearchCriteriaInterfaceAlias|null $searchCriteria
     *
     * @return $this
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setSearchCriteria(SearchCriteriaInterfaceAlias $searchCriteria = null): self
    {
        return $this;
    }

    /** @return int */
    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    /**
     * @param int $totalCount
     *
     * @return $this
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    /**
     * @param ExtensibleDataInterface[]|null $items
     *
     * @return $this
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setItems(array $items = null)
    {
        return $this;
    }
}
