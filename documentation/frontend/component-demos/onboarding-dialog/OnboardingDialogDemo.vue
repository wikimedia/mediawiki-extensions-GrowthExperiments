<template>
	<demo-wrapper>
		<div class="ext-growthExperiments-OnboardingDialogDemo">
			<cdx-button @click="open = true">
				Show dialog
			</cdx-button>
			<!-- eslint-disable vue/no-v-model-argument -->
			<onboarding-dialog
				v-model:open="open"
				v-model:is-checked="isDontShowAgainChecked"
				class="ext-growthExperiments-OnboardingDialogDemo__dialog"
				:initial-step="1"
				:show-paginator="true"
				:total-steps="3"
				:stepper-label="`${currentStep} of 3`"
				@close="onDialogClose"
				@update:current-step="( newVal ) => currentStep = newVal"
			>
				<template #title>
					A slotted <i>Onboarding dialog</i> header
				</template>
				<template #closeBtnText>
					Skip all
				</template>
				<template #step1>
					<div class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent"
					>
						<h5
							class="
						ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__title"
						>
							Step 1 with basic text content
						</h5>
						<p
							class="
						ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph"
						>
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
							nulla ullam expedita consectetur.
						</p>
					</div>
				</template>

				<template #step2>
					<div class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent"
					>
						<h5
							class="
							ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__title"
						>
							Step 2 with long text content
						</h5>
						<!-- eslint-disable-next-line max-len -->
						<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph">
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
							nulla ullam expedita consectetur.
						</p>
						<!-- eslint-disable-next-line max-len -->
						<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph">
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
							nulla ullam expedita consectetur.
						</p>
						<br><br>
						<!-- eslint-disable-next-line max-len -->
						<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph">
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
							nulla ullam expedita consectetur.
						</p>
						<!-- eslint-disable-next-line max-len -->
						<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph">
							Lorem ipsum dolor sit amet consectetur adipisicing elit.
							Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
							nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
							nulla ullam expedita consectetur.
						</p>
					</div>
				</template>
				<template #step3>
					<div>
						<!-- eslint-disable max-len -->
						<div
							class="ext-growthExperiments-OnboardingDialogDemo__dialog__image"
							role="img"
							aria-label="Illustration of the Moon article, with the articles Earth and Satellite being suggested as links that could be added to the associated text."
						>
						<!-- eslint-enable max-len -->
						</div>
						<div class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent"
						>
							<h5
								class="
							ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__title"
							>
								Step 1 with basic text content
							</h5>
							<!-- eslint-disable-next-line max-len -->
							<p class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph">
								Lorem ipsum dolor sit amet consectetur adipisicing elit.
								Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
								nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
								nulla ullam expedita consectetur.
							</p>
						</div>
					</div>
				</template>
				<template #checkboxLabel>
					Don't show again
				</template>
				<template #startBtnText>
					Get started
				</template>
			</onboarding-dialog>
		</div>
	</demo-wrapper>
</template>

<script>
import { ref } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import DemoWrapper from '../DemoWrapper.vue';
import OnboardingDialog from '../../components/OnboardingDialog.vue';

export default {
	name: 'OnboardingDialogDemo',

	components: {
		CdxButton,
		DemoWrapper,
		OnboardingDialog
	},
	setup() {
		const open = ref( false );
		const isDontShowAgainChecked = ref( false );
		const currentStep = ref( 1 );

		function onDialogClose( result ) {
			// eslint-disable-next-line no-console
			console.log( 'Dialog closed', result );
		}

		return {
			currentStep,
			isDontShowAgainChecked,
			open,
			onDialogClose
		};
	}
};
</script>

<style lang="less">
@import '../../components/variables.less';
@import '../../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
@import '../../components/mixins.less';

.ext-growthExperiments-OnboardingDialogDemo {
	border: @border-width-base @border-style-base;
	padding: @spacing-50;
	margin: @spacing-50;

	&__dialog {
		height: 520px;

		&__image {
			background-image: url( ../../../../images/addlink/onboarding-image1-ltr.svg );
			.ext-growthExperiments-onboarding-dialog-image();
		}

		&__textcontent {
			padding-left: @spacing-150;
			padding-right: @spacing-150;

			&__title {
				.ext-growthExperiments-onboarding-dialog-title();
			}

			&__paragraph {
				padding-top: @spacing-50;
				font-size: @font-size-small;
				line-height: @line-height-small;
			}
		}
	}
}
</style>
