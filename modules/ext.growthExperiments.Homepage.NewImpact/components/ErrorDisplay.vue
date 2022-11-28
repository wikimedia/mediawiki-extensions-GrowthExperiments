<template>
	<div class="ext-growthExperiments-ErrorDisplay">
		<score-cards></score-cards>
		<div
			class="ext-growthExperiments-ErrorDisplay__display"
		>
			<c-text
				as="h3"
				weight="bold"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-error-data-not-found-text', userName ).text() }}
			</c-text>
			<div class="ext-growthExperiments-ErrorDisplay__image">
			</div>
			<c-text color="subtle" class="ext-growthExperiments-ErrorDisplay__subtext">
				{{ $i18n( 'growthexperiments-homepage-impact-error-data-not-found-subtext', userName ).text() }}
			</c-text>
		</div>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const ScoreCards = require( './ScoreCards.vue' );
const CText = require( '../../vue-components/CText.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		ScoreCards
	},
	setup() {
		const userName = inject( 'RELEVANT_USER_USERNAME' );
		return {
			userName
		};
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';

.ext-growthExperiments-ErrorDisplay {
	&__display {
		text-align: center;
		margin: 16px auto;

		&__subtext {
			max-width: 260px;
			margin: 0 auto;
		}
	}

	&__image {
		background-repeat: no-repeat;
		background-position: center;
		background-image: url( ../../../images/user-impact-data-error.svg );
		height: 120px;
		background-size: contain;
		margin: 16px auto;
	}
}
</style>
