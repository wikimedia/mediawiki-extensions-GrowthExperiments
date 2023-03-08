<template>
	<!-- eslint-disable vue/no-v-model-argument -->
	<onboarding-dialog
		v-model:open="open"
		v-model="modelValue"
		:total-steps="3"
		:initial-step="0"
		@default="open = false"

	>
		<template #title>
			Introduction
		</template>
		<template #headerbtntext>
			Skip all
		</template>
		<template #body="{ currentIndex }">
			<onboarding-step

				:current-index="currentIndex"
				:index="1"
			>
				<template #image>
					<div
						class="
							ext-growthExperiments-AddLinkDialog__image
							ext-growthExperiments-AddLinkDialog__image-1">
					</div>
				</template>
				<template #header>
					<h5 class="ext-growthExperiments-AddLinkDialog__text__title">
						Adding links will help people learn faster.
					</h5>
				</template>
				<template #main>
					<p>
						You will decide whether words in one Wikipedia article should link
						to other Wikipedia articles.
					</p>
					<div
						class="ext-growthExperiments-AddLinkDialog__text__label">
						Example sentence
					</div>
					<div
						class="
							ext-growthExperiments-AddLinkDialog__text__example">
						The moon is the only
						<!-- eslint-disable-next-line max-len -->
						<mark class="ext-growthExperiments-AddLinkDialog__text__example__highlighted">
							natural satellite</mark> that
						<mark
							class="
						ext-growthExperiments-AddLinkDialog__text__example__highlighted">
							orbits</mark> around the
						<mark
							class="
						ext-growthExperiments-AddLinkDialog__text__example__highlighted">
							Earth</mark>.
					</div>
					<p>
						No special knowledge about the article is needed to do this task.
					</p>
				</template>
			</onboarding-step>
			<onboarding-step
				learn-more-link="Learn more about machine suggestions"
				:index="2"
				:current-index="currentIndex">
				<template #image>
					<div
						class="
							ext-growthExperiments-AddLinkDialog__image
							ext-growthExperiments-AddLinkDialog__image-2">
					</div>
				</template>
				<template #header>
					<h5 class="ext-growthExperiments-AddLinkDialog__text__title">
						Suggested links are machine-generated, and can be incorrect.
					</h5>
				</template>
				<template #main>
					<!-- eslint-disable-next-line max-len -->
					<p>The suggestions might be on words that don’t need them, or might link to the wrong article. Use your judgment to decide whether they are right or wrong.</p>
				</template>
			</onboarding-step>

			<onboarding-step

				:index="3"
				:current-index="currentIndex">
				<template #image>
					<div
						class="
							ext-growthExperiments-AddLinkDialog__image
							ext-growthExperiments-AddLinkDialog__image-3">
					</div>
				</template>
				<template #header>
					<h5 class="ext-growthExperiments-AddLinkDialog__text__title">
						Guidelines
					</h5>
				</template>
				<template #main>
					<div class="ext-growthExperiments-AddLinkDialog__text__list">
						<ul>
							<li>Link concepts that a reader might want to learn more about.</li>
							<li>Make sure the link is going to the right article.</li>
							<li>Don't link common words, years, or dates.</li>
							<li>If you're not sure, skip.</li>
						</ul>
					</div>
				</template>
			</onboarding-step>
		</template>
		<template #checkbox>
			Don't show again
		</template>
		<template #last-step-button-text>
			Get started
		</template>
	</onboarding-dialog>
</template>

<script>
import { ref } from 'vue';
import OnboardingDialog from './OnboardingDialog.vue';

import OnboardingStep from './OnboardingStep.vue';

export default {
	name: 'AddLinkDialog',
	components: {
		OnboardingDialog,
		OnboardingStep

	},
	props: {

	},

	setup() {
		const modelValue = ref( false );
		return {
			modelValue,
			open
		};
	}
};
</script>

<style lang="less">
	@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
	@import './variables.less';

	.ext-growthExperiments-AddLinkDialog {
		&__image {
			height: 216px;
			background-repeat: no-repeat;
			background-position: center;
			background-color: @onboardingBackgroundColor;

			&-1 {
				background-image: url( ../../../images/addlink/onboarding-image1-ltr.svg );
			}

			&-2 {
				background-image: url( ../../../images/addlink/onboarding-image2-ltr.svg );
			}

			&-3 {
				background-image: url( ../../../images/addlink/onboarding-image3-ltr.svg );
			}
		}

		&__text {
			&__title {
				font-size: @font-size-medium;
				line-height: @line-height-xx-small;
				font-weight: @font-weight-bold;
			}

			&__label {
				color: @color-placeholder;
				font-size: @font-size-x-small;
				line-height: @line-height-xx-small;
				font-style: italic;
				margin-top: @spacing-50;
			}

			&__example {
				padding: 12px;
				background: @background-color-interactive-subtle;
				// As discussed on https://phabricator.wikimedia.org/T332567
				// this color is not a DS border-color yet
				// and should be changed
				border: 1px solid @onboardingExampleBorderColor;
				box-sizing: border-box;
				border-radius: @border-radius-base;
				margin-bottom: @spacing-50;
				line-height: @line-height-xx-small;
				font-size: @font-size-small;

				&__highlighted {
					background-color: @background-color-progressive-subtle;
				}
			}

			&__list {
				ul {
					list-style: inside;
					margin-left: @spacing-50;

					li {
						margin-top: 8px;
					}
				}
			}
		}
	}

</style>
