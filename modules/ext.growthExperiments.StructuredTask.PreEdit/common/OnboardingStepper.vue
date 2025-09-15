<template>
	<span class="ext-growthExperiments-OnboardingStepper">
		<span v-if="label" class="ext-growthExperiments-OnboardingStepper__label">
			{{ label }}
		</span>

		<span v-if="totalSteps <= 3" class="ext-growthExperiments-OnboardingStepper__dots">
			<span
				v-for="step in [ ...Array( totalSteps ).keys() ]"
				:key="step"
				class="ext-growthExperiments-OnboardingStepper__dots__dot"
				:class="
					{ 'ext-growthExperiments-OnboardingStepper__dots__dot--active':
						step < modelValue }"
			></span>
		</span>
	</span>
</template>

<script>
const { defineComponent } = require( 'vue' );

// @vue/component
module.exports = exports = defineComponent( {
	name: 'OnboardingStepper',
	props: {
		/**
		 * The current step
		 */
		modelValue: { type: Number, default: 0 },

		/**
		 * the total number of steps
		 */
		totalSteps: { type: Number, default: 0 },

		/**
		 * textual representation of the steps
		 */
		label: { type: String, default: '' },
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-OnboardingStepper {
	display: inline-flex;
	font-size: @font-size-small;
	color: @color-subtle;
	align-items: center;

	&__label {
		margin-inline-end: @spacing-50;
	}

	&__dots {
		display: flex;
		align-items: center;
		height: @size-125;

		&__dot {
			background-color: @background-color-disabled;
			border-radius: @border-radius-circle;
			width: @size-75;
			height: @size-75;
			margin-inline-end: @spacing-50;

			&--active {
				background-color: @color-base;
			}
		}
	}
}
</style>
