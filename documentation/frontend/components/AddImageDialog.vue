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
			{{ $i18n( 'growthexperiments-structuredtask-onboarding-dialog-title' ).text() }}
		</template>
		<template #closeBtnText>
			{{
				$i18n( 'growthexperiments-structuredtask-onboarding-dialog-label-skip-all' ).text()
			}}
		</template>
		<template #step1>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					:aria-label="$i18n( 'growthexperiments-addimage-onboarding-content-intro-image-alt-text' ).text()"
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--1"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					{{
						$i18n( 'growthexperiments-addimage-onboarding-content-intro-title' ).text()
					}}
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						{{
							$i18n(
								// eslint-disable-next-line max-len
								"growthexperiments-addimage-onboarding-content-intro-body-paragraph1",
								userName
							).text()
						}}
					</p>
					<p>
						{{
							$i18n(
								// eslint-disable-next-line max-len
								"growthexperiments-addimage-onboarding-content-intro-body-paragraph2",
								userName
							).text()
						}}
					</p>
					<p
						class="ext-growthExperiments-AddImageDialog__text--italic">
						{{
							$i18n(
								// eslint-disable-next-line max-len
								"growthexperiments-addimage-onboarding-content-intro-body-paragraph3"
							).text()
						}}
					</p>
				</div>
			</div>
		</template>
		<template #step2>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					:aria-label="$i18n( 'growthexperiments-addimage-onboarding-content-imagedetails-image-alt-text' ).text()"
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--2"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					{{
						// eslint-disable-next-line max-len
						$i18n( 'growthexperiments-addimage-onboarding-content-imagedetails-title' ).text()
					}}
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						{{
							// eslint-disable-next-line max-len
							$i18n( 'growthexperiments-addimage-onboarding-content-imagedetails-body-paragraph1', userName ).text()
						}}
					</p>
					<p>
						{{
							// eslint-disable-next-line max-len
							$i18n( 'growthexperiments-addimage-onboarding-content-imagedetails-body-paragraph2', userName ).text()
						}}
					</p>
				</div>
			</div>
		</template>
		<template #step3>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					:aria-label="$i18n( 'growthexperiments-addimage-onboarding-content-article-image-alt-text' ).text()"
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--3"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					{{
						// eslint-disable-next-line max-len
						$i18n( 'growthexperiments-addimage-onboarding-content-article-title' ).text()
					}}
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						{{
							// eslint-disable-next-line max-len
							$i18n( 'growthexperiments-addimage-onboarding-content-article-body' ).text()
						}}
					</p>
				</div>
			</div>
		</template>
		<template #step4>
			<div>
				<!-- eslint-disable max-len -->
				<div
					role="img"
					:aria-label="$i18n( 'growthexperiments-addimage-onboarding-content-decision-image-alt-text' ).text()"
					class="ext-growthExperiments-AddImageDialog__image
					ext-growthExperiments-AddImageDialog__image--4"
				>
				<!-- eslint-enable max-len -->
				</div>
				<h5 class="ext-growthExperiments-AddImageDialog__title">
					{{
						// eslint-disable-next-line max-len
						$i18n( 'growthexperiments-addimage-onboarding-content-decision-title' ).text()
					}}
				</h5>
				<div class="ext-growthExperiments-AddImageDialog__text">
					<p>
						{{
							// eslint-disable-next-line max-len
							$i18n( 'growthexperiments-addimage-onboarding-content-decision-body-paragraph1', userName ).text()
						}}
					</p>
					<p>
						{{
							// eslint-disable-next-line max-len
							$i18n( 'growthexperiments-addimage-onboarding-content-decision-body-paragraph2', userName ).text()
						}}
					</p>
				</div>
			</div>
		</template>
		<template #checkboxLabel>
			{{
				$i18n(
					// eslint-disable-next-line max-len
					"growthexperiments-structuredtask-onboarding-dialog-dismiss-checkbox"
				).text()
			}}
		</template>
		<template
			#startBtnText>
			{{
				$i18n(
					// eslint-disable-next-line max-len
					"growthexperiments-structuredtask-onboarding-dialog-get-started-button"
				).text()
			}}
		</template>
	</onboarding-dialog>
</template>

<script>
import { inject, ref, toRef } from 'vue';
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
		},
		/**
		 * Url to show a "learn more" link in one of the dialogs steps
		 */
		// eslint-disable-next-line vue/no-unused-properties
		learnMoreLink: {
			type: String,
			default: ''
		}
	},
	emits: [ 'update:open', 'update:is-checked', 'close' ],

	setup( props, { emit } ) {
		const userName = inject( 'USER_USERNAME' );
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const wrappedIsChecked = useModelWrapper( toRef( props, 'isChecked' ), emit, 'update:is-checked' );
		const initialStep = 1;
		const currentStep = ref( initialStep );
		const totalSteps = 4;
		return {
			currentStep,
			initialStep,
			totalSteps,
			userName,
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

		p {
			padding-bottom: @spacing-50;
		}

		&--italic {
			color: @color-placeholder;
			font-style: italic;
		}

		&__link {
			.cdx-mixin-link-base();
		}
	}
}
</style>
