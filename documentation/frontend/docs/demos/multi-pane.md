<!-- <link rel="stylesheet" href="../node_modules/@wikimedia/codex/dist/codex.style.css" /> -->

<script setup>
import '../../node_modules/@wikimedia/codex/dist/codex.style.css';
import MultiPaneDemo from '../../component-demos/multi-pane/MultiPaneDemo.vue'
</script>

MultiPane component demo
========================
A reusable navigation panel component with mobile gesture support to navigate between steps and slide transitions between.

## Demo
This example includes 3 steps. 
The content for each step can be provided via named slots as in OnboardingDialog component.
When the current step is updated an update:current-step event is emitted.

::: raw
<MultiPaneDemo />
:::

::: details View code
```vue
<template>
	<div class="ext-growthExperiments-MultiPaneDemo">
		<div class="ext-growthExperiments-MultiPaneDemo__container">
			<multi-pane
				v-model:current-step="currentStep"
				:total-steps="3"
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

		return {
			cdxIconNext,
			cdxIconPrevious,
			currentStep
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
```
:::
## Caveats

### Transitions
To correctly display slide transitions between dialog steps, step content needs to be wrapped in a single element, eg: div, section etc.

## Usage
### Props

| Prop name | Description | Type  | Default |
| --------- | ----------- | :---: | :-----: |
| currentStep | The number of the step displayed. Should be provided via a v-model:current-step binding in the parent scope | Number | 0 |
| totalSteps | The total number of the steps that will be displayed | Number | 1 |


### Events

| Event name | Properties | Description |
| ---------- | ---------- | ----------- |
| update:currentStep | Number | When the shown step changes |
