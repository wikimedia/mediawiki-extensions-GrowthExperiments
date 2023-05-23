<template>
	<!-- necessary to make LTR/RTL demos work -->
	<div ref="contentRef" class="ext-growthExperiments-DemoWrapper">
		<slot></slot>
	</div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
const contentRef = ref( null );
// READING_DIRECTION is initially set in vite/theme/index.js based on the default
// locale. It's a reactive property so it can be set again in vite/theme/CustomLayout.vue
// once the application is mounted on the client and it can retrieve the ?uselang param.
const readingDirection = inject( 'READING_DIRECTION' );
// Set the direction onMounted hook since binding directly in the template does not
// work after the site is built.
onMounted( () => contentRef.value.setAttribute( 'dir', readingDirection.value ) );
</script>
