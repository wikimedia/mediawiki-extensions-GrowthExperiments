<template>
	<form
		id="personalized-praise-settings-form"
		@submit.prevent="onSettingsUpdate"
	>
		<h3 class="no-gutter">
			{{ $i18n(
				'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-headline'
			).text() }}
		</h3>
		<section class="ext-growthExperiments-PersonalizedPraiseSettings__form_group">
			<label>
				{{ $i18n(
					'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-edits-within-timeframe'
				).text() }}
			</label>

			<div class="ext-growthExperiments-PersonalizedPraiseSettings__field_flexbox">
				<c-number-input
					v-model="settingsData.minEdits"
					class="ext-growthExperiments-PersonalizedPraiseSettings__input"
					min="0"
					step="1"
				></c-number-input>
				<cdx-select
					v-model:selected="settingsData.days"
					class="ext-growthExperiments-PersonalizedPraiseSettings__input"
					:menu-items="timeframeItems()"
				></cdx-select>
			</div>
		</section>

		<h3 class="no-gutter">
			{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-positive-message-headline' ).text() }}
		</h3>
		<section class="ext-growthExperiments-PersonalizedPraiseSettings__form_group">
			<cdx-text-input
				v-model="settingsData.messageSubject"
				class="ext-growthExperiments-PersonalizedPraiseSettings__input"
			></cdx-text-input>
			<textarea
				v-model="settingsData.messageText"
				class="
					ext-growthExperiments-PersonalizedPraiseSettings__input
					ext-growthExperiments-PersonalizedPraiseSettings__message_text
				"
				rows="6"
			></textarea>
			<c-text color="subtle" class="ext-growthExperiments-PersonalizedPraiseSettings__help_text">
				{{ $i18n(
					'growthexperiments-mentor-dashboard-personalized-praise-settings-positive-message-help-text'
				).text() }}
			</c-text>
		</section>

		<div v-if="areNotificationsEnabled">
			<h3>
				{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-headline' ).text() }}
			</h3>
			<section class="ext-growthExperiments-PersonalizedPraiseSettings__form_group">
				<label>
					{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-pretext' ).text() }}
				</label>
				<cdx-select
					v-model:selected="settingsData.notificationFrequency"
					class="ext-growthExperiments-PersonalizedPraiseSettings__input"
					:menu-items="notificationFrequencyItems()"
				></cdx-select>
			</section>
		</div>

		<cdx-button
			class="ext-growthExperiments-PersonalizedPraiseSettings__button"
			form="personalized-praise-settings-form"
			weight="primary"
			action="progressive"
		>
			{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-save' ).text() }}
		</cdx-button>
		<cdx-button
			class="ext-growthExperiments-PersonalizedPraiseSettings__button"
			type="button"
			@click="$emit( 'close' )"
		>
			{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-cancel' ).text() }}
		</cdx-button>
	</form>
</template>

<script>
const { CdxSelect, CdxTextInput, CdxButton } = require( '@wikimedia/codex' );
const CNumberInput = require( '../CNumberInput/CNumberInput.vue' );
const CText = require( '../../../vue-components/CText.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxSelect,
		CdxTextInput,
		CdxButton,
		CNumberInput,
		CText
	},
	props: {
		minEdits: { type: Number, default: undefined },
		days: { type: Number, default: undefined },
		messageSubject: { type: String, default: undefined },
		messageText: { type: String, default: undefined },
		notificationFrequency: { type: Number, default: undefined }
	},
	emits: [ 'update:settings', 'close' ],
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
	computed: {
		areNotificationsEnabled() {
			return mw.config.get( 'GEPersonalizedPraiseNotificationsEnabled' );
		}
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
					).text(),
					value: 2
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-timeframe-week'
					).text(),
					value: 7
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-timeframe-2-weeks'
					).text(),
					value: 14
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-praiseworthy-metric-timeframe-month'
					).text(),
					value: 30
				}
			];
		},
		notificationFrequencyItems() {
			return [
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-immediately'
					).text(),
					value: 0
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-daily'
					).text(),
					value: 24
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-weekly'
					).text(),
					value: 168
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-monthly'
					).text(),
					value: 720
				},
				{
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-personalized-praise-settings-notifications-never'
					).text(),
					value: -1
				}
			];
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-PersonalizedPraiseSettings {
	&__input {
		width: 100%;
	}

	&__message_text {
		font-family: inherit;
		font-size: inherit;
		padding: 8px;
		resize: vertical;
	}

	&__field_flexbox {
		display: flex;
	}

	&__button {
		margin-top: 10px;
		width: 100%;
		max-width: inherit;
	}
}
</style>
