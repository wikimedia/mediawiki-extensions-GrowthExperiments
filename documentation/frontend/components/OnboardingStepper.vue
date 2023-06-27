<template>
	<div class="ext-growthExperiments-OnboardingStepper">
		<span v-if="label" class="ext-growthExperiments-OnboardingStepper__label">
			{{ label }}
		</span>

		<div class="ext-growthExperiments-OnboardingStepper__dots">
			<div
				v-for="step in [ ...Array( totalSteps ).keys() ]"
				:key="step"
				class="ext-growthExperiments-OnboardingStepper__dots__dot"
				:class="
					{ 'ext-growthExperiments-OnboardingStepper__dots__dot--active':
						step < modelValue }"
			></div>
		</div>
	</div>
</template>

<script>
export default {
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
		label: { type: String, default: '' }
	}
};
</script>

<style lang="less">
	@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

	.ext-growthExperiments-OnboardingStepper {
		display: flex;
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
