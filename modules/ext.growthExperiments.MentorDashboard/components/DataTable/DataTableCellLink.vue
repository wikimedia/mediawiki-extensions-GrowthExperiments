<template>
	<data-table-cell-value :align="align">
		<a
			:data-link-id="'data-link-' + fieldName"
			:href="value.link.href"
			:alt="value.link.text"
		>
			{{ value.value }}
		</a>
	</data-table-cell-value>
</template>

<script>
const alignValidator = require( '../../validators/align-text.validator.js' );
const DataTableCellValue = require( './DataTableCellValue.vue' );
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	components: {
		DataTableCellValue
	},
	props: {
		align: { type: String, default: 'left', validator: alignValidator },
		fieldName: { type: String, required: true },
		value: { type: Object, required: true }
	}
};
</script>
