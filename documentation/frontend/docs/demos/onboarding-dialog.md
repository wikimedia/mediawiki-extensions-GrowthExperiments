<script setup>
import '../../node_modules/@wikimedia/codex/dist/codex.style.css';
import OnboardingDialogDemo from '../../component-demos/onboarding-dialog/OnboardingDialogDemo.vue'
import OnboardingSimpleDialogDemo from '../../component-demos/onboarding-simple-dialog/OnboardingSimpleDialogDemo.vue'
</script>

Onboarding Dialog Vue demo
==========================

A reusable custom dialog created using [Codex Dialog](https://doc.wikimedia.org/codex/main/components/demos/dialog.html) component.

## Demo
An Onboarding dialog example with 3 steps

::: raw
<OnboardingDialogDemo />
:::

::: details View code

```vue
<template>
	<onboarding-dialog
		v-model:open="open"
		v-model:is-checked="isDontShowAgainChecked"
		class="ext-growthExperiments-OnboardingDialogDemo__dialog"
		:initial-step="1"
		:show-paginator="true"
		:total-steps="3"
		:is-rtl="readingDirection === 'rtl'"
		@close="onDialogClose"
	>
		<template #title>
			A slotted <i>Onboarding dialog</i> header
		</template>
		<template #closeBtnText>
			Skip all
		</template>
		<template #step1>
			<div>
				<h5>
					Step 1 with basic text content
				</h5>
				<p>
					Lorem ipsum dolor sit amet consectetur adipisicing elit.
					Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
					nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
					nulla ullam expedita consectetur.
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
			<template #checkboxLabel>
				Don't show again
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
- #### Dialog height
In the demo example above the height of the dialog is set to 520px. 

The dialog height must be set to a fixed value to avoid changes in dialog height between steps with different content length.

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
The following overwrites are applied to tha cdx-dialog styles to avoid padding in the dialog body
and to keep the same padding value when the dialog body content is and is not scrollable:
```css
	&.cdx-dialog--dividers,
	&.cdx-dialog--has-custom-header,
	&.cdx-dialog--has-custom-footer {
		.cdx-dialog__body {
			padding: 0;
		}
	}

	&.cdx-dialog--dividers {
		.cdx-dialog__header {
			padding-bottom: 0;
		}

		.cdx-dialog__footer {
			padding-top: 0;
		}
	}
```


## Usage
### Props

| Prop name | Description | Type  | Default |
| --------- | ----------- | :---: | :-----: |
| initialStep | The first step to show when the dialog is open | Number | 1 |
| isChecked | Checkbox value provided via a v-model:is-checked binding in the parent scope | Boolean | false |
| open | Whether the dialog is visible. Should be provided via a v-model:open binding in the parent scope | Boolean | false |
| stepperLabel | Text representation of dialog progress in the stepper component | String | '' |
| totalSteps | The total number of steps the dialog includes | Number  | 0 |


### Slots

| Name | Description | Bindings |
| ---- | ----------- | -------- |
| checkboxLabel | Text for the checkbox label | |
| default | If no #step1 is provided, the dialog will display the content in the default slot | |
| closeBtnText | Text for the header button | |
| stepN | Step content provided by named slots: #step1, #step2, #step3, etc... | |
| startBtnText | Text for the last step button| |
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
	<onboarding-dialog
		v-model:open="open"
		class="ext-growthExperiments-OnboardingDialogDemo__dialog"
		:total-steps="1"
		:initial-step="1"
		@close="onDialogClose"
	>
		<template #title>
			Onboarding dialog with one step
		</template>
		<!-- eslint-disable max-len -->
		<div
			class="ext-growthExperiments-OnboardingDialogDemo__dialog__image"
			role="img"
			aria-label="Illustration of the Moon article, with the articles Earth and Satellite being suggested as links that could be added to the associated text."
		>
		</div>
		<div class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content">
			<h5 class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__title">
				This is a simple dialog without steps.
			</h5>
			<p class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__text">
				One or several paragraphs will be included here as dialog content, and this text can be multiline.
			</p>
			<p class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__text">
				Lorem ipsum dolor sit amet consectetur. Interdum ultricies etiam pharetra sapien curabitur commodo. Imperdiet sed purus proin libero malesuada amet nibh. Eleifend diam tincidunt sagittis gravida.
			</p>
			<p class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__text">
				Nulla phasellus eget risus volutpat aliquet velit leo ac.
			</p>
		<!-- eslint-enable max-len -->
		</div>
		<template #checkboxLabel>
			Don't show again
		</template>
	</onboarding-dialog>
<template>

```
