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
    <multi-pane
        v-model:current-step="currentStep"
        :total-steps="3"
        :is-rtl="isRtl"
        @update:current-step="( newVal ) => currentStep = newVal"
    >
		<template #step1>
			<div class="ext-growthExperiments-MultiPaneDemo__step
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
```
:::
## Caveats

### Transitions
To correctly display slide transitions between dialog steps, step content needs to be wrapped in a single element, eg: div, section etc.

## Usage
### Props

| Prop name | Description | Type  | Default |
| --------- | ----------- | :---: | :-----: |
| currentStep | The number of the step displayed. Should be provided via a v-model:current-step binding in the parent scope | Number | 1 |
| isRtl | If reading direction is RTL | Boolean | false |


### Events

| Event name | Properties | Description |
| ---------- | ---------- | ----------- |
| update:currentStep | Number | When the shown step changes |