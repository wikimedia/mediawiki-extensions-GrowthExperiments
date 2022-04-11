<template>
	<div :class="'data-table-cell-value--align-' + align">
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
.data-table-cell-value {
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
