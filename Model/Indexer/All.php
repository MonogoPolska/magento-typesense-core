<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Zend_Db_Statement_Exception;

class All implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'typesense_all';

    /**
     * @param $ids
     * @return $this|void
     */
    public function execute($ids)
    {
        return $this;
    }

    /**
     * @return void
     * @throws Zend_Db_Statement_Exception
     */
    public function executeFull()
    {

    }

    /**
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids)
    {

    }

    /**
     * @param $id
     * @return void
     */
    public function executeRow($id)
    {

    }
}
