<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Traits;

trait CastTrait
{
    /**
     * @var array
     */
    private array $nonCastableAttributes = ['id', 'sku', 'name', 'description', 'query'];

    /**
     * @param array $nonCastableAttributes
     */
    public function __construct(array $nonCastableAttributes = [])
    {
        $this->nonCastableAttributes = array_unique(array_merge($this->nonCastableAttributes, $nonCastableAttributes));
    }

    /**
     * @param array $object
     * @return array
     */
    private function castRecord(array $object): array
    {
        foreach ($object as $key => &$value) {
            if (in_array($key, $this->nonCastableAttributes, true) === true) {
                continue;
            }
            $value = $this->castAttribute($value);
        }
        return $object;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function castAttribute(mixed $value): mixed
    {
        if (is_numeric($value) && floatval($value) === floatval((int)$value)) {
            return (int)$value;
        }

        if (is_numeric($value) && $this->isValidFloat($value)) {
            return floatval($value);
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function isValidFloat(mixed $value): bool
    {
        return floatval($value) !== INF;
    }
}
