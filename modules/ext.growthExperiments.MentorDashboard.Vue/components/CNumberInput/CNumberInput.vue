<template>
	<div
		class="ext-growthExperiments-NumberInput"
		:class="rootClasses"
		:style="rootStyle"
	>
		<input
			ref="input"
			v-model="wrappedModel"
			class="ext-growthExperiments-NumberInput__input"
			:class="inputClasses"
			v-bind="otherAttrs"
			type="number"
			:disabled="disabled"
			@input="onInput"
			@change="onChange"
			@focus="onFocus"
			@blur="onBlur"
		>
	</div>
</template>

<script>
const { toRef, computed } = require( 'vue' );
const { useModelWrapper, useSplitAttributes } = require( '@wikimedia/codex' );

/**
 * HTML `<input>` element with type "number" wrapped in a `<div>`.
 *
 * `v-model` is used to track the current value of the input. See the events docs for details on
 * emitted events and their properties.
 */

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	name: 'CNumberInput',
	/**
	 * We want the input to inherit attributes, not the root element.
	 */
	inheritAttrs: false,
	expose: [ 'focus' ],
	props: {
		/**
		 * Current value of the input.
		 *
		 * Provided by `v-model` binding in the parent component.
		 */
		/* eslint-disable-next-line vue/no-unused-properties */
		modelValue: {
			type: Number,
			default: null
		},
		/**
		 * Whether the input is disabled.
		 */
		disabled: {
			type: Boolean,
			default: false
		}
	},
	emits: [
		/**
		 * When the input value changes
		 *
		 * @property {number} modelValue The new model value
		 */
		'update:modelValue',
		/**
		 * When the input value changes via direct use of the input
		 *
		 * @property {InputEvent} event
		 */
		'input',
		/**
		 * When an input value change is committed by the user (e.g. on blur)
		 *
		 * @property {Event} event
		 */
		'change',
		/**
		 * When the input comes into focus
		 *
		 * @property {FocusEvent} event
		 */
		'focus',
		/**
		 * When the input loses focus
		 *
		 * @property {FocusEvent} event
		 */
		'blur'
	],
	setup( props, { emit, attrs } ) {
		// Take the modelValue provided by the parent component via v-model and
		// generate a wrapped model that we can use for the input element in
		// this component.
		const wrappedModel = useModelWrapper( toRef( props, 'modelValue' ), emit );

		const internalClasses = {};
		// Get helpers from useSplitAttributes.
		const {
			rootClasses,
			rootStyle,
			otherAttrs
		} = useSplitAttributes( attrs, internalClasses );
		const inputClasses = computed( () => {
			return {
				'ext-growthExperiments-NumberInput__input--has-value': !!wrappedModel.value
			};
		} );

		// Emit other events to the parent in case they're needed.
		const onInput = ( event ) => {
			emit( 'input', event );
		};
		const onChange = ( event ) => {
			emit( 'change', event );
		};
		const onFocus = ( event ) => {
			emit( 'focus', event );
		};
		const onBlur = ( event ) => {
			emit( 'blur', event );
		};
		return {
			wrappedModel,
			rootClasses,
			rootStyle,
			otherAttrs,
			inputClasses,
			onInput,
			onChange,
			onFocus,
			onBlur
		};
	},
	methods: {
		/**
		 * Focus the component's input element.
		 *
		 * @public
		 */
		focus() {
			this.$refs.input.focus();
		}
	}
};
</script>

<style lang="less">
@import '../../../lib/wikimedia-ui-base/wikimedia-ui-base.less';
@import '../variables.less';
// TODO: these should be design tokens.
@font-size-browser: 16;
@font-size-base: 14 / @font-size-browser;
@line-height-component: unit( ( 20 / @font-size-browser / @font-size-base ), em );

.ext-growthExperiments-NumberInput {
	position: relative;
	box-sizing: @box-sizing-base;
	// TODO: hide the step arrows based on prop
	/* Chrome, Safari, Edge, Opera */
	input::-webkit-outer-spin-button,
	input::-webkit-inner-spin-button {
		-webkit-appearance: none;
		margin: 0;
	}
	/* Firefox */
	input[ type='number' ] {
		-moz-appearance: textfield;
	}
}

.ext-growthExperiments-NumberInput__input {
	display: block;
	box-sizing: @box-sizing-base;
	min-height: @min-size-base;
	width: @size-full;
	margin: 0;
	border-width: @border-width-base;
	border-style: @border-style-base;
	border-radius: @border-radius-base;
	padding: @padding-input-text;
	font-family: inherit;
	font-size: inherit;
	line-height: @line-height-component;

	&:enabled {
		background-color: @background-color-base;
		color: @color-base;
		border-color: @border-color-base;
		box-shadow: @box-shadow-inset-small @box-shadow-color-transparent;
		transition-property: @transition-property-base;
		transition-duration: @transition-duration-medium;

		~ .ext-growthExperiments-NumberInput__icon {
			color: @color-placeholder;
		}

		&:focus,
		&.ext-growthExperiments-NumberInput__input--has-value {
			~ .ext-growthExperiments-NumberInput__icon {
				color: @color-base;
			}
		}

		&:hover {
			border-color: @border-color-input--hover;
		}

		&:focus {
			border-color: @border-color-base--focus;
			box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;
			outline: @outline-base--focus;
		}

		&:invalid {
			border-color: @border-color-destructive;
			box-shadow: @box-shadow-inset-small @box-shadow-destructive--focus;
			outline: @outline-base--focus;
		}
	}
	/* stylelint-disable-next-line no-descending-specificity */
	&:disabled {
		background-color: @background-color-base--disabled;
		color: @color-base--disabled;
		-webkit-text-fill-color: @color-base--disabled;
		border-color: @border-color-base--disabled;
		// Don't implement coined effect on text-shadow from OOUI.
		// This has never gone through design review and was a hack to increase
		// color contrast.
		// text-shadow: @text-shadow-base--disabled;
		/* stylelint-disable-next-line no-descending-specificity */
		~ .ext-growthExperiments-NumberInput__icon {
			color: @color-base--disabled;
			pointer-events: none;
		}
	}
	// Normalize placeholder styling, see T139034.
	&::placeholder {
		color: @color-placeholder;
		opacity: @opacity-base;
	}
}
</style>
