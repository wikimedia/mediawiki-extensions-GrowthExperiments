<template>
	<form
		id="personalized-praise-settings-form"
		@submit.prevent="onSettingsUpdate"
	>
		<h3 class="no-gutter">
			{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-headline' ) }}
		</h3>
		<section class="ext-growthExperiments-PersonalizedPraiseSettings__form_group">
			<label>
				{{ $i18n(
					'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-edits-within-timeframe'
				) }}
			</label>
			<c-number-input
				v-model="settingsData.minEdits"
				min="0"
				step="1"
			></c-number-input>

			<cdx-select
				v-model:selected="settingsData.days"
				:menu-items="timeframeItems()"
			></cdx-select>
		</section>

		<h3 class="no-gutter">
			{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-positive-message-headline' ) }}
		</h3>
		<section class="ext-growthExperiments-PersonalizedPraiseSettings__form_group">
			<cdx-text-input
				v-model="settingsData.messageSubject"
			></cdx-text-input>
			<cdx-text-input
				v-model="settingsData.messageText"
			></cdx-text-input>
		</section>

		<h3>
			{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-headline' ) }}
		</h3>
		<section class="ext-growthExperiments-PersonalizedPraiseSettings__form_group">
			<label>
				{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-pretext' ) }}
			</label>
			<cdx-select
				v-model:selected="settingsData.notificationFrequency"
				:menu-items="notificationFrequencyItems()"
			></cdx-select>
		</section>

		<cdx-button
			form="personalized-praise-settings-form"
		>
			Submit
		</cdx-button>
	</form>
</template>

<script>
const { CdxSelect, CdxTextInput, CdxButton } = require( '@wikimedia/codex' );
const CNumberInput = require( '../CNumberInput/CNumberInput.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxSelect,
		CdxTextInput,
		CdxButton,
		CNumberInput
	},
	props: {
		minEdits: { type: Number, default: undefined },
		days: { type: Number, default: undefined },
		messageSubject: { type: String, default: undefined },
		messageText: { type: String, default: undefined },
		notificationFrequency: { type: Number, default: undefined }
	},
	emits: [ 'update:settings' ],
	data() {
		return {
			settingsData: {
				minEdits: this.minEdits,
				days: this.days,
				messageSubject: this.messageSubject,
				messageText: this.messageText,
				notificationFrequency: this.notificationFrequency
			}
		};
	},
	methods: {
		onSettingsUpdate() {
			this.$emit( 'update:settings', this.$data.settingsData );
		},
		timeframeItems() {
			return [
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-timeframe-48-hours'
					),
					value: 2
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-timeframe-week'
					),
					value: 7
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-timeframe-2-weeks'
					),
					value: 15
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-timeframe-month'
					),
					value: 30
				}
			];
		},
		notificationFrequencyItems() {
			return [
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-immediately'
					),
					value: 0
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-daily'
					),
					value: 24
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-weekly'
					),
					value: 168
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-monthly'
					),
					value: 720
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-never'
					),
					value: -1
				}
			];
		}
	}
};
</script>
