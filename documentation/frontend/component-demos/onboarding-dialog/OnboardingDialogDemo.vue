<template>
	<div class="ext-growthExperiments-OnboardingDialogDemo">
		<cdx-button @click="open = true">
			Show dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
		<onboarding-dialog
			v-model:open="open"
			:total-steps="3"
			:initial-step="1"
			class="ext-growthExperiments-OnboardingDialogDemo__dialog"
			:show-paginator="true"
		>
			<template #title>
				A slotted <i>Onboarding dialog</i> header
			</template>
			<template #headerbtntext>
				<b>Skip all</b>
			</template>
			<template #step1>
				<div>
					<h5 class="ext-growthExperiments-OnboardingDialogDemo__dialog__title">
						Step 1 with basic text content
					</h5>
					<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__text">
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
					</p>
				</div>
			</template>

			<template #step2>
				<div>
					<h5 class="ext-growthExperiments-OnboardingDialogDemo__dialog__title">
						Step 2 with long text content
					</h5>
					<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__text">
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
					</p>

					<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__text">
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
					</p>
					<br><br>
					<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__text">
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
					</p>
					<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__text">
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
					</p>
				</div>
			</template>
			<template #step3>
				<div>
					<img
						class="ext-growthExperiments-OnboardingDialogDemo__dialog__image"
						src="../../../../images/addlink/onboarding-image1-ltr.svg"
					>
					<h5 class="ext-growthExperiments-OnboardingDialogDemo__dialog__title">
						Step 3 This is an step with an image
					</h5>
					<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__text">
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam nulla
						ullam expedita consectetur.
					</p>
				</div>
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

<style lang="less">
@import '../../components/variables.less';
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

.ext-growthExperiments-OnboardingDialogDemo {
	&__dialog {
		height: @onboardingDialogHeight;

		&__image {
			margin: auto;
		}
	}
}
</style>
