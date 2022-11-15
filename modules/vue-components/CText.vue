<template>
	<div></div>
</template>

<script>
const { h, inject } = require( 'vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	props: {
		as: {
			type: String,
			default: 'div'
		},
		size: {
			type: [ String, Array ],
			default: null
		},
		color: {
			type: String,
			default: null
		},
		weight: {
			type: String,
			default: null
		},
		/**
		 * An array of mode names. The index of each mode
		 * will be used to match the given sizes in prop size.
		 *
		 * e.g: size: [,,'xxl'] will display base font for
		 * desktop and overlay but xxl font in 'overlay-summary'
		 */
		breakpoints: {
			type: Array,
			default: () => ( [
				'desktop',
				'overlay',
				'overlay-summary'
			] )
		}
	},
	render() {
		const extraClasses = [];
		// FIXME if we want to keep CText reusable across apps
		// the mode should be passed as a prop.
		const mode = inject( 'RENDER_MODE' );
		if ( typeof this.size === 'string' ) {
			extraClasses.push( `ext-growthExperiments-CText--size-${this.size}` );
		}
		if ( Array.isArray( this.size ) ) {
			const breakpointIndex = this.breakpoints.indexOf( mode );
			const relevantSize = this.size[ breakpointIndex ];
			// If we can't find an specified size for the mode don't add any
			// class so we use base font
			if ( relevantSize ) {
				extraClasses.push( `ext-growthExperiments-CText--size-${relevantSize}` );
			}
		}
		if ( this.color ) {
			extraClasses.push( `ext-growthExperiments-CText--color-${this.color}` );
		}
		if ( this.weight ) {
			extraClasses.push( `ext-growthExperiments-CText--weight-${this.weight}` );
		}

		return h( this.as, {
			class: [ 'ext-growthExperiments-CText' ].concat( extraClasses )
		}, this.$slots.default ? this.$slots.default() : null );
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
