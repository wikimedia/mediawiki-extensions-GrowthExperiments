<template>
	<data-table-cell-value :align="align">
		<a :href="value.link.href" :alt="value.link.text">
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
	components: {
		DataTableCellValue
	},
	props: {
		align: { type: String, default: 'left', validator: alignValidator },
		value: { type: Object, required: true }
	}
};
</script>
