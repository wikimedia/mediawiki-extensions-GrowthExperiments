<template>
	<div>
		<cdx-button @click="open = true">
			Show dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
		<onboarding-dialog
			v-model:open="open"
			:total-steps="2"
			:initial-step="0"
		>
			<template #title>
				<h5>A slotted <i>Onboarding ddialog</i> header</h5>
			</template>
			<template #headerbtntext>
				<b>Skip all</b>
			</template>
			<template #body="{ currentIndex }">
				<onboarding-step
					:current-index="currentIndex"
					:index="1"
				>
					<template #main>
						Step 1
					</template>
				</onboarding-step>
				<onboarding-step
					:current-index="currentIndex"
					:index="2"
				>
					<template #main>
						Step 2
					</template>
				</onboarding-step>
			</template>
		</onboarding-dialog>
	</div>
</template>

<script>
import { CdxButton } from '@wikimedia/codex';
import { ref } from 'vue';
import OnboardingDialog from '../../components/OnboardingDialog.vue';
import OnboardingStep from '../../components/OnboardingStep.vue';
export default {
	name: 'OnboardingDialogDemo',

	components: {
		CdxButton,
		OnboardingDialog,
		OnboardingStep
	},
	setup() {
		const open = ref( false );
		return {
			open
		};
	}
};
</script>
