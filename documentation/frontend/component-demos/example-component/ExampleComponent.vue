<template>
	<div class="ext-growthExperiments-ExampleComponent">
		<h1>Hello world</h1>
		<cdx-button @click="onButtonClick">
			increment
		</cdx-button>
		<p>Count is {{ count }}</p>
	</div>
</template>

<script>
import { ref } from 'vue';
import { CdxButton } from '@wikimedia/codex';

export default {
	name: 'ExampleComponent',
	components: { CdxButton },
	setup() {
		const count = ref( 0 );
		const onButtonClick = () => {
			count.value++;
		};
		return {
			count,
			onButtonClick
		};
	}

};
</script>
