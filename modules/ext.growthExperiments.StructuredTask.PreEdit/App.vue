<template>
	<div>
		<onboarding-dialog
			v-model:open="open"
			v-model:is-checked="isChecked"
			class="ext-growthExperiments-ReviseToneOnboarding"
			:initial-step="1"
			:total-steps="totalSteps"
			:stepper-label="stepperLabelText"
			:close-button-text="$i18n( 'growthexperiments-revisetone-onboarding-dialog-skip-label' ).text()"
			:start-button-text="$i18n( 'growthexperiments-structuredtask-onboarding-dialog-get-started-button' ).text()"
			:checkbox-label="$i18n( 'growthexperiments-structuredtask-onboarding-dialog-dismiss-checkbox' ).text()"
			@update:current-step="onStepChange"
			@close="reset"
		>
			<template #title>
				<div
					v-i18n-html:growthexperiments-revisetone-onboarding-dialog-title-label="[ userName ]"
					class="ext-growthExperiments-ReviseToneOnboarding__title"
				>
				</div>
			</template>

			<!-- Outer container only, no quiz content -->
			<template #step1>
				<quiz-game
					v-model:result="quizResults[0]"
					:data="quizData[0]"
					@update:result="( newVal ) => quizResults[0] = newVal"
				></quiz-game>
			</template>
			<template #step2>
				<quiz-game
					v-model:result="quizResults[1]"
					:data="quizData[1]"
					@update:result="( newVal ) => quizResults[1] = newVal"
				></quiz-game>
			</template>
			<template #step3>
				<quiz-game
					v-model:result="quizResults[2]"
					:data="quizData[2]"
					@update:result="( newVal ) => quizResults[2] = newVal"
				></quiz-game>
			</template>
		</onboarding-dialog>
	</div>
</template>

<script>

const OnboardingDialog = require( './common/OnboardingDialog.vue' );
const QuizGame = require( './revisetone/QuizGame.vue' );
// FIXME data should come from static variable, config?
const quizData = require( './revisetone/__mocks__/quizsData.json' );
const { computed, defineComponent, inject, ref, reactive } = require( 'vue' );

// @vue/component
module.exports = defineComponent( {
	name: 'ReviseToneOnboarding',
	components: { OnboardingDialog, QuizGame },
	props: {
		prefName: {
			type: [ String, null ],
			default: null,
		},
	},
	setup( props ) {
		const i18n = inject( 'i18n' );
		const Api = inject( 'mw.Api' );
		const mwUser = inject( 'mw.user' );
		const mwHook = inject( 'mw.hook' );
		const open = ref( true );
		const isChecked = ref( false );
		const quizResults = reactive( [] );
		const totalSteps = 3;
		const currentStep = ref( 1 );
		const stepperLabelText = computed(
			() => i18n( 'growthexperiments-structuredtask-onboarding-dialog-progress', currentStep.value, totalSteps ).text(),
		);
		const onStepChange = ( newVal ) => {
			currentStep.value = newVal;
		};
		const saveDismissed = () => new Api().saveOption( props.prefName, isChecked.value ? '1' : '0' );
		const reset = () => {
			// Reset each game result in the rare case the dialog is re-opened
			quizResults.splice( 0 );
			// Store the checkbox value
			saveDismissed();
			// Fire the structured task
			mwHook( 'growthExperiments.structuredTask.onboardingCompleted' ).fire();
		};
		return {
			isChecked,
			onStepChange,
			open,
			quizData,
			quizResults,
			reset,
			stepperLabelText,
			totalSteps,
			userName: mwUser.getName(),
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-ReviseToneOnboarding {
  // Force vertical stacking of elements in the title label
  &__title > * {
    display: block;
  }
}
</style>
