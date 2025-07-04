<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Modules\TopHostsMonzphere\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTags
};

use Modules\TopHostsMonzphere\Widget;

/**
 * Top hosts data widget form.
 */
class WidgetForm extends CWidgetForm {

	private const DEFAULT_HOSTS_COUNT = 10;
	private const DEFAULT_ORDER_COLUMN = 0;

	// Column data types
	public const DATA_ITEM_VALUE = 1;
	public const DATA_HOST_NAME = 2;
	public const DATA_TEXT = 3;

	// Column display types
	public const DISPLAY_AS_IS = 0;
	public const DISPLAY_BAR = 1;
	public const DISPLAY_INDICATORS = 2;

	// History data types
	public const HISTORY_DATA_AUTO = 0;
	public const HISTORY_DATA_HISTORY = 1;
	public const HISTORY_DATA_TRENDS = 2;

	private array $field_column_values = [];

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('columnsthresholds', $values)) {
			foreach ($values['columnsthresholds'] as $column_index => $fields) {
				$values['columns'][$column_index]['thresholds'] = [];

				foreach ($fields as $field_key => $field_values) {
					foreach ($field_values as $value_index => $value) {
						$values['columns'][$column_index]['thresholds'][$value_index][$field_key] = $value;
					}
				}
			}
		}

		// Apply sortable changes to data.
		if (array_key_exists('sortorder', $values)) {
			if (array_key_exists('column', $values) && array_key_exists('columns', $values['sortorder'])) {
				// Fix selected column index when columns were sorted.
				$values['column'] = array_search($values['column'], $values['sortorder']['columns'], true);
			}

			foreach ($values['sortorder'] as $key => $sortorder) {
				if (!array_key_exists($key, $values)) {
					continue;
				}

				$sorted = [];

				foreach ($sortorder as $index) {
					$sorted[] = $values[$key][$index];
				}

				$values[$key] = $sorted;
			}
		}

		if (array_key_exists('columns', $values)) {
			foreach ($values['columns'] as $key => $value) {
				$value['name'] = trim($value['name']);

				switch ($value['data']) {
					case self::DATA_ITEM_VALUE:
						$this->field_column_values[$key] = $value['name'] === '' ? $value['item'] : $value['name'];
						break;

					case self::DATA_HOST_NAME:
						$this->field_column_values[$key] = $value['name'] === '' ? _('Host name') : $value['name'];
						break;

					case self::DATA_TEXT:
						$this->field_column_values[$key] = $value['name'] === '' ? $value['text'] : $value['name'];
						break;
				}
			}
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('evaltype', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('tags')
			)
			->addField(
				new CWidgetFieldCheckBox('maintenance',
					$this->isTemplateDashboard() ? _('Show data in maintenance') : _('Show hosts in maintenance')
				)
			)
			->addField(
				(new CWidgetField('columns', _('Columns')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldSelect('column', _('Order by'), $this->field_column_values))
					->setDefault($this->field_column_values
						? self::DEFAULT_ORDER_COLUMN
						: CWidgetFieldSelect::DEFAULT_VALUE
					)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('order', _('Order'), [
					Widget::ORDER_TOP_N => _('Top N'),
					Widget::ORDER_BOTTOM_N => _('Bottom N')
				]))->setDefault(Widget::ORDER_TOP_N)
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldIntegerBox('show_lines', _('Host limit'), ZBX_MIN_WIDGET_LINES,
					ZBX_MAX_WIDGET_LINES
				))
					->setDefault(self::DEFAULT_HOSTS_COUNT)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
