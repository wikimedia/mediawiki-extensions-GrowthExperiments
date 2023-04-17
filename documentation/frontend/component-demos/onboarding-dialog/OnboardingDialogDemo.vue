<template>
	<div>
		<cdx-button @click="open = true">
			Show dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
		<onboarding-dialog
			v-model:open="open"
			:total-steps="3"
			:initial-step="1"
			class="ext-growthExperiments-OnboardingDialogDemo"
			:show-paginator="true"
		>
			<template #title>
				A slotted <i>Onboarding dialog</i> header
			</template>
			<template #headerbtntext>
				<b>Skip all</b>
			</template>
			<template #step1>
				<h5>
					Step 1 with basic text content
				</h5>
				<p>
					Lorem ipsum dolor sit amet consectetur adipisicing elit.
					Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
					nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
					ullam expedita consectetur.
				</p>
			</template>

			<template #step2>
				<h5>
					Step 2 with long text content
				</h5>
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
			<template #step3>
				<img src="../../../../images/addlink/onboarding-image1-ltr.svg"
				>

				<h5>
					Step 3 This is an step with an image
				</h5>

				<p>
					Lorem ipsum dolor sit amet consectetur adipisicing elit.
					Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
					nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
					ullam expedita consectetur.
				</p>
			</template>
		</onboarding-dialog>
	</div>
</template>

<script>
import { CdxButton } from '@wikimedia/codex';
import { ref } from 'vue';
import OnboardingDialog from '../../components/OnboardingDialog.vue';
export default {
	name: 'OnboardingDialogDemo',

	components: {
		CdxButton,
		OnboardingDialog
	},

	setup() {
		const open = ref( false );
		return {
			open
		};
	}
};
</script>
