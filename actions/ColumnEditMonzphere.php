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


namespace Modules\TopHostsMonzphere\Actions;

use CController,
	CControllerResponseData,
	CNumberParser,
	CParser,
	CWidgetsData;

use Zabbix\Widgets\CWidgetField;

use Modules\TopHostsMonzphere\Includes\WidgetForm;

use Zabbix\Widgets\Fields\{
	CWidgetFieldTimePeriod
};

class ColumnEditMonzphere extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		// Validation is done by CWidgetField
		$fields = [
			'name' =>				'string',
			'data' =>				'int32',
			'item' =>				'string',
			'aggregate_function' =>	'int32',
			'time_period' =>		'array',
			'display' =>			'int32',
			'history' =>			'int32',
			'min' =>				'string',
			'max' =>				'string',
			'decimal_places' =>		'string',
			'base_color' =>			'string',
			'thresholds' =>			'array',
			'text' =>				'string',
			'edit' =>				'in 1',
			'update' =>				'in 1',
			'templateid' =>			'string'
		];

		$ret = $this->validateInput($fields) && $this->validateFields();

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)]))->disableView()
			);
		}

		return $ret;
	}

	protected function validateFields(): bool {
		if (!$this->hasInput('update')) {
			return true;
		}

		$input = $this->getInputAll();

		$field = new CWidgetField('columns', '');

		if (!$this->hasInput('edit') && !$this->hasInput('update')) {
			$input += self::getColumnDefaults();
		}

		unset($input['edit'], $input['update'], $input['templateid']);
		$field->setValue([$input]);

		$errors = $field->validate(true);
		array_map('error', $errors);

		return !$errors;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$input = $this->getInputAll();
		unset($input['update']);

		if (!$this->hasInput('update')) {
			$data = [
				'action' => $this->getAction(),
				'thresholds_colors' => ['#97AAB3', '#7499FF', '#FFC859', '#FFA059', '#E97659', '#E45959', '#C8766E', '#A6A6A6'],
				'templateid' => $this->hasInput('templateid') ? $this->getInput('templateid') : null,
				'errors' => hasErrorMessages() ? getMessages() : null,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			] + $input + self::getColumnDefaults();

			$data['time_period_field'] = (new CWidgetFieldTimePeriod('time_period', 'Time period'))
				->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
				->setInType(CWidgetsData::DATA_TYPE_TIME_PERIOD)
				->acceptDashboard()
				->acceptWidget()
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK);

			$data['time_period_field']->setValue($data['time_period']);

			$this->setResponse(new CControllerResponseData($data));

			return;
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$thresholds = [];

		if (array_key_exists('thresholds', $input)) {
			foreach ($input['thresholds'] as $threshold) {
				$order_threshold = trim($threshold['threshold']);

				if ($order_threshold !== '' && $number_parser->parse($order_threshold) == CParser::PARSE_SUCCESS) {
					$thresholds[] = $threshold + ['order_threshold' => $number_parser->calcValue()];
				}
			}

			unset($input['thresholds']);
		}

		if ($thresholds) {
			uasort($thresholds,
				static function (array $threshold_1, array $threshold_2): int {
					return $threshold_1['order_threshold'] <=> $threshold_2['order_threshold'];
				}
			);

			$input['thresholds'] = [];

			foreach ($thresholds as $threshold) {
				unset($threshold['order_threshold']);

				$input['thresholds'][] = $threshold;
			}
		}

		$this->setResponse(
			(new CControllerResponseData(['main_block' => json_encode($input, JSON_THROW_ON_ERROR)]))->disableView()
		);
	}

	/**
	 * Retrieve the default configuration values for column in Top hosts widget.
	 *
	 * @return array
	 */
	private static function getColumnDefaults(): array {
		static $column_defaults;

		if ($column_defaults === null) {
			$column_defaults = [
				'name' => '',
				'data' => WidgetForm::DATA_ITEM_VALUE,
				'item' => '',
				'aggregate_function' => AGGREGATE_NONE,
				'time_period' => [
					CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
						CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
					)
				],
				'display' => WidgetForm::DISPLAY_AS_IS,
				'history' => WidgetForm::HISTORY_DATA_AUTO,
				'min' => '',
				'max' => '',
				'decimal_places' => '2',
				'base_color' => '',
				'text' => '',
				'thresholds' => []
			];
		}

		return $column_defaults;
	}
}
