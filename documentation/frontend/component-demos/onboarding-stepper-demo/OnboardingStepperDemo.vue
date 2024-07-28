<template>
	<div class="ext-growthExperiments-OnboardingStepperDemo">
		<div class="ext-growthExperiments-OnboardingStepperDemo__container">
			<onboarding-stepper
				:model-value="modelValue"
				:total-steps="totalSteps"
				:label="`${modelValue} of ${totalSteps}`">
			</onboarding-stepper>

			<onboarding-stepper
				:model-value="modelValue"
				:total-steps="totalSteps"
			>
			</onboarding-stepper>
		</div>

		<p>totalSteps: </p>
		<cdx-select
			v-model:selected="totalSteps"
			:menu-items="numbers"
			default-label="total steps"
		>
		</cdx-select>
		<p>modelValue: </p>
		<cdx-select
			v-model:selected="modelValue"
			:menu-items="filteredNumbers"
			default-label="current step"
		>
		</cdx-select>
	</div>
</template>

<script>
import OnboardingStepper from '../../components/OnboardingStepper.vue';
import { CdxSelect } from '@wikimedia/codex';
import { ref, computed } from 'vue';

export default {

	name: 'OnboardingStepperDemo',

	components: {
		CdxSelect,
		OnboardingStepper
	},

	setup() {
		const modelValue = ref( 1 );
		const totalSteps = ref( 4 );

		const numbers = [
			{ value: 1 },
			{ value: 2 },
			{ value: 3 },
			{ value: 4 },
			{ value: 5 },
			{ value: 6 },
			{ value: 7 },
			{ value: 8 }
		];

		const filteredNumbers = computed( () => numbers.filter(
			( x ) => x.value <= totalSteps.value
		) );
		return {
			filteredNumbers,
			modelValue,
			numbers,
			totalSteps
		};
	}
};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

.ext-growthExperiments-OnboardingStepperDemo {
	&__container {
		border: @border-width-base @border-style-base;
		padding: @spacing-75;
		margin-top: @spacing-75;
		margin-bottom: @spacing-75;
		display: flex;
		justify-content: space-evenly;
		align-items: center;
	}
}

</style>
