<?php
declare(strict_types=1);

namespace Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Zend_Db_Statement_Exception;

class Job extends AbstractDb
{
    /**
     * @return array
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    public function getQueueInfo(): array
    {
        $select = $this->getConnection()->select()
            ->from(
                [$this->getMainTable()],
                [
                    'count' => 'COUNT(*)',
                    'oldest' => 'MIN(created)',
                ]
            );

        $queueInfo = $this->getConnection()->query($select)->fetch();

        if (!$queueInfo['oldest']) {
            $queueInfo['oldest'] = '[no jobs in indexing queue]';
        }

        return $queueInfo;
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('typesense_queue', 'job_id');
    }
}
