<template>
	<!-- eslint-disable vue/no-v-model-argument max-len-->
	<onboarding-dialog
		v-model:open="wrappedOpen"
		v-model:is-checked="wrappedIsChecked"
		:total-steps="totalSteps"
		:initial-step="initialStep"
		class="ext-growthExperiments-AddLinkDialog"
		:stepper-label="$i18n( 'growthexperiments-structuredtask-onboarding-dialog-progress', currentStep, totalSteps ).text()"
		@close="$emit( 'close', $event )"
		@update:current-step="( newVal )=> currentStep = newVal"
	>
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
				<div
					role="img"
					:aria-label="$i18n( 'growthexperiments-addlink-onboarding-content-intro-image-alt-text' ).text()"
					class="ext-growthExperiments-AddLinkDialog__image
					ext-growthExperiments-AddLinkDialog__image--1"
				>
				</div>
				<h5 class="ext-growthExperiments-AddLinkDialog__title">
					{{
						$i18n( 'growthexperiments-addlink-onboarding-content-intro-title' ).text()
					}}
				</h5>
				<div class="ext-growthExperiments-AddLinkDialog__text">
					<p>
						{{
							$i18n(
								// eslint-disable-next-line max-len
								'growthexperiments-addlink-onboarding-content-intro-body-paragraph1',
								userName
							).text()
						}}
					</p>
					<div
						class="ext-growthExperiments-AddLinkDialog__text__label">
						{{
							$i18n(
								// eslint-disable-next-line max-len
								'growthexperiments-addlink-onboarding-content-intro-body-example-label'
							).text()
						}}
					</div>
					<!-- eslint-disable-next-line max-len, vue/first-attribute-linebreak -->
					<div v-i18n-html:growthexperiments-addlink-onboarding-content-intro-body-example-text
						class="ext-growthExperiments-AddLinkDialog__text__example"
					>
					</div>
					<p>
						{{
							$i18n(
								'growthexperiments-addlink-onboarding-content-intro-body-paragraph2'
							).text()
						}}
					</p>
				</div>
			</div>
		</template>

		<template #step2>
			<div>
				<div
					role="img"
					:aria-label="$i18n( 'growthexperiments-addlink-onboarding-content-about-image-alt-text' ).text()"
					class="ext-growthExperiments-AddLinkDialog__image
					ext-growthExperiments-AddLinkDialog__image--2"
				>
				</div>
				<h5 class="ext-growthExperiments-AddLinkDialog__title">
					{{
						$i18n(
							// eslint-disable-next-line max-len
							'growthexperiments-addlink-onboarding-content-about-suggested-links-title'
						).text()
					}}
				</h5>
				<div class="ext-growthExperiments-AddLinkDialog__text">
					<p>
						{{
							$i18n(
								// eslint-disable-next-line max-len
								'growthexperiments-addlink-onboarding-content-about-suggested-links-body',
								userName
							).text()
						}}
					</p>
					<a
						v-if="learnMoreLink"
						class="ext-growthExperiments-AddLinkDialog__text__link"
						:href="learnMoreLink"
						target="_blank">
						{{
							$i18n(
								// eslint-disable-next-line max-len
								'growthexperiments-addlink-onboarding-content-about-suggested-links-body-learn-more-link-text'
							).text()
						}}
					</a>
				</div>
			</div>
		</template>
		<template #step3>
			<div>
				<div
					role="img"
					:aria-label="$i18n( 'growthexperiments-addlink-onboarding-content-linking-image-alt-text' ).text()"
					class="
					ext-growthExperiments-AddLinkDialog__image
					ext-growthExperiments-AddLinkDialog__image--3"
				>
				</div>
				<h5 class="ext-growthExperiments-AddLinkDialog__title">
					{{
						$i18n(
							// eslint-disable-next-line max-len
							'growthexperiments-addlink-onboarding-content-linking-guidelines-title'
						).text()
					}}
				</h5>
				<div class="ext-growthExperiments-AddLinkDialog__text">
					<div class="ext-growthExperiments-AddLinkDialog__text__list">
						<!-- eslint-disable-next-line max-len -->
						<ul v-i18n-html:growthexperiments-addlink-onboarding-content-linking-guidelines-body="[ userName ]">
						</ul>
					</div>
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
	name: 'AddLinkDialog',
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
		 * Url to show a "learn more" link in the second step
		 */
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
		const totalSteps = 3;
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

.ext-growthExperiments-AddLinkDialog {
	height: @onboardingDialogHeight;

	&__image {
		.ext-growthExperiments-onboarding-dialog-image();

		&--1 {
			background-image: url( ../../../images/addlink/onboarding-image1-ltr.svg );
		}

		&--2 {
			background-image: url( ../../../images/addlink/onboarding-image2-ltr.svg );
		}

		&--3 {
			background-image: url( ../../../images/addlink/onboarding-image3-ltr.svg );
		}
	}

	&__title {
		.ext-growthExperiments-onboarding-dialog-textcontent();
		.ext-growthExperiments-onboarding-dialog-title();
	}

	&__text {
		.ext-growthExperiments-onboarding-dialog-textcontent();
		.ext-growthExperiments-onboarding-dialog-text();

		&__label {
			color: @color-placeholder;
			font-size: @font-size-x-small;
			line-height: @line-height-xx-small;
			font-style: italic;
			margin-top: @spacing-50;
		}

		&__example {
			padding: @spacing-75;
			background: @background-color-interactive-subtle;
			// As discussed on https://phabricator.wikimedia.org/T332567
			// this color is not a DS border-color yet
			// and should be changed
			border: @border-width-base @border-style-base @onboardingExampleBorderColor;
			box-sizing: @box-sizing-base;
			border-radius: @border-radius-base;
			margin-bottom: @spacing-50;
			line-height: @line-height-xx-small;
			font-size: @font-size-small;

			// stylelint-disable selector-class-pattern
			& > mark.positive {
				background-color: @background-color-progressive-subtle;
			}
		}

		&__link {
			.cdx-mixin-link-base();
		}

		&__list {
			ul {
				list-style: inside;

				li {
					margin-top: @spacing-50;
				}
			}
		}
	}
}
</style>
