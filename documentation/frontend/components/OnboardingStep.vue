<template>
	<div
		v-if="currentIndex + 1 === index"
		class="ext-growthExperiments-OnboardingStep"
	>
		<div v-if="$slots.image">
			<slot name="image"></slot>
		</div>
		<div>
			<h5 class="ext-growthExperiments-OnboardingStep__title">
				<slot name="content-title"></slot>
			</h5>
			<div class="ext-growthExperiments-OnboardingStep__text">
				<slot name="content-text">
				</slot>
			</div>
			<a
				v-if="learnMoreLink !== ''"
				class="cdx-docs-link "
				href="">{{ learnMoreLink }}</a>
		</div>
	</div>
</template>

<script>
export default {
	name: 'OnboardingStep',
	props: {

		index: {
			type: Number,
			default: 0
		},

		currentIndex: {
			type: Number,
			default: 0
		},
		learnMoreLink: {
			type: String,
			default: ''
		}

	}

};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
@import '../node_modules/@wikimedia/codex/dist/mixins/link.less';
@import './variables.less';
// stylelint-disable-next-line selector-class-pattern
.cdx-docs-link {
	.cdx-mixin-link();
}

.ext-growthExperiments-OnboardingStep {
	&__title {
		padding-top: @spacing-50;
		font-size: @font-size-medium;
		font-weight: @font-weight-bold;
	}

	&__text {
		padding-top: @spacing-50;
		font-size: @font-size-small;
		line-height: @line-height-xx-small;
	}
}
</style>
