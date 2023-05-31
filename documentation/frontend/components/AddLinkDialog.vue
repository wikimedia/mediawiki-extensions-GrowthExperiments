<template>
	<!-- eslint-disable vue/no-v-model-argument -->
	<onboarding-dialog
		v-model:open="wrappedOpen"
		v-model:is-checked="wrappedIsChecked"
		:total-steps="3"
		:initial-step="1"
		class="ext-growthExperiments-AddLinkDialog"
		:is-rtl="isRtl"
		@close="$emit( 'close', $event )"
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
				<!-- eslint-disable max-len -->
				<div
					role="img"
					aria-label="Illustration of the moon article, with the articles Earth and Satellite being suggested as links that could be added to the associated text."
					class="ext-growthExperiments-AddLinkDialog__image
					ext-growthExperiments-AddLinkDialog__image--1"
				>
				<!-- eslint-enable max-len -->
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
				<!-- eslint-disable max-len -->
				<div
					role="img"
					aria-label="Illustration of the moon article, next to the article there's a robot representing a machine suggestion - asking if a word on the Moon article should be linked to the Cheese article."
					class="ext-growthExperiments-AddLinkDialog__image
					ext-growthExperiments-AddLinkDialog__image--2"
				>
				<!-- eslint-enable max-len -->
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
						<!-- eslint-disable-next-line max-len -->
						{{
							$i18n(
								// eslint-disable-next-line max-len
								'growthexperiments-addlink-onboarding-content-about-suggested-links-body',
								userName
							).text()
						}}
					</p>

					<a class="ext-growthExperiments-AddLinkDialog__text__link" href="">
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
				<!-- eslint-disable max-len -->
				<div
					role="img"
					aria-label="Illustration of an article sentence showing link suggestions for two different words. Next to the suggestion there's a blue check icon and a red cross icon for the options to accept or reject the suggestion."
					class="
					ext-growthExperiments-AddLinkDialog__image
					ext-growthExperiments-AddLinkDialog__image--3"
				>
					<!-- eslint-enable max-len -->
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
			Don't show again
		</template>
		<template
			#startBtnText>
			Get started
		</template>
	</onboarding-dialog>
</template>

<script>
import { inject, toRef, ref } from 'vue';
import { useModelWrapper } from '@wikimedia/codex';
import OnboardingDialog from './OnboardingDialog.vue';

export default {
	compatConfig: { MODE: 3 },
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
		}
	},
	emits: [ 'update:open', 'update:is-checked', 'close' ],
	setup( props, { emit } ) {
		const isRtl = ref( false );
		const userName = inject( 'USER_USERNAME' );
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const wrappedIsChecked = useModelWrapper( toRef( props, 'isChecked' ), emit, 'update:is-checked' );
		return {
			isRtl,
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
