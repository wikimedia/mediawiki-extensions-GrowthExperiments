<script setup>
import '../../node_modules/@wikimedia/codex/dist/codex.style.css';
import FilterDialogDemo from '../../component-demos/filter-dialog/FilterDialogDemo.vue'
</script>

Filter Dialog Vue prototype
===========================



A reusable custom dialog created using [Codex Dialog](https://doc.wikimedia.org/codex/main/components/demos/dialog.html) component.

## Demo
A basic filter dialog example
::: raw
<FilterDialogDemo />
:::

## Usage
### Props

| Prop name | Description | Type  | Default |
| --------- | ----------- | :---: | :-----: |
| isLoading | When true the dialog has loading styles and the 'progressive' button is disabled | Boolean | false |
| open | Whether the dialog is visible. Should be provided via a v-model:open binding in the parent scope | Boolean | false |

### Slots

| Name | Description | Bindings |
| ---- | ----------- | -------- |
| default | Content in the dialog body | |
| doneBtn | Text for the progressive action button in the dialog header | |
| taskCount | Text to display in the dialog footer | |
| taskCountLoading | Text to display in the dialog footer when isLoading | |
| title | Text to display in the dialog header | |


### Events

| Event name | Properties | Description |
| ---------- | ---------- | ----------- |
| close | Object | Emitted when the dialog is closed |
| update:open | Boolean | When the open/close dialog state changes |
