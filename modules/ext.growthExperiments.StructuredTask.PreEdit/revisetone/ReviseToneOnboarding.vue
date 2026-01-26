<template>
	<onboarding-dialog
		v-model:open="open"
		class="ext-growthExperiments-ReviseToneOnboarding"
		:initial-step="1"
		:total-steps="totalSteps"
		:stepper-label="stepperLabelText"
		:close-button-text="$i18n( 'growthexperiments-revisetone-onboarding-dialog-skip-label' ).text()"
		:start-button-text="$i18n( 'growthexperiments-structuredtask-onboarding-dialog-get-started-button' ).text()"
		:disable-touch-navigation="true"
		@update:current-step="onStepChange"
		@close="reset"
	>
		<template #title>
			<div
				class="ext-growthExperiments-ReviseToneOnboarding__title"
			>
				<span>{{ $i18n( 'growthexperiments-revisetone-onboarding-introduction' ).text() }}</span>
				<b>
					{{ renderedInstructions }}
				</b>
			</div>
		</template>

		<template
			v-for="( quiz, index ) in quizData"
			#[`step${index+1}`]
			:key="`step${index + 1}`"
		>
			<quiz-game
				v-model:result="quizResults[index]"
				:data="quiz"
				@update:result="logQuizResponse"
			></quiz-game>
		</template>
	</onboarding-dialog>
</template>

<script>
const OnboardingDialog = require( '../common/OnboardingDialog.vue' );
const QuizGame = require( './QuizGame.vue' );
const quizData = require( './quizData.json' );
const { computed, defineComponent, inject, ref, reactive } = require( 'vue' );
const normalizeResults = ( results ) => {
	const getOptionsMap = ( options ) => options.reduce( ( acc, curr ) => {
		acc[ curr.label ] = curr.correct || false;
		return acc;
	}, {} );
	return results.reduce( ( acc, curr, index ) => {
		const optionsMap = getOptionsMap( quizData[ index ].options );
		acc.push( optionsMap[ curr ] );
		return acc;
	}, [] )
	// REVIEW format is 'correct,unanswered,incorrect,correct,unanswered', we could do counters instead
		.map( ( v ) => v ? 'correct' : typeof v === 'boolean' ? 'incorrect' : 'unanswered' ).toString();
};

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
		const mwHook = inject( 'mw.hook' );
		const mwTrack = inject( 'mw.track' );
		const mwLanguage = inject( 'mw.language' );
		const experiment = inject( 'experiment' );
		const open = ref( true );
		// null stands for an unanswered quiz at the array's index
		const quizResults = reactive( Array( quizData.length ).fill( null ) );
		const totalSteps = quizData.length;
		const currentStep = ref( 1 );
		const stepperLabelText = computed(
			() => i18n( 'growthexperiments-structuredtask-onboarding-dialog-progress', currentStep.value, totalSteps ).text(),
		);
		if ( experiment ) {
			experiment.send( 'impression', {
				/* eslint-disable camelcase */
				instrument_name: 'Revise tone onboarding dialog impression',
				action_source: `Quiz-step-${ currentStep.value }`,
			} );
		}
		const onStepChange = ( newVal ) => {
			currentStep.value = newVal;
		};
		const renderedInstructions = computed(
			() => i18n( quizData[ currentStep.value - 1 ].instruction ).text(),
		);
		const saveDismissed = () => {
			if ( !props.prefName ) {
				return;
			}
			// Mark onboarding as seen for this user so it is not shown again.
			new Api().saveOption( props.prefName, '1' );
		};
		const reset = ( closeEventData ) => {
			if ( experiment ) {
				experiment.send( 'click', {
					/* eslint-disable camelcase */
					instrument_name: 'Revise tone onboarding dialog end click',
					action_subtype: ( {
						primary: 'get-started',
						quiet: 'skip',
						unknown: 'dismiss',
					} )[ closeEventData.closeSource ],
					action_source: `Quiz-step-${ currentStep.value }`,
					action_context: normalizeResults( quizResults ),
					/* eslint-enable camelcase */
				} );
			}
			// Reset each game result in the rare case the dialog is re-opened
			quizResults.forEach( ( _, index, arr ) => {
				arr[ index ] = null;
			} );
			saveDismissed();
			// Fire the structured task
			mwHook( 'growthExperiments.structuredTask.onboardingCompleted' ).fire();
		};

		const logQuizResponse = ( responseMessageKey ) => {
			const selectedResponseData = quizData[ currentStep.value - 1 ].options.find(
				( option ) => option.label === responseMessageKey,
			);
			if ( selectedResponseData === undefined ) {
				return;
			}
			const isCorrect = !!selectedResponseData.correct;
			mwTrack( 'stats.mediawiki_GrowthExperiments_revise_tone_onboarding_quiz_response_total', 1, {
				outcome: isCorrect ? 'correct' : 'incorrect',
				step: currentStep.value,
				language: mwLanguage.getFallbackLanguageChain().shift(),
			} );
		};
		return {
			onStepChange,
			renderedInstructions,
			open,
			quizData,
			quizResults,
			reset,
			stepperLabelText,
			totalSteps,
			logQuizResponse,
		};
	},
} );

</script>

<style lang="less">
.ext-growthExperiments-ReviseToneOnboarding {
	// Force vertical stacking of elements in the title label
	&__title > * {
		display: block;
	}
}
</style>
