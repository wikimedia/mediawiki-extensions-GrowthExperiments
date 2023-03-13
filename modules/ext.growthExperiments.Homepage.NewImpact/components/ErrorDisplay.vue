<template>
	<div class="ext-growthExperiments-ErrorDisplay">
		<c-score-cards :render-third-person="renderThirdPerson" :user-name="userName"></c-score-cards>
		<div
			class="ext-growthExperiments-ErrorDisplay__display"
		>
			<c-text
				as="h3"
				weight="bold"
			>
				{{ notFoundText }}
			</c-text>
			<div class="ext-growthExperiments-ErrorDisplay__image">
			</div>
			<c-text color="subtle" class="ext-growthExperiments-ErrorDisplay__subtext">
				{{ notFoundSubtext }}
			</c-text>
		</div>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const CScoreCards = require( '../../vue-components/CScoreCards.vue' );
const CText = require( '../../vue-components/CText.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		CScoreCards
	},
	setup() {
		const userName = inject( 'RELEVANT_USER_USERNAME' );
		const renderThirdPerson = inject( 'RENDER_IN_THIRD_PERSON' );
		return {
			userName,
			renderThirdPerson
		};
	},
	computed: {
		notFoundText() {
			return this.renderThirdPerson ?
				this.$i18n( 'growthexperiments-homepage-impact-error-data-not-found-text-third-person' ).text() :
				this.$i18n( 'growthexperiments-homepage-impact-error-data-not-found-text', this.userName ).text();
		},
		notFoundSubtext() {
			return this.renderThirdPerson ?
				this.$i18n( 'growthexperiments-homepage-impact-error-data-not-found-subtext-third-person' ).text() :
				this.$i18n( 'growthexperiments-homepage-impact-error-data-not-found-subtext', this.userName ).text();
		}
	}
};
</script>

<style lang="less">
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
