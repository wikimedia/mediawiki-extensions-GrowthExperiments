<template>
	<div class="ext-growthExperiments-ExampleComponent">
		<cdx-button @click="onButtonClick">
			Increment
		</cdx-button>
		<p class="ext-growthExperiments-ExampleComponent__counter">
			Count is {{ count }}
		</p>
	</div>
</template>

<script>
import { computed } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import { useCounterStore } from '../../store/example-store/counter.js';

export default {
	name: 'ExampleComponent',
	components: { CdxButton },
	setup() {
		const store = useCounterStore();
		const count = computed( () => store.count );
		const onButtonClick = () => store.increment();
		return {
			count,
			onButtonClick
		};
	}
};
</script>

<style lang="less">
.ext-growthExperiments-ExampleComponent {
	padding-top: 2em;

	&__counter {
		padding-top: 1em;
		padding-bottom: 1em;
		font-weight: bold;
	}
}
</style>
