<template>
	<div :class="'ext-growthExperiments-DataTableCellValue--align-' + align">
		<slot>{{ value }}</slot>
	</div>
</template>

<script>
const alignValidator = require( '../../validators/align-text.validator.js' );
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	props: {
		align: {
			type: String,
			default: 'left',
			validator: alignValidator
		},
		value: { type: [ String, Number, Boolean ], default: null }
	}
};
</script>

<style lang="less">
.ext-growthExperiments-DataTableCellValue {
	&--align {
		&-left {
			text-align: left;
		}

		&-right {
			text-align: right;
		}

		&-center {
			text-align: center;
		}
	}
}
</style>
