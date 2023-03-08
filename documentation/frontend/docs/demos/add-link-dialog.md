<!-- <link rel="stylesheet" href="../node_modules/@wikimedia/codex/dist/codex.style.css" /> -->

<script setup>
import '../../node_modules/@wikimedia/codex/dist/codex.style.css';
import AddLinkDialogDemo from '../../component-demos/add-link-dialog/AddLinkDialogDemo.vue'
</script>

Add a link dialog Vue prototype
===============================

When a newcomer starts an Add a link task a dialog is placed on top of visual editor giving the user detailed information in the steps to complete the task. 

- This dialog has 3 steps which are navigable with arrows back and forth.
- The first step includes a "Don't show again" checkbox mark so users can check it to indicate they don't want the instructions dialog to show again when doing Add a link tasks.
- The last step includes a "Get started" button to close the dialog.
- The dialog can be closed at any step that is not the last one by the "Skip all" button at the top.

See the [Figma Design](https://www.figma.com/file/Pgo6fPGaDDiqXWGfMI8oiF/Growth---features?node-id=1271-97685).


::: raw
<AddLinkDialogDemo />
:::


