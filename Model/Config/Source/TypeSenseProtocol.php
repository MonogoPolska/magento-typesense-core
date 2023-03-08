<?php
declare(strict_types=1);

namespace Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use function Monogo\TypesenseCore\Model\Config\Source\__;

class TypeSenseProtocol implements OptionSourceInterface
{
    const HTTP = 'http';
    const HTTPS = 'https';

    /**
     * @var array
     */
    private array $protocols = [
        self::HTTP => 'HTTP',
        self::HTTPS => 'HTTPS',
    ];

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->protocols as $key => $value) {
            $options[] = [
                'value' => $key,
                'label' => __($value),
            ];
        }
        return $options;
    }
}
