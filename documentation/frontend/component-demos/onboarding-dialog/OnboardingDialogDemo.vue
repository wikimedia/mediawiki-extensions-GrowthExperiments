<template>
	<div>
		<cdx-button @click="open = true">
			Show dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
		<onboarding-dialog
			v-model:open="open"
			:total-steps="3"
			:initial-step="0"
			class="ext-growthExperiments-OnboardingDialogDemo"
		>
			<template #title>
				A slotted <i>Onboarding dialog</i> header
			</template>
			<template #headerbtntext>
				<b>Skip all</b>
			</template>
			<template #body="{ currentIndex }">
				<onboarding-step
					:current-index="currentIndex"
					:index="1"
				>
					<template #content-title>
						Step 1 with basic text content
					</template>
					<template #content-text>
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
					</template>
				</onboarding-step>
				<onboarding-step
					:current-index="currentIndex"
					:index="2"
				>
					<template #content-title>
						Step 2 with long text content
					</template>
					<template #content-text>
						<p>
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
							ullam expedita consectetur.
						</p>

						<p>
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
							ullam expedita consectetur.
						</p>
						<br><br>
						<p>
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
							ullam expedita consectetur.
						</p>

						<p>
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
							ullam expedita consectetur.
						</p>
					</template>
				</onboarding-step>
				<onboarding-step
					:current-index="currentIndex"
					:index="3"
				>
					<template #image>
						<img src="../../../../images/addlink/onboarding-image1-ltr.svg"
						>
					</template>
					<template #content-title>
						Step 3 This is an step with an image
					</template>

					<template #content-text>
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
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
