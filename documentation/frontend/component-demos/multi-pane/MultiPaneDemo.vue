<template>
	<div class="ext-growthExperiments-MultiPaneDemo">
		<div class="ext-growthExperiments-MultiPaneDemo__container">
			<multi-pane
				:total-steps="3"
				:current-step="currentStep"
				:is-rtl="isRtl"
				@update:current-step="( newVal ) => currentStep = newVal"
			>
				<template #step1>
					<div
						class="ext-growthExperiments-MultiPaneDemo__step
					ext-growthExperiments-MultiPaneDemo__step-1"
					>
					</div>
				</template>
				<template #step2>
					<div
						class="ext-growthExperiments-MultiPaneDemo__step
					ext-growthExperiments-MultiPaneDemo__step-2"
					>
					</div>
				</template>
				<template #step3>
					<div
						class="ext-growthExperiments-MultiPaneDemo__step
					ext-growthExperiments-MultiPaneDemo__step-3"
					>
					</div>
				</template>
			</multi-pane>
		</div>
		<div class="ext-growthExperiments-MultiPaneDemo__controls">
			<cdx-button
				weight="primary"
				action="progressive"
				class="cdx-button--icon-only"
				aria-label="prev"
				:disabled="currentStep === 1"
				@click="currentStep > 1 ? currentStep-- : currentStep">
				<cdx-icon :icon="cdxIconPrevious" icon-label="previous"></cdx-icon>
			</cdx-button>
			<cdx-button
				weight="primary"
				action="progressive"
				class="cdx-button--icon-only"
				aria-label="next"
				:disabled="currentStep === 3"
				@click="currentStep < 3 ? currentStep++ : currentStep">
				<cdx-icon :icon="cdxIconNext" icon-label="next"></cdx-icon>
			</cdx-button>
		</div>
	</div>
</template>

<script>
import MultiPane from '../../components/MultiPane.vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconNext, cdxIconPrevious } from '@wikimedia/codex-icons';
import { ref } from 'vue';
export default {
	name: 'MultiPaneDemo',
	components: {
		MultiPane,
		CdxButton,
		CdxIcon
	},
	setup() {
		const currentStep = ref( 1 );
		const isRtl = ref( false );
		return {
			cdxIconNext,
			cdxIconPrevious,
			currentStep,
			isRtl
		};
	}
};
</script>

<style lang="less">
@import '../../components/variables.less';
@import '../../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

.ext-growthExperiments-MultiPaneDemo {
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;

	&__container {
		border: @border-width-base @border-style-base;
		width: 300px;
		height: 300px;
		display: flex;
		justify-content: center;
		align-items: center;
		overflow: hidden;
	}

	&__step {
		width: 280px;
		height: 280px;
		display: flex;
		justify-content: center;
		align-items: flex-end;

		&-1 {
			background-color: @background-color-error-subtle;
		}

		&-2 {
			background-color: @background-color-warning-subtle;
		}

		&-3 {
			background-color: @background-color-success-subtle;
		}
	}

	&__controls {
		padding-top: @spacing-75;
		display: flex;
		width: @size-800;
		justify-content: space-evenly;
	}
}
</style>
