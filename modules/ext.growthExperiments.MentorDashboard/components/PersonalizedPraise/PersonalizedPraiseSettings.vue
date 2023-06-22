<template>
	<div class="ext-growthExperiments-PersonalizedPraise-Settings">
		<cdx-button
			class="ext-growthExperiments-PersonalizedPraise-Settings__cogicon"
			weight="quiet"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-cog-icon-label' )"
			@click="open = true"
		>
			<cdx-icon :icon="cdxIconSettings"></cdx-icon>
		</cdx-button>

		<cdx-dialog
			v-model:open="open"
			class="ext-growthExperiments-PersonalizedPraise-Settings__dialog"
			:title="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-title' )"
			:close-button-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-cancel' )"
		>
			<personalized-praise-settings-form
				v-bind="settingsData"
				@close="open = false"
				@update:settings="onSettingsUpdate"
			></personalized-praise-settings-form>
		</cdx-dialog>
	</div>
</template>

<script>
const { ref, inject } = require( 'vue' );
const { CdxIcon, CdxButton, CdxDialog } = require( '@wikimedia/codex' );
const { cdxIconSettings } = require( '../../../vue-components/icons.json' );
const PersonalizedPraiseSettingsForm = require( './PersonalizedPraiseSettingsForm.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
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
		const log = inject( '$log' );

		return {
			open,
			log,
			cdxIconSettings
		};
	},
	methods: {
		onSettingsUpdate( $event ) {
			this.$emit( 'update:settings', $event );
			this.open = false;
			mw.notify(
				this.$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-success' ),
				{ type: 'success' }
			);
			this.log( 'pp-settings-saved', $event );
		}
	},
	watch: {
		open( val ) {
			this.log( val ? 'pp-settings-impression' : 'pp-settings-closed' );
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

	&__dialog {
		gap: 0;
	}
}
</style>
