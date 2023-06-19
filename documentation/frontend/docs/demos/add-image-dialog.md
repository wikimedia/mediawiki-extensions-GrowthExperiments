<!-- <link rel="stylesheet" href="../node_modules/@wikimedia/codex/dist/codex.style.css" /> -->

<script setup>
import '../../node_modules/@wikimedia/codex/dist/codex.style.css';
import AddImageDialogDemo from '../../component-demos/add-image-dialog/AddImageDialogDemo.vue'
</script>

Add an image dialog Vue prototype
===============================

This dialog is part of the in context help process of the Growth features. It is shown when a user arrives to an article page to start an "Add an image" task.

It has 4 navigable steps with arrows back and forth and includes a "Don't show again" checkbox that users can check to indicate they don't want to see the instructions again. The dialog can be closed at any step that is not the last one by the "Skip all" button at the top.

::: raw
<AddImageDialogDemo />
:::
---
Find more details in Phabricator: [Refactor the "Add an image" on-boarding dialog to Vue](https://phabricator.wikimedia.org/T329038).
