<template>
	<cdx-dialog
		v-model:open="open"
		:use-close-button="true"
		:title="$i18n( 'growthexperiments-interest-selector-dialog-title' ).text()"
		class="ext-growthExperiments-reading-recommendations-interest-selector-dialog"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		:fixed-height="isMobile"
		@primary="onSave"
		@default="onCancel"
		@update:open="onUpdateOpen"
	>
		<interest-selector
			v-model:chips="chips"
		></interest-selector>
	</cdx-dialog>
</template>

<script>
const { defineComponent, ref, computed } = require( 'vue' );
const { CdxDialog } = require( './codex.js' );
const InterestSelector = require( './InterestSelector.vue' );

// @vue/component
module.exports = defineComponent( {
	name: 'InterestSelectorDialog',
	components: {
		InterestSelector,
		CdxDialog,
	},
	props: {
		onDismiss: {
			type: Function,
			required: true,
		},
	},
	setup( props ) {
		const open = ref( true );
		const primaryAction = computed( () => ( {
			label: mw.msg( 'growthexperiments-interest-selector-dialog-save-button-label' ),
			actionType: 'progressive',
		} ) );
		const defaultAction = computed( () => ( {
			label: mw.msg( 'growthexperiments-interest-selector-dialog-cancel-button-label' ),
		} ) );

		const api = new mw.Api();
		const storedInterestArticles = mw.config.get( 'wgGEInterestArticles', [] );

		const chips = ref( storedInterestArticles.map( ( articleName ) => ( { label: articleName, value: articleName } ) ) );

		async function saveInterestArticles() {
			const pageNames = chips.value.map( ( chip ) => chip.value );
			return api.saveOption( 'growthexperiments-interest-articles-editing', JSON.stringify( pageNames ) );
		}

		function onSave() {
			saveInterestArticles().then( () => {
				open.value = false;
				props.onDismiss();

				// Refresh the page and therefore the recommendations.
				window.location.reload();
			} ).catch( ( error ) => {
				mw.errorLogger.logError(
					new Error( 'Unable to save selected interests: ' + error ),
					'error.growthexperiments',
				);
			} );
		}

		function onCancel() {
			open.value = false;
			props.onDismiss();
		}

		/**
		 * Handle dialog dismissal.
		 *
		 * @param {boolean} newValue - Whether the dialog is open.
		 */
		async function onUpdateOpen( newValue ) {
			if ( !newValue ) {
				props.onDismiss();
			}
		}

		const isMobile = mw.config.get( 'skin' ) === 'minerva';

		return {
			isMobile,
			open,
			primaryAction,
			defaultAction,
			chips,
			onSave,
			onCancel,
			onUpdateOpen,
		};
	},
} );
</script>

<style lang="less">
.skin-vector-2022 .ext-growthExperiments-reading-recommendations-interest-selector-dialog {
	// Fix the height on desktop so that the layout does not shift when selecting interests.
	height: 48rem;
}
</style>
