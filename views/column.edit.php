<?php
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


/**
 * @var CView $this
 * @var array $data
 */

use Modules\TopHostsMonzphere\Includes\WidgetForm;

$form = (new CForm())
	->setName('tophosts_column')
	->addStyle('display: none;')
	->addVar('action', $data['action'])
	->addVar('update', 1);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = new CFormGrid();

$scripts = [];

if (array_key_exists('edit', $data)) {
	$form->addVar('edit', 1);
}

// Name.
$form_grid->addItem([
	(new CLabel(_('Name'), 'column_name'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('name', $data['name'], false))
			->setId('column_name')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
]);

// Data.
$form_grid->addItem([
	new CLabel(_('Data'), 'data'),
	new CFormField(
		(new CSelect('data'))
			->setValue($data['data'])
			->addOptions(CSelect::createOptionsFromArray([
				WidgetForm::DATA_ITEM_VALUE => _('Item value'),
				WidgetForm::DATA_HOST_NAME => _('Host name'),
				WidgetForm::DATA_TEXT => _('Text')
			]))
			->setFocusableElementId('data')
	)
]);

// Static text.
$form_grid->addItem([
	(new CLabel(_('Text'), 'text'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('text', $data['text']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Text, supports {INVENTORY.*}, {HOST.*} macros'))
	)
]);

// Item.
$parameters = [
	'srctbl' => 'items',
	'srcfld1' => 'name',
	'dstfrm' => $form->getName(),
	'dstfld1' => 'item',
	'value_types' => [
		ITEM_VALUE_TYPE_FLOAT,
		ITEM_VALUE_TYPE_STR,
		ITEM_VALUE_TYPE_LOG,
		ITEM_VALUE_TYPE_UINT64,
		ITEM_VALUE_TYPE_TEXT
	]
];

if ($data['templateid'] === '') {
	$parameters['real_hosts'] = 1;
	$parameters['resolve_macros'] = 1;
}
else {
	$parameters += [
		'hostid' => $data['templateid'],
		'hide_host_filter' => true
	];
}

$item_select = (new CPatternSelect([
	'name' => 'item',
	'object_name' => 'items',
	'data' => $data['item'] === '' ? '' : [$data['item']],
	'multiple' => false,
	'popup' => [
		'parameters' => $parameters
	],
	'add_post_js' => false
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$scripts[] = $item_select->getPostJS();

$form_grid->addItem([
	(new CLabel(_('Item name'), 'item_ms'))->setAsteriskMark(),
	new CFormField($item_select)
]);

// Display.
$form_grid->addItem([
	new CLabel(
		[
			_('Display'),
			(makeWarningIcon(_('With this setting only numeric data will be displayed.')))
				->setId('tophosts-column-display-warning')
		],
		'display'
	),
	new CFormField(
		(new CRadioButtonList('display', (int) $data['display']))
			->addValue(_('As is'), WidgetForm::DISPLAY_AS_IS)
			->addValue(_('Bar'), WidgetForm::DISPLAY_BAR)
			->addValue(_('Indicators'), WidgetForm::DISPLAY_INDICATORS)
			->setModern()
	)
]);

// Min value.
$form_grid->addItem([
	new CLabel(_('Min'), 'min'),
	new CFormField(
		(new CTextBox('min', $data['min']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	)
]);

// Max value.
$form_grid->addItem([
	new CLabel(_('Max'), 'max'),
	new CFormField(
		(new CTextBox('max', $data['max']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	)
]);

// Base color.
$form_grid->addItem([
	new CLabel(_('Base color'), 'base_color'),
	new CFormField(
		(new CColorPicker('base_color', $data['base_color']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
]);

// Thresholds.
$form_grid->addItem([
	new CLabel(_('Thresholds'), 'thresholds'),
	new CFormField(
		(new CDiv())
			->addClass('thresholds-table')
			->setId('thresholds_table')
	)
]);

// Aggregation function.
$form_grid->addItem([
	new CLabel(
		[
			_('Aggregation function'),
			(makeWarningIcon(_('With this setting only numeric items will be displayed.')))
				->setId('tophosts-column-aggregate-function-warning')
		],
		'column_aggregate_function'
	),
	new CFormField(
		(new CSelect('aggregate_function'))
			->setId('aggregate_function')
			->setValue($data['aggregate_function'])
			->addOptions(CSelect::createOptionsFromArray([
				AGGREGATE_NONE => CItemHelper::getAggregateFunctionName(AGGREGATE_NONE),
				AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
				AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
				AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
				AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
				AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM),
				AGGREGATE_FIRST => CItemHelper::getAggregateFunctionName(AGGREGATE_FIRST),
				AGGREGATE_LAST => CItemHelper::getAggregateFunctionName(AGGREGATE_LAST)
			]))
			->setFocusableElementId('column_aggregate_function')
	)
]);

$time_period_field_view = (new CWidgetFieldTimePeriodView($data['time_period_field']))
	->setDateFormat(ZBX_FULL_DATE_TIME)
	->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	->setFormName('tophosts_column')
	->addClass('js-time-period');

foreach ($time_period_field_view->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
	$form_grid->addItem([
		$label,
		(new CFormField($view))->addClass($class)
	]);
}

$form_grid->addItem(new CScriptTag([
	'document.forms.tophosts_column.fields = {};',
	$time_period_field_view->getJavaScript()
]));

// History data.
$form_grid->addItem([
	new CLabel(_('History data'), 'history'),
	new CFormField(
		(new CRadioButtonList('history', (int) $data['history']))
			->addValue(_('Auto'), WidgetForm::HISTORY_DATA_AUTO)
			->addValue(_('History'), WidgetForm::HISTORY_DATA_HISTORY)
			->addValue(_('Trends'), WidgetForm::HISTORY_DATA_TRENDS)
			->setModern()
	)
]);

// Decimal places.
$form_grid->addItem([
	new CLabel(_('Decimal places'), 'decimal_places'),
	new CFormField(
		(new CTextBox('decimal_places', $data['decimal_places']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', '2')
	)
]);

$form->addItem($form_grid);

$form->addItem(
	(new CDiv())
		->addClass('tfoot')
		->addItem(
			(new CDiv())
				->addClass('btn-container')
				->addItem(
					(new CButton('submit', _('Update')))
						->setAttribute('data-action', 'update')
						->addClass('btn-alt')
				)
				->addItem(
					(new CButton('cancel', _('Cancel')))
						->addClass('btn-alt')
				)
		)
);

$form->show();

// Thresholds table template.
(new CTemplateTag('thresholds-row-tmpl'))->addItem(
	(new CRow([
		(new CCol(
			(new CColorPicker('thresholds[#{rowNum}][color]', ''))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
		))->addClass('input-color-picker'),
		(new CCol(
			(new CTextBox('thresholds[#{rowNum}][threshold]', ''))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAttribute('placeholder', _('Threshold'))
		)),
		(new CCol(
			(new CButton('remove', _('Remove')))
				->addClass('btn-link btn-remove')
		))
	]))
		->addClass('form_row')
		->show()
);

(new CScriptTag('
	document.addEventListener("DOMContentLoaded", function() {
		tophosts_column_edit_form.init({
			form_name: "tophosts_column",
			thresholds: '.json_encode($data['thresholds']).',
			thresholds_colors: '.json_encode($data['thresholds_colors']).'
		});
	});
'))->show();
