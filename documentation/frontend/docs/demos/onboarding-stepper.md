<!-- <link rel="stylesheet" href="../node_modules/@wikimedia/codex/dist/codex.style.css" /> -->

<script setup>
import '../../node_modules/@wikimedia/codex/dist/codex.style.css';
import OnboardingStepperDemo from '../../component-demos/onboarding-stepper-demo/OnboardingStepperDemo.vue'
</script>

Stepper component demo
========================
A reusable stepper that provides a visual reference of progress inside the dialog.

## Demo
This example includes 3 steps. 
The content for each step can be provided via named slots as in OnboardingDialog component.
When the current step is updated an update:current-step event is emitted.

::: raw
<OnboardingStepperDemo />
:::

::: details View code

```vue
<onboarding-stepper
	:model-value="modelValue"
	:total-steps="totalSteps"
	:label="`${modelValue} of ${totalSteps}`"
>
</onboarding-stepper>
```
:::

## Usage
### Props

| Prop name | Description | Type  | Default |
| --------- | ----------- | :---: | :-----: |
| modelValue | The current step | Number | 0 |
| totalSteps | The total number of steps | Number | 0 |
| label | If provided add a textual representation of step progress | String | '' |



