<template>
	<div>
		<cdx-button @click="open = true">
			Show Filter Dialog
		</cdx-button>

		<!-- eslint-disable vue/no-v-model-argument -->
		<filter-dialog
			v-model:open="open"
			:is-loading="false"
		>
			<template #title>
				Select
			</template>
			<template #doneBtn>
				Done
			</template>
			<h6>Some content for the filter dialog</h6>
			<p>
				Lorem ipsum dolor sit amet consectetur adipisicing elit.
				Veniam quo mollitia expedita, sit laborum facere ipsam voluptatem
				modi exercitationem minus dignissimos nulla. Explicabo omnis eligendi
				sed distinctio culpa? Repellendus, quae!
			</p>
		</filter-dialog>
	</div>
</template>

<script>
import { CdxButton } from '@wikimedia/codex';
import FilterDialog from '../../components/FilterDialog.vue';
import { ref } from 'vue';
export default {
	name: 'FilterDialogDemo',

	components: {
		FilterDialog,
		CdxButton
	},
	setup() {
		const open = ref( false );

		return {
			open
		};

	}
};
</script>
