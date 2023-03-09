<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Config\Source;

class Sections extends AbstractTable
{
    /**
     * @return array
     */
    protected function getTableData(): array
    {
        return [
            'name' => [
                'label' => 'Section',
                'values' => function () {
                    $options = [];
                    $sections = $this->getAdditionalData();
                    foreach ($this->config->getFacets() as $attribute) {
                        if ($attribute['attribute'] === 'price') {
                            continue;
                        }

                        if ($attribute['attribute'] === 'category' || $attribute['attribute'] === 'categories') {
                            continue;
                        }

                        $sections[] = [
                            'name' => $attribute['attribute'],
                            'label' => $attribute['label'] ?: $attribute['attribute'],
                        ];
                    }

                    foreach ($sections as $section) {
                        $options[$section['name']] = $section['label'];
                    }
                    return $options;
                },
            ],
            'label' => [
                'label' => 'Label',
            ],
            'hitsPerPage' => [
                'label' => 'Hits per page',
                'class' => 'validate-digits',
            ],
        ];
    }
}
