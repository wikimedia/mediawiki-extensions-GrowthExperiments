<template>
	<!-- eslint-disable vue/no-v-model-argument max-len-->
	<onboarding-dialog
		v-model:open="wrappedOpen"
		v-model:is-checked="wrappedIsChecked"
		:total-steps="totalSteps"
		:initial-step="initialStep"
		class="ext-growthExperiments-AddImageDialog"
		:stepper-label="$i18n( 'growthexperiments-structuredtask-onboarding-dialog-progress', currentStep, totalSteps ).text()"
		@close="$emit( 'close', $event )"
		@update:current-step="( newVal )=> currentStep = newVal"
	>
		<!-- eslint-enable max-len -->
		<template #title>
			Introduction
		</template>
		<template #closeBtnText>
			Skip all
		</template>
		<template #step1>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					aria-label="Illustration of the moon article. Next to the article illustration there's a robot asking if an image of the moon should be added to the article."
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--1"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					Images help people learn, but many articles don't have one.
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						<!-- eslint-disable-next-line max-len -->
						You will decide whether a suggested image should be put in a Wikipedia article.
					</p>
					<br>
					<p>
						<!-- eslint-disable-next-line max-len -->
						Suggestions are machine-generated, and you'll use your judgment to decide whether to accept or reject them.
					</p>
					<br>
					<p
						class="ext-growthExperiments-AddImageDialog__text--italic">
						<!-- eslint-disable-next-line max-len -->
						Images come from Wikimedia Commons, a collection of freely licensed images used by Wikipedia.
					</p>
				</div>
			</div>
		</template>
		<template #step2>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					aria-label="Illustration of an article. An image is being suggested for the article. The image suggestion is zoomed in to highlight that the image suggestion and details should be reviewed."
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--2"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					Look at the suggested image
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						<!-- eslint-disable-next-line max-len -->
						Use the filename, description, and the reason it was suggested to help you decide if it should be placed in the article.
					</p>
					<br>
					<p>
						You can also expand the image to view it more clearly.
					</p>
				</div>
			</div>
		</template>
		<template #step3>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					aria-label="Illustration of an article. At the bottom of the article there’s an image suggestion."
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--3"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					Look at the article
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						<!-- eslint-disable-next-line max-len -->
						Read over the article and think about whether the suggested image will help readers understand the content. Is it appropriate to be displayed in the article?
					</p>
				</div>
			</div>
		</template>
		<template #step4>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					aria-label="Illustration of an article. An image is being suggested for the article. Inside of the suggestion there are three icon buttons for the options available: a checkmark to accept the suggestion, a cross to reject the suggestion, and an arrow to move to the next step."
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--4"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					Decide if the image belongs
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						<!-- eslint-disable-next-line max-len -->
						The suggestion may be unrelated to the article, low quality, or may not belong for other reasons. Use your judgment to decide whether the suggestion is right or wrong.
					</p>
					<br>
					<p>
						<!-- eslint-disable-next-line max-len -->
						For images that you accept, you'll write a short caption, and then your edit will be published.
					</p>
				</div>
			</div>
		</template>

		<template #checkboxLabel>
			Don't show again
		</template>
		<template
			#startBtnText>
			Get started
		</template>
	</onboarding-dialog>
</template>

<script>
import { ref, toRef } from 'vue';
import { useModelWrapper } from '@wikimedia/codex';
import OnboardingDialog from './OnboardingDialog.vue';

export default {
	name: 'AddImageDialog',
	components: {
		OnboardingDialog
	},
	props: {
		/**
		 * Whether the dialog is visible. Should be provided via a v-model:open
		 * binding in the parent scope.
		 */
		open: {
			type: Boolean,
			default: false
		},
		/**
		 * The initial value to use for the optional checkbox model. Should be
		 * provided via a v-model:is-checked binding in the parent scope.
		 */
		isChecked: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'update:open', 'update:is-checked', 'close' ],

	setup( props, { emit } ) {
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const wrappedIsChecked = useModelWrapper( toRef( props, 'isChecked' ), emit, 'update:is-checked' );
		const initialStep = 1;
		const currentStep = ref( initialStep );
		const totalSteps = 4;
		return {
			currentStep,
			initialStep,
			totalSteps,
			wrappedIsChecked,
			wrappedOpen
		};
	}
};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
@import '../node_modules/@wikimedia/codex/dist/mixins/link.less';
@import './variables.less';
@import './mixins.less';

.ext-growthExperiments-AddImageDialog {
	height: @onboardingDialogHeight;

	&__image {
		.ext-growthExperiments-onboarding-dialog-image();

		&--1 {
			background-image: url( ../../../images/addimage/onboarding-image1-ltr.svg );
		}

		&--2 {
			background-image: url( ../../../images/addimage/onboarding-image2-ltr.svg );
		}

		&--3 {
			background-image: url( ../../../images/addimage/onboarding-image3-ltr.svg );
		}

		&--4 {
			background-image: url( ../../../images/addimage/onboarding-image4-ltr.svg );
		}
	}

	&__title {
		.ext-growthExperiments-onboarding-dialog-textcontent();
		.ext-growthExperiments-onboarding-dialog-title();
	}

	&__text {
		.ext-growthExperiments-onboarding-dialog-textcontent();
		.ext-growthExperiments-onboarding-dialog-text();

		&--italic {
			color: @color-placeholder;
			font-size: @font-size-small;
			font-style: italic;
		}

		&__link {
			.cdx-mixin-link-base();
		}
	}
}
</style>
