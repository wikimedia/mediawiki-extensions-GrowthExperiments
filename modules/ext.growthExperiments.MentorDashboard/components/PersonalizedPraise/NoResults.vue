<template>
	<div class="ext-growthExperiments-NoResults">
		<div class="ext-growthExperiments-NoResults__image">
		</div>
		<p>
			{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-empty-state-footer' ) }}
		</p>
	</div>
</template>

<script>

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' }
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import '../../../utils/mixins.less';

.ext-growthExperiments-PersonalizedPraise .ext-growthExperiments-NoResults {
	&__image {
		background-color: @background-color-interactive-subtle;
		background-repeat: no-repeat;
		background-position: center;
		background-image: url( ../../../../images/personalised-praise-empty-state.svg );
		height: 210px;
		background-size: auto;
		margin-bottom: 16px;
	}

	p {
		.no-gutter();
	}
}
</style>
