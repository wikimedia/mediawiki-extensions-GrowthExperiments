<template>
	<div>
		<onboarding-dialog
			v-model:open="open"
			v-model:is-checked="isChecked"
			:initial-step="1"
			:total-steps="totalSteps"
			:stepper-label="stepperLabel"
			:close-button-text="closeBtnText"
			:start-button-text="startBtnText"
			:checkbox-label="checkboxLabel"
			@update:current-step="( newVal )=> currentStep = newVal"
		>
			<template #title>
				<div class="ext-growthExperiments-StructuredTaskOnboarding__title">
					<span>Before you start, test you skills.</span><br><strong>Spot overly positive words</strong>
				</div>
			</template>

			<!-- Outer container only, no quiz content -->
			<template #step1>
				<div class="demo-container">
					Onboarding content here. Step 1
				</div>
			</template>
			<template #step2>
				<div class="demo-container">
					Onboarding content here. Step 3
				</div>
			</template>
			<template #step3>
				<div class="demo-container">
					Onboarding content here. Step 3
				</div>
			</template>
		</onboarding-dialog>

		<!-- Simple control to reopen in dev after closing -->
		<div class="ext-growthExperiments-StructuredTaskOnboarding__controls">
			<button type="button" @click="open = true">
				Open onboarding
			</button>
		</div>
	</div>
</template>

<script>
const OnboardingDialog = require( '../../common/OnboardingDialog.vue' );
const { defineComponent, ref, computed } = require( 'vue' );

// @vue/component
module.exports = defineComponent( {
	name: 'CommonComponentsDemo',
	components: { OnboardingDialog },
	setup() {
		const open = ref( true );
		const isChecked = ref( false );
		const totalSteps = 3;
		const closeBtnText = 'I already know this';
		const startBtnText = 'Start editing';
		const checkboxLabel = "Don't show this again";
		const currentStep = ref( 1 );
		const stepperLabel = computed( () => `${ currentStep.value } of ${ totalSteps }` );

		return {
			open,
			isChecked,
			currentStep,
			totalSteps,
			closeBtnText,
			startBtnText,
			checkboxLabel,
			stepperLabel,
		};
	},
} );
</script>

<style lang="less">
.demo-container {
	border: 1px solid #f00;
}
</style>
