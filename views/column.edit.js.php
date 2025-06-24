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


use Modules\TopHostsMonzphere\Includes\WidgetForm;

?>

window.tophosts_column_edit_form = new class {

	init({form_name, thresholds, thresholds_colors}) {
		this._$widget_form = $(`form[name="${form_name}"]`);

		this._$thresholds_table = this._$widget_form.find('#thresholds_table');

		$('[name="data"], [name="aggregate_function"], [name="display"], [name="history"]', this._$widget_form)
			.on('change', () => this._update());

		colorPalette.setThemeColors(thresholds_colors);

		this._$thresholds_table.dynamicRows({
			rows: thresholds,
			template: '#thresholds-row-tmpl',
			allow_empty: true,
			dataCallback: (row_data) => {
				if (!('color' in row_data)) {
					const colors = this._$widget_form[0].querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input');
					const used_colors = [];

					for (const color of colors) {
						if (color.value !== '') {
							used_colors.push(color.value);
						}
					}

					row_data.color = colorPalette.getNextColor(used_colors);
				}
			}
		});

		$('tr.form_row input[name$="[color]"]', this._$thresholds_table).each((i, colorpicker) => {
			$(colorpicker).colorpicker({appendTo: $(colorpicker).closest('.input-color-picker')});
		});

		this._$thresholds_table
			.on('afteradd.dynamicRows', e => {
				const $colorpicker = $('tr.form_row:last input[name$="[color]"]', e.target);

				$colorpicker.colorpicker({appendTo: $colorpicker.closest('.input-color-picker')});

				this._update();
			})
			.on('afterremove.dynamicRows', () => this._update());

		this._$widget_form[0].addEventListener('change', (e) => e.target.value = e.target.value.trim(),
			{capture: true}
		);

		// Initialize form elements accessibility.
		this._update();

		this._$widget_form[0].style.display = '';
		this._$widget_form[0].querySelector('[name="name"]').focus();

		this._$widget_form.on('process.form', (e, overlay) => {
			this.handleFormSubmit(e, overlay);
		});
	}

	_update() {
		const display_as_is = ($('[name="display"]:checked').val() == <?= WidgetForm::DISPLAY_AS_IS ?>);
		const history_data_trends = ($('[name="history"]:checked').val() ==
			<?= WidgetForm::HISTORY_DATA_TRENDS ?>);
		const data_item_value = ($('[name="data"]').val() == <?= WidgetForm::DATA_ITEM_VALUE ?>);
		const data_text = ($('[name="data"]').val() == <?= WidgetForm::DATA_TEXT ?>);
		const aggregate_function = parseInt(document.getElementById('aggregate_function').value);

		$('#item', this._$widget_form).multiSelect(data_item_value ? 'enable' : 'disable');
		$('[name="aggregate_function"]', this._$widget_form).attr('disabled', !data_item_value);
		$('[name="display"],[name="history"]', this._$widget_form).attr('disabled', !data_item_value);
		$('[name="text"]', this._$widget_form).attr('disabled', !data_text);
		$('[name="min"],[name="max"]', this._$widget_form).attr('disabled', display_as_is || !data_item_value);
		$('[name="decimal_places"]', this._$widget_form).attr('disabled', !data_item_value);
		this._$thresholds_table.toggleClass('disabled', !data_item_value);

		// Toggle warning icons for non-numeric items settings.
		$('#tophosts-column-display-warning').toggle(display_as_is);
		$('#tophosts-column-aggregate-function-warning').toggle(aggregate_function == <?= AGGREGATE_COUNT ?>);
	}

	handleFormSubmit(e, overlay) {
		const formData = getFormFields(this._$widget_form[0]);

		// Validate required fields
		if (!formData.name.trim()) {
			alert(_('Name is required'));
			return;
		}

		if (formData.data == <?= WidgetForm::DATA_ITEM_VALUE ?> && !formData.item.trim()) {
			alert(_('Item name is required'));
			return;
		}

		if (formData.data == <?= WidgetForm::DATA_TEXT ?> && !formData.text.trim()) {
			alert(_('Text is required'));
			return;
		}

		// Process thresholds
		const thresholds = [];
		$('#thresholds_table tr.form_row').each(function() {
			const color = $(this).find('input[name$="[color]"]').val();
			const threshold = $(this).find('input[name$="[threshold]"]').val();
			
			if (threshold.trim()) {
				thresholds.push({
					color: color || '#97AAB3',
					threshold: threshold.trim()
				});
			}
		});

		formData.thresholds = thresholds;

		// Close popup and update parent form
		overlay.close();
		window.widget_tophosts_form.updateColumns({detail: formData});
	}
};
