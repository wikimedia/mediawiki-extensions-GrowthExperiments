<template>
	<div class="ext-growthExperiments-OnboardingDialogDemo" :dir="readingDirection">
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
			:is-rtl="readingDirection === 'rtl'"
			@close="onDialogClose"
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
					<p
						class="
						ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph"
					>
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
						nulla ullam expedita consectetur.
					</p>

					<p
						class="
						ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__example"
					>
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
						nulla ullam expedita consectetur.
					</p>
					<br><br>
					<p
						class="
						ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__paragraph"
					>
						Lorem ipsum dolor sit amet consectetur adipisicing elit.
						Voluptatum necessitatibus nostrum vitae doloribus nisi itaque quasi
						nihil nam eum magni aliquam distinctio, commodi, dolore quibusdam
						nulla ullam expedita consectetur.
					</p>
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
			<template #step3>
				<div>
					<!-- eslint-disable max-len -->
					<div
						class="ext-growthExperiments-OnboardingDialogDemo__dialog__image"
						role="img"
						aria-label="Illustration of the moon article, with the articles Earth and Satellite being suggested as links that could be added to the associated text."
					>
					<!-- eslint-enable max-len -->
					</div>
					<div class="ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent"
					>
						<h5
							class="
						ext-growthExperiments-OnboardingDialogDemo__dialog__textcontent__title"
						>
							Step 3 This is an step with an image
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
				</div>
			</template>
			<template #checkboxLabel>
				Don't show again
			</template>
		</onboarding-dialog>
	</div>
	<direction-radio-selector
		@update:reading-direction="( newVal ) => readingDirection = newVal"
	>
	</direction-radio-selector>
</template>

<script>
import { ref } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import OnboardingDialog from '../../components/OnboardingDialog.vue';
import DirectionRadioSelector from '../../components/DirectionRadioSelector.vue';

export default {
	name: 'OnboardingDialogDemo',

	components: {
		CdxButton,
		DirectionRadioSelector,
		OnboardingDialog
	},
	setup() {
		const open = ref( false );
		const isDontShowAgainChecked = ref( false );
		const readingDirection = ref( 'ltr' );

		function onDialogClose( result ) {
			// eslint-disable-next-line no-console
			console.log( 'Dialog closed', result );
		}

		return {
			isDontShowAgainChecked,
			open,
			onDialogClose,
			readingDirection
		};
	}
};
</script>

<style lang="less">
@import '../../components/variables.less';
@import '../../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

.ext-growthExperiments-OnboardingDialogDemo {
	border: @border-width-base @border-style-base;
	padding: @spacing-50;
	margin: @spacing-50;

	&__dialog {
		&__image {
			background-image: url( ../../../../images/addlink/onboarding-image1-ltr.svg );
			background-color: @onboardingBackgroundColor;
			height: @size-1600;
			background-repeat: no-repeat;
			background-position: center;
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
				}

				&__link {
					.cdx-mixin-link-base();
				}

				&__list {
					ul {
						list-style: inside;
						margin-left: @spacing-50;

						li {
							margin-top: @spacing-50;
						}
					}
				}
			}
		}
	}
}
</style>
