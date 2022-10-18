<template>
	<div></div>
</template>

<script>
const { h } = require( 'vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	props: {
		as: {
			type: String,
			default: 'div'
		},
		size: {
			type: String,
			default: null
		},
		color: {
			type: String,
			default: null
		},
		weight: {
			type: String,
			default: null
		}
	},
	render() {
		const extraClasses = [];
		if ( this.size ) {
			extraClasses.push( `ext-growthExperiments-CText--size-${this.size}` );
		}
		if ( this.color ) {
			extraClasses.push( `ext-growthExperiments-CText--color-${this.color}` );
		}
		if ( this.weight ) {
			extraClasses.push( `ext-growthExperiments-CText--weight-${this.weight}` );
		}
		return h( this.as, {
			class: [ 'ext-growthExperiments-CText' ].concat( extraClasses )
		}, this.$slots.default() );
	}
};
</script>

<style lang="less">
@import './variables.less';
// TODO match WIP line height spec from Codex https://www.figma.com/file/X8pKlndyPaqZg4I3GubQs6/Typography

.ext-growthExperiments-CText {
	font-size: @font-size-100;
	font-weight: @font-weight-normal;

	&--size-xs {
		font-size: @font-size-80;
	}

	&--size-sm {
		font-size: @font-size-90;
	}

	&--size-md {
		font-size: @font-size-110;
	}

	&--size-lg {
		font-size: @font-size-125;
	}

	&--size-xl {
		font-size: @font-size-150;
	}

	&--size-xxl {
		font-size: @font-size-175;
	}

	&--color-subtle {
		color: @color-subtle;
	}

	&--color-placeholder {
		color: @color-placeholder;
	}

	&--weight-hairline {
		font-weight: @font-weight-hairline;
	}

	&--weight-light {
		font-weight: @font-weight-light;
	}

	&--weight-semi-bold {
		font-weight: @font-weight-semi-bold;
	}

	&--weight-bold {
		font-weight: @font-weight-bold;
	}
}
</style>
