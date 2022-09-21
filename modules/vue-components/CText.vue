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
// TODO match font size tokens in Figma against standard WM tokens
.ext-growthExperiments-CText {
	font-size: 1em;

	&--size-x-small {
		font-size: 0.7em;
	}

	&--size-small {
		font-size: 0.9em;
	}

	&--size-medium {
		font-size: 0.94em;
	}

	&--size-large {
		font-size: 1.1em;
	}

	&--size-x-large {
		font-size: 2em;
	}

	&--color-subtle {
		color: @color-subtle;
	}

	&--weight-hairline {
		font-weight: @font-weight-hairline;
	}

	&--weight-light {
		font-weight: @font-weight-light;
	}

	&--weight-normal {
		font-weight: @font-weight-normal;
	}

	&--weight-semi-bold {
		font-weight: @font-weight-semi-bold;
	}

	&--weight-bold {
		font-weight: @font-weight-bold;
	}
}
</style>
