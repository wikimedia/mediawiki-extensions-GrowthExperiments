<template>
	<div class="ext-growthExperiments-PersonalizedPraise-Settings">
		<cdx-button
			class="ext-growthExperiments-PersonalizedPraise-Settings__cogicon"
			type="quiet"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-cog-icon-label' )"
			@click="open = true"
		>
			<cdx-icon :icon="cdxIconSettings"></cdx-icon>
		</cdx-button>

		<cdx-dialog
			v-model:open="open"
			:title="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-title' )"
		>
			<personalized-praise-settings-form
				v-bind="settingsData"
				@update:settings="onSettingsUpdate"
			></personalized-praise-settings-form>
		</cdx-dialog>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxIcon, CdxButton, CdxDialog } = require( '@wikimedia/codex' );
const { cdxIconSettings } = require( '../../../vue-components/icons.json' );
const PersonalizedPraiseSettingsForm = require( './PersonalizedPraiseSettingsForm.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon,
		CdxDialog,
		PersonalizedPraiseSettingsForm
	},
	props: {
		settingsData: { type: Object, required: true }
	},
	emits: [ 'update:settings' ],
	setup() {
		const open = ref( false );

		return {
			open,
			cdxIconSettings
		};
	},
	methods: {
		onSettingsUpdate( $event ) {
			this.$emit( 'update:settings', $event );
			this.open = false;
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-PersonalizedPraise-Settings {
	&__cogicon {
		position: absolute;
		top: 16px;
		right: 0;
	}
}
</style>
