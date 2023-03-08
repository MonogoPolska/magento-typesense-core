<?php
declare(strict_types=1);

namespace Traits;

trait AdditionalDataTrait
{
    /**
     * @var array
     */
    protected array $additionalData = [];

    /**
     * @var array
     */
    protected array $dataFormatted = [];

    /**
     * @return array
     */
    public function getAdditionalData(): array
    {
        if (empty($this->dataFormatted)) {
            foreach ($this->additionalData as $name => $label) {
                $this->dataFormatted[] = [
                    'name' => $name,
                    'label' => $label
                ];
            }
        }
        return $this->dataFormatted;
    }
}
