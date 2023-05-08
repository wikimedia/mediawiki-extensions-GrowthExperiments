<script setup>
import '../../node_modules/@wikimedia/codex/dist/codex.style.css';
import OnboardingDialogDemo from '../../component-demos/onboarding-dialog/OnboardingDialogDemo.vue'
import OnboardingSimpleDialogDemo from '../../component-demos/onboarding-simple-dialog/OnboardingSimpleDialogDemo.vue'
</script>

Onboarding Dialog Vue demo
==========================

A reusable custom dialog created using [Codex Dialog](https://doc.wikimedia.org/codex/main/components/demos/dialog.html) component.

## Demo
This example includes 3 steps that are navigable with arrow buttons back and forth. It includes a paginator to inform the user the progress within the dialog, a dismiss button in the dialog header, and a checkbox in the dialog footer on the first step.
::: raw
<OnboardingDialogDemo />
:::

::: details View code

```vue
<template>
	<cdx-button @click="open = true">
		Show dialog
	</cdx-button>
	<!-- eslint-disable vue/no-v-model-argument -->
	<onboarding-dialog
		v-model:open="open"
		v-model:is-checked="isDontShowAgainChecked"
		:total-steps="3"
		:initial-step="1"
		:show-paginator="true"
		@close="onDialogClose"
	>
		<template #title>
			A slotted <i>Onboarding dialog</i> header
		</template>
		<template #headerbtntext>
			<b>Skip all</b>
		</template>
		<template #step1>
			<div>
				<h5>
					Step 1 with basic text content
				</h5>
				<p>
					Lorem ipsum dolor sit amet consectetur adipisicing elit.
					Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
					nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
					ullam expedita consectetur.
				</p>
			</div>
		</template>
		<template #step2>
			<div>
				// step content...
			</div>
		</template>
		<template #step3>
			<div>
				// step content...
			</div>
		</template>
    	<template #checkbox>
    	  	Don't show again
    	</template>
    	<template #last-step-button-text>
    	  	Get Started
    	</template>
	</onboarding-dialog>
</template>

<script>
import { ref } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import OnboardingDialog from '..';

export default {
	name: 'OnboardingDialogDemo',

	components: {
		CdxButton,
		OnboardingDialog
	},

	setup() {
		const open = ref( false );
		const isDontShowAgainChecked = ref( false );
		
		function onDialogClose( result ) {
			// eslint-disable-next-line no-console
			console.log( 'Dialog closed', result );
		}

		return {
			isDontShowAgainChecked,
			open,
			onDialogClose
		};
	}
};
</script>
```
:::

### Caveats
- #### Transitions
To correctly display slide transitions between dialog steps, step content needs to be wrapped in a single element, eg: div, section

```html
<template #step1>
  <div>
   // step content...
  </div>
</template>
```

### CSS overwrites

- #### Codex dialog body overflow-x
The overflow-x in .cdx-dialog__body class is overwritten to 'hidden' to avoid showing the horizontal scroll bar during transitions

## Usage
### Props

| Prop name | Description | Type  | Default |
| --------- | ----------- | :---: | :-----: |
| initialStep | The first step to show when the dialog is open | Number | 1 |
| open | Whether the dialog is visible. Should be provided via a v-model:open binding in the parent scope | Boolean | false |
| showPaginator | Whether the dialog should display the paginator at the top of the content if more than one step is provided | Boolean | false |
| totalSteps | The total number of steps the dialog includes | Number  | 0 |


### Slots

| Name | Description | Bindings |
| ---- | ----------- | -------- |
| checkbox | If provided, the first step includes a checkbox in the dialog footer | |
| default | If no #step1 is provided, the dialog will display this content | |
| headerbtntext | Header button to display in all the steps aside from the last one | |
| last-step-button-text | Text content for the footer button on the last step | |
| stepN | Step content can be provided by using the named slots: #step1, #step2, #step3, etc... | |
| title | Main dialog title | |


### Events

| Event name | Properties | Description |
| ---------- | ---------- | ----------- |
| close | Object | Emitted when the dialog is closed |
| update:currentStep | Number | When the shown step changes |
| update:open | Boolean | When the open/close dialog state changes |
| update:is-checked | Boolean | When the checkbox value changes |


## Examples

### Simple dialog without steps
If no #step1 is provided, the dialog will display fixed content in the #default slot.

::: raw
<OnboardingSimpleDialogDemo />
:::

::: details View code

```vue
<template>
	<cdx-button @click="open = true">
		Show dialog
	</cdx-button>
	<onboarding-dialog
		v-model:open="open"
		:total-steps="1"
		:initial-step="1"
		@close="onDialogClose"
	>
		<template #title>
			A simple <i>Onboarding dialog</i>
		</template>
		<template #headerbtntext>
			Close
		</template>
		<h5>This is a simple dialog without steps.</h5>
		<p>This dialog doesn't include paginator, header button, and checkbox.</p>
	</onboarding-dialog>
</template>

<script>
import { ref } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import OnboardingDialog from '../../components/OnboardingDialog.vue';

export default {
	name: 'OnboardingSimpleDialogDemo',

	components: {
		CdxButton,
		OnboardingDialog
	},

	setup() {
		const open = ref( false );

		function onDialogClose( result ) {
			// eslint-disable-next-line no-console
			console.log( 'Dialog closed', result );
		}

		return {
			open,
			onDialogClose
		};
	}
};
</script>

```
