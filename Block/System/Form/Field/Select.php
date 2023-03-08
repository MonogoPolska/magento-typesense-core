<?php
declare(strict_types=1);

namespace Block\System\Form\Field;

class Select extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @return string
     */
    protected function _toHtml()
    {
        $this->setData('name', $this->getData('input_name'));
        $this->setClass('select');
        return trim(preg_replace('/\s+/', ' ', parent::_toHtml()));
    }
}
