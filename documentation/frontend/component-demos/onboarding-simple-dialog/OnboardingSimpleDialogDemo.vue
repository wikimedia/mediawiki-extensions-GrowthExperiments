<template>
	<div class="ext-growthExperiments-OnboardingSimpleDialogDemo">
		<cdx-button @click="open = true">
			Show dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
		<onboarding-dialog
			v-model:open="open"
			class="ext-growthExperiments-OnboardingDialogDemo__dialog"
			:total-steps="1"
			:initial-step="1"
			@close="onDialogClose"
		>
			<template #title>
				Onboarding dialog with one step
			</template>

			<!-- eslint-disable max-len -->
			<div
				class="ext-growthExperiments-OnboardingDialogDemo__dialog__image"
				role="img"
				aria-label="Illustration of the Moon article, with the articles Earth and Satellite being suggested as links that could be added to the associated text."
			>
			</div>
			<div class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content">
				<h5 class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__title">
					This is a simple dialog without steps.
				</h5>
				<p class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__text">
					One or several paragraphs will be included here as dialog content, and this text can be multiline.
				</p>
				<p class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__text">
					Lorem ipsum dolor sit amet consectetur. Interdum ultricies etiam pharetra sapien curabitur commodo. Imperdiet sed purus proin libero malesuada amet nibh. Eleifend diam tincidunt sagittis gravida.
				</p>
				<p class="ext-growthExperiments-OnboardingSimpleDialogDemo__dialog__content__text">
					Nulla phasellus eget risus volutpat aliquet velit leo ac.
				</p>
				<!-- eslint-enable max-len -->
			</div>
			<template #checkboxLabel>
				Don't show again
			</template>
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
@import '../../components/variables.less';
@import '../../components/mixins.less';
@import '../../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

.ext-growthExperiments-OnboardingSimpleDialogDemo {
	padding-top: 2em;

	&__dialog {
		&__image {
			.ext-growthExperiments-onboarding-dialog-image();
		}

		&__content {
			.ext-growthExperiments-onboarding-dialog-textcontent();

			&__title {
				.ext-growthExperiments-onboarding-dialog-title();
			}

			&__text {
				.ext-growthExperiments-onboarding-dialog-text();
			}
		}
	}
}
</style>
