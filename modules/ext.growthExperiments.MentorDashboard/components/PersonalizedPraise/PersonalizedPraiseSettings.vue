<template>
	<div class="ext-growthExperiments-PersonalizedPraise-Settings">
		<cdx-button
			class="ext-growthExperiments-PersonalizedPraise-Settings__cogicon"
			weight="quiet"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-cog-icon-label' ).text()"
			@click="open = true"
		>
			<cdx-icon :icon="cdxIconSettings"></cdx-icon>
		</cdx-button>

		<cdx-dialog
			v-model:open="open"
			class="ext-growthExperiments-PersonalizedPraise-Settings__dialog"
			:title="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-title' ).text()"
			:close-button-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-settings-cancel' ).text()"
			:use-close-button="true"
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
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-PersonalizedPraise-Settings {
	&__cogicon {
		// HACK Since the module heading is rendered in the server,
		// aproximately align the "i" icon with the heading text
		// in the vertical axis and to the right hand padding.
		position: absolute;
		top: @spacing-50;
		right: @spacing-50;
	}

	&__dialog {
		gap: 0;
		// Overwrite: Codex CdxDialog does not support configuring the body styles. Avoid
		// extra top vertical gutter caused by skins default stles by removing top padding.
		/* stylelint-disable-next-line selector-class-pattern */
		.cdx-dialog__body {
			padding: 0 @spacing-150 @spacing-100;
		}
	}
}
</style>
