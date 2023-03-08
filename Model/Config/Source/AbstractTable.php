<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Model\Config\Source;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\BlockInterface;
use Monogo\TypesenseCore\Block\System\Form\Field\Select;
use Monogo\TypesenseCore\Services\ConfigService;
use Monogo\TypesenseCore\Traits\AdditionalDataTrait;

/**
 * Typesense custom sort order field
 */
abstract class AbstractTable extends AbstractFieldArray
{
    use AdditionalDataTrait;

    /**
     * @var array
     */
    protected array $selectFields = [];

    /**
     * @var ConfigService
     */
    protected ConfigService $config;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $connection;

    /**
     * @param Context $context
     * @param ConfigService $configService
     * @param ResourceConnection $connection
     * @param array $data
     * @param array $additionalData
     */
    public function __construct(
        Context            $context,
        ConfigService      $configService,
        ResourceConnection $connection,
        array              $data = [],
        array              $additionalData = []
    )
    {
        parent::__construct($context, $data);
        $this->config = $configService;
        $this->connection = $connection;
        $this->additionalData = $additionalData;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->prepareColumns();
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function prepareColumns(): void
    {
        $data = $this->getTableData();

        foreach (array_keys($data) as $columnId) {
            $columnData = $data[$columnId];

            $column = [
                'label' => __($columnData['label']),
            ];

            if (isset($columnData['values'])) {
                $column['renderer'] = $this->getRenderer($columnId, $columnData);
            }

            if (isset($columnData['class'])) {
                $column['class'] = $columnData['class'];
            }

            if (isset($columnData['style'])) {
                $column['style'] = $columnData['style'];
            }

            $this->addColumn($columnId, $column);
        }

        $this->_addAfter = false;
        parent::_construct();
    }

    /**
     * @return array
     */
    abstract protected function getTableData(): array;

    /**
     * @param $columnId
     * @param $columnData
     * @return BlockInterface
     * @throws LocalizedException
     */
    protected function getRenderer($columnId, $columnData): BlockInterface
    {
        if (!array_key_exists($columnId, $this->selectFields) || !$this->selectFields[$columnId]) {

            $select = $this->getLayout()
                ->createBlock(Select::class, '', [
                    'data' => ['is_render_to_js_template' => true],
                ]);

            $options = $columnData['values'];

            if (is_callable($options)) {
                $options = $options();
            }

            $extraParams = $columnId === 'attribute' ? 'style="width:160px;"' : 'style="width:100px;"';
            $select->setData('extra_params', $extraParams);
            $select->setOptions($options);

            $this->selectFields[$columnId] = $select;
        }

        return $this->selectFields[$columnId];
    }

    /**
     * @param DataObject $row
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $data = $this->getTableData();
        $options = [];
        foreach (array_keys($data) as $columnId) {
            $columnData = $data[$columnId];

            if (isset($columnData['values'])) {
                $index = 'option_' . $this->getRenderer($columnId, $columnData)
                        ->calcOptionHash($row->getData($columnId));

                $options[$index] = 'selected="selected"';
            }
        }

        if ($row['_id'] === null || is_int($row['_id'])) {
            $row->setData('_id', '_' . random_int(1000000000, 9999999999) . '_' . random_int(0, 999));
        }

        $row->setData('option_extra_attrs', $options);
    }
}
