<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Indexer;
use Monogo\TypesenseCore\Traits\AdditionalDataTrait;

class RegisteredIndexers
{
    use AdditionalDataTrait;
    /**
     * @param array $additionalData
     */
    public function __construct(array                  $additionalData = [])
    {
        $this->additionalData = $additionalData;
    }
}
