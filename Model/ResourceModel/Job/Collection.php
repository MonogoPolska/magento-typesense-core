<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\ResourceModel\Job;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Monogo\TypesenseCore\Model\ResourceModel\Job;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'job_id';

    protected $_eventPrefix = 'typesense_queue_job_collection';

    protected $_eventObject = 'jpb_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Monogo\TypesenseCore\Model\Job::class,
            Job::class
        );
    }
}
