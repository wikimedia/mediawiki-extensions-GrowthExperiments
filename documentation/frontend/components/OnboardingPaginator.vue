<template>
	<div class="ext-growthExperiments-OnboardingPaginator">
		<div class="ext-growthExperiments-OnboardingPaginator__dots">
			<div
				v-for="step in [ ...Array( totalSteps ).keys() ]"
				:key="step"
				class="ext-growthExperiments-OnboardingPaginator__dots__dot"
				:class="
					{ 'ext-growthExperiments-OnboardingPaginator__dots__dot--active':
						step < currentStep }"
			></div>
		</div>

		<p>{{ currentStep }} of {{ totalSteps }}</p>
	</div>
</template>

<script>
export default {
	name: 'OnboardingPaginator',
	props: {

		totalSteps: { type: Number, default: 0 },

		currentStep: { type: Number, default: 0 }
	},
	setup() {}
};
</script>

<style lang="less">
	@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

	.ext-growthExperiments-OnboardingPaginator {
		display: flex;
		font-size: @font-size-x-small;
		color: @color-subtle;

		&__dots {
			display: flex;
			align-items: center;

			&__dot {
				background-color: @color-emphasized;
				background-color: @background-color-disabled;
				border-radius: @border-radius-circle;
				width: 8px;
				height: 8px;
				margin-right: @spacing-50;

				&--active {
					background-color: @color-base;
				}
			}
		}
	}
</style>
