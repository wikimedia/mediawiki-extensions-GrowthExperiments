<template>
	<div class="ext-growthExperiments-OnboardingSimpleDialogDemo">
		<cdx-button @click="open = true">
			Show dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
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
	</div>
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

<style lang="less">
.ext-growthExperiments-OnboardingSimpleDialogDemo {
	padding-top: 2em;
}
</style>
