<?php
declare(strict_types=1);

namespace Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use function Monogo\TypesenseCore\Model\Config\Source\__;

class RetryValues implements OptionSourceInterface
{
    /**
     * @var array
     */
    private array $values = [
        1 => '1',
        2 => '2',
        3 => '3',
        5 => '5',
        10 => '10',
        20 => '20',
        50 => '50',
        100 => '100',
        9999999 => 'unlimited',
    ];

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->values as $key => $value) {
            $options[] = [
                'value' => $key,
                'label' => __($value),
            ];
        }
        return $options;
    }
}
