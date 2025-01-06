<template>
	<cdx-button
		class="ext-growthExperiments-PersonalizedPraise__praise_button"
		@click="onSkipButtonClicked"
	>
		{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee', mentee.userName ) }}
	</cdx-button>
	<cdx-dialog
		v-model:open="open"
		:title="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-header', menteeGender ).text()"
	>
		<p>
			{{ $i18n(
				'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-pretext',
				skipMenteesForDays
			) }}
		</p>

		<cdx-radio
			v-for="radio in reasonItems"
			:key="radio.value"
			v-model="selectedReason"
			name="reason"
			:input-value="radio.value"
		>
			{{ radio.label }}
		</cdx-radio>

		<div class="ext-growthExperiments-PersonalizedPraise-SkipMenteeDialog__buttons">
			<cdx-button
				@click="open = false"
			>
				{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-cancel' ) }}
			</cdx-button>
			<cdx-button
				weight="primary"
				action="progressive"
				@click="onSubmit"
			>
				{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-submit' ) }}
			</cdx-button>
		</div>
	</cdx-dialog>
</template>

<script>
const { ref, inject, computed } = require( 'vue' );
const { CdxButton, CdxDialog, CdxRadio } = require( '@wikimedia/codex' );
// NOTE: Keep in sync with ApiInvalidatePersonalizedPraiseSuggestion's skipreason param list
const SKIP_REASONS = [
	'already-praised',
	'not-praiseworthy',
	'not-now',
	'other'
];

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxButton,
		CdxDialog,
		CdxRadio
	},
	props: {
		mentee: { type: Object, required: true }
	},
	emits: [ 'skip' ],
	setup( props, { emit } ) {
		const open = ref( false );
		const selectedReason = ref( null );
		const skipMenteesForDays = Number( mw.config.get( 'GEPersonalizedPraiseSkipMenteesForDays' ) );
		const $i18n = inject( 'i18n' );
		const log = inject( '$log' );
		const menteeGender = computed( () => mw.config.get( 'GEMenteeGenders' )[ props.mentee.userId ] );
		const reasonItems = computed( () => SKIP_REASONS.map( ( x ) => ( {
			label: $i18n(
				// Giving grep a chance to find usages:
				// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-already-praised
				// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-not-praiseworthy
				// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-not-now
				// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-other
				'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-' + x,
				[ menteeGender.value ] ).text(),
			value: x
		} ) ) );

		function onSkipButtonClicked() {
			open.value = true;
		}
		function onSubmit() {
			open.value = false;
			emit( 'skip', selectedReason.value !== null ? selectedReason.value : 'other' );
		}

		return {
			open,
			selectedReason,
			skipMenteesForDays,
			log,
			onSkipButtonClicked,
			onSubmit,
			reasonItems,
			menteeGender
		};
	},
	watch: {
		open( val ) {
			this.log( val ? 'pp-skip-mentee' : 'pp-skip-mentee-close', {
				menteeUserId: this.mentee.userId
			} );
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-PersonalizedPraise-SkipMenteeDialog {
	&__buttons {
		display: flex;
		padding-top: 10px;

		button {
			width: 100%;
			margin-right: 5px;
		}
	}
}
</style>
