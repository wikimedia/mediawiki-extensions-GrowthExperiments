<template>
	<div
		v-if="currentIndex + 1 === index"
		class="ext-growthExperiments-ViewStep">
		<div class="ext-growthExperiments-ViewStep__image">
			<slot name="image"></slot>
		</div>
		<div class="ext-growthExperiments-ViewStep__text">
			<div class="ext-growthExperiments-ViewStep__text__title">
				<slot name="header"></slot>
			</div>
			<div class="ext-growthExperiments-ViewStep__text__paragraph">
				<slot name="main">
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
@import '../node_modules/@wikimedia/codex/mixins/link.less';
// stylelint-disable-next-line selector-class-pattern
.cdx-docs-link {
	.cdx-mixin-link();
}

.ext-growthExperiments-ViewStep {
	&__text {
		border-top: @border-width-base @border-style-base @border-color-base;
		padding-top: @spacing-100;
		padding-right: @spacing-100;
		padding-left: @spacing-100;
		min-height: 200px;
		font-size: @font-size-small;

		&__paragraph {
			color: @color-base;
			line-height: @line-height-xx-small;
			margin: 0.4em 0 0.5em 0;
		}
	}
}
</style>
