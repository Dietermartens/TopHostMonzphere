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

use API,
	CAggFunctionData,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemHelper,
	CMacrosResolverHelper,
	CNumberParser,
	CSettingsHelper,
	Manager;

use Modules\TopHostsMonzphere\Widget;
use Modules\TopHostsMonzphere\Includes\WidgetForm;

class WidgetViewMonzphere extends CControllerDashboardWidgetView {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'name' => 'string',
			'groupids' => 'array',
			'hostids' => 'array',
			'evaltype' => 'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'tags' => 'array',
			'maintenance' => 'in 0,1',
			'columns' => 'array',
			'column' => 'int32',
			'order' => 'in '.Widget::ORDER_TOP_N.','.Widget::ORDER_BOTTOM_N,
			'show_lines' => 'int32',
			'override_hostid' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['error' => [
				'messages' => array_column(get_and_clear_messages(), 'message')
			]]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getInput('templateid', '') === '' || $this->checkAccess(CRoleHelper::UI_MONITORING_TEMPLATES);
	}

	protected function doAction(): void {
		$fields = $this->getFormFields();
		$fields = $this->unsetExtraFields($fields);

		$data = [
			'name' => $this->hasInput('name') ? $this->getInput('name') : $this->getDefaultHeader(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'configuration' => $this->getConfiguration($fields),
			'rows' => $this->getRows($fields),
			'error' => null
		];

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getFormFields(): array {
		return [
			'groupids' => $this->hasInput('groupids') ? $this->getInput('groupids') : [],
			'hostids' => $this->hasInput('hostids') ? $this->getInput('hostids') : [],
			'evaltype' => $this->hasInput('evaltype') ? $this->getInput('evaltype') : TAG_EVAL_TYPE_AND_OR,
			'tags' => $this->hasInput('tags') ? $this->getInput('tags') : [],
			'maintenance' => $this->hasInput('maintenance') ? $this->getInput('maintenance') : 0,
			'columns' => $this->hasInput('columns') ? $this->getInput('columns') : [],
			'column' => $this->hasInput('column') ? $this->getInput('column') : 0,
			'order' => $this->hasInput('order') ? $this->getInput('order') : Widget::ORDER_TOP_N,
			'show_lines' => $this->hasInput('show_lines') ? $this->getInput('show_lines') : 10,
			'override_hostid' => $this->hasInput('override_hostid') ? $this->getInput('override_hostid') : []
		];
	}

	private function getConfiguration(array $fields): array {
		$configuration = [];

		foreach ($fields['columns'] as $column) {
			$configuration[] = [
				'name' => $column['name'],
				'data' => $column['data'],
				'item' => $column['item'],
				'aggregate_function' => $column['aggregate_function'],
				'display' => $column['display'],
				'history' => $column['history'],
				'min' => $column['min'],
				'max' => $column['max'],
				'decimal_places' => $column['decimal_places'],
				'base_color' => $column['base_color'],
				'thresholds' => $column['thresholds'],
				'text' => $column['text']
			];
		}

		return $configuration;
	}

	private function getRows(array $fields): array {
		$groupids = $fields['groupids'];
		$hostids = $fields['hostids'];
		$evaltype = $fields['evaltype'];
		$tags = $fields['tags'];
		$maintenance = $fields['maintenance'];
		$configuration = $this->getConfiguration($fields);
		$column = $fields['column'];
		$order = $fields['order'];
		$show_lines = $fields['show_lines'];

		// Get hosts
		$hosts = [];
		if ($groupids || $hostids) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name', 'maintenance_status', 'maintenanceid'],
				'groupids' => $groupids ?: null,
				'hostids' => $hostids ?: null,
				'evaltype' => $evaltype,
				'tags' => $tags,
				'maintenance' => $maintenance,
				'preservekeys' => true
			]);
		}

		if (!$hosts) {
			return [];
		}

		$hostids = array_keys($hosts);

		// Get maintenance data
		$db_maintenances = [];
		if ($hosts) {
			$db_maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_unique(array_filter(array_column($hosts, 'maintenanceid'))),
				'preservekeys' => true
			]);
		}

		$db_maintenances = CArrayHelper::renameObjectsKeys($db_maintenances,
			['name' => 'maintenance_name', 'description' => 'maintenance_description']
		);

		$has_text_column = false;
		$item_names = [];
		$items = [];

		foreach ($configuration as $column_index => $column_config) {
			switch ($column_config['data']) {
				case WidgetForm::DATA_TEXT:
					$has_text_column = true;
					break 2;

				case WidgetForm::DATA_ITEM_VALUE:
					$item_names[$column_index] = $column_config['item'];
					break;
			}
		}

		if (!$has_text_column && $item_names) {
			$hosts_with_items = [];

			foreach ($item_names as $column_index => $item_name) {
				$numeric_only = self::isNumericOnlyColumn($configuration[$column_index]);
				$items[$column_index] = self::getItems($item_name, $numeric_only, $groupids, $hostids);

				foreach ($items[$column_index] as $item) {
					$hosts_with_items[$item['hostid']] = true;
				}
			}

			$hostids = array_keys($hosts_with_items);
			$hosts = array_intersect_key($hosts, $hosts_with_items);
		}

		if (!$hostids) {
			return [];
		}

		$master_column_index = $column;
		$master_column = $configuration[$master_column_index];
		$master_entities = $hosts;
		$master_entity_values = [];

		switch ($master_column['data']) {
			case WidgetForm::DATA_ITEM_VALUE:
				$master_entities = array_key_exists($master_column_index, $items)
					? $items[$master_column_index]
					: self::getItems($master_column['item'], self::isNumericOnlyColumn($master_column), $groupids,
						$hostids
					);
				$master_entity_values = self::getItemValues($master_entities, $master_column);
				break;

			case WidgetForm::DATA_HOST_NAME:
				$master_entity_values = array_column($master_entities, 'name', 'hostid');
				break;

			case WidgetForm::DATA_TEXT:
				$master_entity_values = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns(
					[$master_column_index => $master_column['text']], $hostids
				)[$master_column_index];

				foreach ($master_entity_values as $key => $value) {
					if ($value === '') {
						unset($master_entity_values[$key], $master_entities[$key]);
					}
				}

				break;
		}

		$master_items_only_numeric_present = $master_column['data'] == WidgetForm::DATA_ITEM_VALUE
			&& ($master_column['aggregate_function'] == AGGREGATE_COUNT
				|| !array_filter($master_entities,
					static function(array $item): bool {
						return !in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
					}
				)
			);

		if ($order == Widget::ORDER_TOP_N) {
			if ($master_items_only_numeric_present) {
				arsort($master_entity_values, SORT_NUMERIC);

				$master_entities_min = end($master_entity_values);
				$master_entities_max = reset($master_entity_values);
			}
			else {
				natcasesort($master_entity_values);
			}
		}
		else {
			if ($master_items_only_numeric_present) {
				asort($master_entity_values, SORT_NUMERIC);

				$master_entities_min = reset($master_entity_values);
				$master_entities_max = end($master_entity_values);
			}
			else {
				natcasesort($master_entity_values);
				$master_entity_values = array_reverse($master_entity_values, true);
			}
		}

		$master_hostids = array_slice(array_keys($master_entity_values), 0, $show_lines, true);

		// Get item values for all columns
		$item_values = [];
		$text_columns = [];

		foreach ($configuration as $column_index => &$column) {
			if ($column['data'] == WidgetForm::DATA_TEXT) {
				$text_columns[$column_index] = $column['text'];
				continue;
			}

			if ($column['data'] != WidgetForm::DATA_ITEM_VALUE) {
				continue;
			}

			$calc_extremes = $column['display'] == WidgetForm::DISPLAY_BAR
				|| $column['display'] == WidgetForm::DISPLAY_INDICATORS;

			if ($column_index == $master_column_index) {
				$column_items = $master_entities;
				$column_item_values = $master_entity_values;
			}
			else {
				$numeric_only = self::isNumericOnlyColumn($column);

				if (!$calc_extremes || ($column['min'] !== '' && $column['max'] !== '')) {
					$column_items = self::getItems($column['item'], $numeric_only, $groupids, $master_hostids);
				}
				else {
					$column_items = array_key_exists($column_index, $items)
						? $items[$column_index]
						: self::getItems($column['item'], $numeric_only, $groupids, $hostids);
				}

				$column_item_values = self::getItemValues($column_items, $column);
			}

			if ($calc_extremes && ($column['min'] !== '' || $column['max'] !== '')) {
				if ($column['min'] !== '') {
					$number_parser_binary->parse($column['min']);
					$column['min_binary'] = $number_parser_binary->calcValue();

					$number_parser->parse($column['min']);
					$column['min'] = $number_parser->calcValue();
				}

				if ($column['max'] !== '') {
					$number_parser_binary->parse($column['max']);
					$column['max_binary'] = $number_parser_binary->calcValue();

					$number_parser->parse($column['max']);
					$column['max'] = $number_parser->calcValue();
				}
			}
			elseif ($calc_extremes) {
				$column['min'] = $column_items ? min(array_column($column_item_values, 'value')) : 0;
				$column['max'] = $column_items ? max(array_column($column_item_values, 'value')) : 100;
			}

			$item_values[$column_index] = $column_item_values;
		}
		unset($column);

		// Resolve text columns
		if ($text_columns) {
			$text_columns = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns($text_columns, $master_hostids);
		}

		// Build rows
		$rows = [];

		foreach ($master_hostids as $hostid) {
			$row = [];

			foreach ($configuration as $column_index => $column) {
				switch ($column['data']) {
					case WidgetForm::DATA_HOST_NAME:
						$data = [
							'value' => $hosts[$hostid]['name'],
							'hostid' => $hostid,
							'maintenance_status' => $hosts[$hostid]['maintenance_status']
						];

						if ($data['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
							$data = array_merge($data, $db_maintenances[$hosts[$hostid]['maintenanceid']]);
						}

						$row[] = $data;

						break;

					case WidgetForm::DATA_TEXT:
						$row[] = ['value' => $text_columns[$column_index][$hostid]];

						break;

					case WidgetForm::DATA_ITEM_VALUE:
						$row[] = array_key_exists($hostid, $item_values[$column_index])
							? [
								'value' => $item_values[$column_index][$hostid]['value'],
								'item' => $item_values[$column_index][$hostid]['item'],
								'is_binary_units' => $item_values[$column_index][$hostid]['is_binary_units']
							]
							: null;

						break;
				}
			}

			$rows[] = [
				'columns' => $row,
				'context' => ['hostid' => $hostid]
			];
		}

		return $rows;
	}

	/**
	 * Check if column configuration requires selecting numeric items only.
	 *
	 * @param array $column  Column configuration.
	 *
	 * @return bool
	 */
	private static function isNumericOnlyColumn(array $column): bool {
		if ($column['display'] == WidgetForm::DISPLAY_AS_IS) {
			return CAggFunctionData::requiresNumericItem($column['aggregate_function']);
		}

		return $column['aggregate_function'] != AGGREGATE_COUNT;
	}

	/**
	 * Get items by name pattern.
	 *
	 * @param string $item_name  Item name pattern.
	 * @param bool   $numeric_only  Whether to select numeric items only.
	 * @param array  $groupids  Host group IDs.
	 * @param array  $hostids   Host IDs.
	 *
	 * @return array
	 */
	private static function getItems(string $item_name, bool $numeric_only, array $groupids, array $hostids): array {
		$value_types = $numeric_only
			? [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]
			: [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT];

		return API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'value_type', 'units'],
			'selectHosts' => ['hostid', 'name'],
			'hostids' => $hostids,
			'groupids' => $groupids ?: null,
			'search' => ['name' => $item_name],
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'value_type' => $value_types,
			'preservekeys' => true
		]);
	}

	/**
	 * Get item values.
	 *
	 * @param array $items   Items.
	 * @param array $column  Column configuration.
	 *
	 * @return array
	 */
	private static function getItemValues(array $items, array $column): array {
		if (!$items) {
			return [];
		}

		$items = self::addDataSource($items, time(), $column);

		$item_values = [];

		foreach ($items as $item) {
			$value = CItemHelper::getHistory($item, $column['aggregate_function'], $column['time_period']);

			if ($value !== null) {
				$item_values[$item['hostid']] = [
					'value' => $value,
					'item' => $item,
					'is_binary_units' => CNumberParser::isBinaryUnits($item['units'])
				];
			}
		}

		return $item_values;
	}

	/**
	 * Add data source to items.
	 *
	 * @param array $items   Items.
	 * @param int   $time    Time.
	 * @param array $column  Column configuration.
	 *
	 * @return array
	 */
	private static function addDataSource(array $items, int $time, array $column): array {
		if ($column['history'] == WidgetForm::HISTORY_DATA_AUTO) {
			$items = CItemHelper::addDataSource($items, $time);
		}
		else {
			foreach ($items as &$item) {
				$item['source'] = $column['history'] == WidgetForm::HISTORY_DATA_TRENDS
					? 'trends'
					: 'history';
			}
			unset($item);
		}

		return $items;
	}
}
