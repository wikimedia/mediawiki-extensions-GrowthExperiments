<template>
	<cdx-button
		class="ext-growthExperiments-PersonalizedPraise__praise_button"
		@click="onSkipButtonClicked"
	>
		{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee', menteeUserName ) }}
	</cdx-button>
	<cdx-dialog
		v-model:open="open"
		:title="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-header' ).text()"
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
const { ref, inject } = require( 'vue' );
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
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxDialog,
		CdxRadio
	},
	props: {
		menteeUserName: { type: String, required: true }
	},
	emits: [ 'skip' ],
	setup( _props, { emit } ) {
		const open = ref( false );
		const selectedReason = ref( null );
		const skipMenteesForDays = Number( mw.config.get( 'GEPersonalizedPraiseSkipMenteesForDays' ) );
		const $i18n = inject( 'i18n' );
		const reasonItems = SKIP_REASONS.map( ( x ) => {
			return {
				label: $i18n(
					// Giving grep a chance to find usages:
					// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-already-praised
					// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-not-praiseworthy
					// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-not-now
					// * growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-other
					'growthexperiments-mentor-dashboard-personalized-praise-skip-mentee-reason-' + x
				),
				value: x
			};
		} );

		function onSkipButtonClicked() {
			open.value = true;
		}
		function onSubmit() {
			open.value = false;
			emit( 'skip', selectedReason.value !== null ? selectedReason.value : 'other' );
		}

		return {
			open,
			reasonItems,
			selectedReason,
			skipMenteesForDays,
			onSkipButtonClicked,
			onSubmit
		};
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
