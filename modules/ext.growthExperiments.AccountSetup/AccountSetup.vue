<template>
	<cdx-dialog
		v-model:open="open"
		:fixed-height="true"
		:title="$i18n( 'growthexperiments-account-setup-a11y-title' ).text()"
		class="ext-growthExperiments-account-setup"
		@update:open="skipAndGoToHome"
	>
		<template #header>
			<div class="ext-growthExperiments-account-setup-header">
				<cdx-button
					v-if="step === 0"
					:aria-label="$i18n( 'growthexperiments-account-setup-skip-a11y-label' ).text()"
					weight="quiet"
					@click="skipAndGoToHome"
				>
					<cdx-icon :icon="cdxIconClose"></cdx-icon>
				</cdx-button>
				<cdx-button
					v-else
					:aria-label="$i18n( 'growthexperiments-account-setup-back-a11y-label' ).text()"
					weight="quiet"
					@click="goToPreviousScreen"
				>
					<cdx-icon :icon="cdxIconPrevious"></cdx-icon>
				</cdx-button>
				<span class="ext-growthExperiments-account-setup-header__counter">{{
					$i18n( 'growthexperiments-account-setup-step-counter', step + 1, 3 ).text()
				}}</span>
			</div>
		</template>

		<div v-if="step === 0" class="ext-growthExperiments-account-setup-step-1">
			<h1 class="ext-growthExperiments-account-setup-step-1-heading">
				{{
					$i18n( 'growthexperiments-account-setup-step-1-title', siteName, username ).text()
				}}
			</h1>
			<div class="ext-growthExperiments-account-setup-step-1-image"></div>
		</div>
		<div
			v-else-if="step === 1"
			class="ext-growthExperiments-account-setup-step-2"
		>
			<h3
				class="ext-growthExperiments-account-setup-step-2-heading"
			>
				{{ $i18n( 'growthexperiments-account-setup-step-2-title', siteName ).text() }}
			</h3>
			<button
				class="ext-growthExperiments-account-setup-transparent-button"
				@click="() => accountTypeClicked( 'reading' )"
			>
				<cdx-card>
					<template #title>
						{{ $i18n( 'growthexperiments-account-setup-motivation-option-reading-title' ).text() }}
					</template>
					<template #description>
						{{ $i18n( 'growthexperiments-account-setup-motivation-option-reading-description' ).text() }}
					</template>
				</cdx-card>
			</button>
			<button
				class="ext-growthExperiments-account-setup-transparent-button"
				@click="() => accountTypeClicked( 'editing' )"
			>
				<cdx-card>
					<template #title>
						{{ $i18n( 'growthexperiments-account-setup-motivation-option-editing-title' ).text() }}
					</template>
					<template #description>
						{{ $i18n( 'growthexperiments-account-setup-motivation-option-editing-description' ).text() }}
					</template>
				</cdx-card>
			</button>
			<button
				class="ext-growthExperiments-account-setup-transparent-button"
				data-test-id="account-setup-step-2-both"
				@click="() => accountTypeClicked( 'both' )"
			>
				<cdx-card>
					<template #title>
						{{ $i18n( 'growthexperiments-account-setup-motivation-option-both-title' ).text() }}
					</template>
					<template #description>
						{{ $i18n( 'growthexperiments-account-setup-motivation-option-both-description' ).text() }}
					</template>
				</cdx-card>
			</button>
		</div>
		<div v-else>
			<h3
				class="ext-growthExperiments-account-setup-step-3-heading"
			>
				{{ $i18n( 'growthexperiments-account-setup-step-3-title' ).text() }}
			</h3>
			<interest-selector
				:chips="chips"
				@update:chips="updateChips"
			></interest-selector>
		</div>

		<template #footer>
			<div v-if="step === 0">
				<cdx-button
					weight="primary"
					action="progressive"
					size="large"
					class="ext-growthExperiments-account-setup-footer-buttons"
					@click="goToNextScreen"
				>
					<span
						data-test-id="account-setup-step-1-progress"
					>
						{{ $i18n( 'growthexperiments-account-setup-step-1-progress-button' ).text() }}
					</span>
				</cdx-button>
			</div>
			<div v-else-if="step === 1">
				<cdx-button
					weight="quiet"
					action="default"
					size="large"
					class="ext-growthExperiments-account-setup-footer-buttons"
					@click="() => accountTypeClicked( 'skipped' )"
				>
					{{ $i18n( 'growthexperiments-account-setup-step-2-skip-button' ).text() }}
				</cdx-button>
			</div>
			<div v-else>
				<cdx-button
					:weight="chips.length >= 3 ? 'primary' : 'quiet'"
					:action="chips.length >= 3 ? 'progressive' : 'default'"
					size="large"
					class="ext-growthExperiments-account-setup-footer-buttons"
					@click="saveAndGoToHome"
				>
					<span
						data-test-id="account-setup-step-3-finish"
					>
						{{ $i18n( 'growthexperiments-account-setup-step-3-finish-button' ).text() }}
					</span>
				</cdx-button>
			</div>
		</template>
	</cdx-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxDialog, CdxButton, CdxCard, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconPrevious, cdxIconClose } = require( './codex-icons.json' );
const useAccountSetupStore = require( './AccountSetupStore.js' );
const InterestSelector = require( '../vue-components/InterestSelector.vue' );

// @vue/component
module.exports = defineComponent( {
	name: 'AccountSetup',
	components: {
		InterestSelector,
		CdxDialog,
		CdxButton,
		CdxCard,
		CdxIcon,
	},
	setup() {
		const store = useAccountSetupStore();
		const { step, chips, modulePreset } = storeToRefs( store );

		async function saveAndGoToHome() {
			await store.saveInterestArticles();
			window.location.reload();
		}

		async function skipAndGoToHome() {
			await store.saveInitialModulePreset();
			window.location.reload();
		}

		/**
		 * @param {'both'|'editing'|'reading'|'skipped'} accountType
		 */
		function accountTypeClicked( accountType ) {
			modulePreset.value = accountType;
			store.saveInitialModulePreset();
			store.stepForward();
		}

		const open = ref( true );

		const siteName = mw.config.get( 'wgSiteName' );
		const username = mw.user.getName();

		return {
			open,
			chips,
			updateChips: store.updateChips,
			goToNextScreen: store.stepForward,
			goToPreviousScreen: store.stepBack,
			step,
			accountTypeClicked,
			saveAndGoToHome,
			skipAndGoToHome,
			siteName,
			username,
			cdxIconPrevious,
			cdxIconClose,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.growthexperiments-homepage-desktop .cdx-dialog-backdrop {
	background: @background-color-backdrop-light;
	backdrop-filter: blur( 30px );
}

.ext-growthExperiments-account-setup {
	.cdx-dialog__header {
		padding: 0;
	}

	.cdx-dialog__body {
		height: 100%;
	}

	&-header {
		display: flex;
		padding: @spacing-125 @spacing-100 @spacing-75 @spacing-100;
		justify-content: space-between;
		align-items: flex-start;

		&__counter {
			align-self: stretch;
			color: @color-subtle;
			text-align: right;
			font-family: @font-family-base;
			font-size: @font-size-medium;
			font-style: normal;
			font-weight: normal;
			line-height: @line-height-small;
		}
	}

	&-step-1 {
		height: 100%;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: space-between;

		&-heading {
			border: 0;
			margin: 0;
			color: @color-base;
			font-family: @font-family-serif;
			font-size: @font-size-xxx-large;
			font-style: normal;
			font-weight: normal;
			line-height: @line-height-xxx-large;
		}

		&-image {
			background: url( ../../images/accountsetup/bg_confetti_small.gif ) transparent 50% / cover no-repeat;
			height: 256px;
			width: 256px;
		}
	}

	&-step-2 {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: @spacing-75;
		flex: 1 0 0;
		align-self: stretch;
	}

	&-step-3-heading,
	&-step-2-heading {
		color: @color-base;
		font-family: @font-family-base;
		font-size: @font-size-x-large;
		font-style: normal;
		font-weight: bold;
		line-height: @line-height-x-large;
	}

	&-footer-buttons {
		width: 100%;
	}

	&-transparent-button {
		background: none;
		border: 0;
		padding: 0;
		width: 100%;
		text-align: inherit;
		font: inherit;
		cursor: pointer;
	}
}
</style>
