<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

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
