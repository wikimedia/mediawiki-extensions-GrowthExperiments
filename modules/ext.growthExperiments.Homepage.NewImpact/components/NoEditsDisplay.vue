<template>
	<div :class="`ext-growthExperiments-NoEditsDisplay--${renderMode}`">
		<div
			v-if="renderMode !== 'overlay-summary'"
			class="ext-growthExperiments-NoEditsDisplay__scorecards"
			:class="`ext-growthExperiments-NoEditsDisplay__scorecards--${renderMode}`"
		>
			<c-score-card
				:icon="cdxIconUserTalk"
				:label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
				:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
				:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-label' ).text()"
				@open="log( 'impact', 'open-thanks-info-tooltip' );"
				@close="log( 'impact', 'close-thanks-info-tooltip' );"
			>
				<c-text
					size="md"
					weight="bold"
				>
					<a
						v-if="data && data.receivedThanksCount > 0"
						:href="thanksUrl"
						class="ext-growthExperiments-NoEditsDisplay__scorecards__link"
						data-link-id="impact-thanks-log"
					>
						{{ receivedThanksCount }}
					</a>
					<span v-else>
						{{ $filters.convertNumber( 0 ) }}
					</span>
				</c-text>
				<template #info-content>
					<div class="ext-growthExperiments-ScoreCards__scorecard__info">
						<span>
							<cdx-icon
								class="ext-growthExperiments-ScoreCards__scorecard__info__icon"
								:icon="cdxIconInfoFilled"
							></cdx-icon>
							{{ $i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text() }}
						</span>
						<p>
							{{ $i18n( 'growthexperiments-homepage-impact-scores-thanks-info-text', userName ).text() }}
						</p>
					</div>
				</template>
			</c-score-card>
			<c-score-card
				:icon="cdxIconChart"
				:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
				:icon-label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
				:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-streak-info-label' ).text()"
				@open="log( 'impact', 'open-streak-info-tooltip' );"
				@close="log( 'impact', 'close-streak-info-tooltip' );"
			>
				<!-- &#8211; is the code for the en dash character: — -->
				<c-text
					as="span"
					size="md"
					weight="bold"
				>
					&#8211;
				</c-text>
				<template #info-content>
					<div class="ext-growthExperiments-ScoreCards__scorecard__info">
						<span>
							<cdx-icon
								class="ext-growthExperiments-ScoreCards__scorecard__info__icon"
								:icon="cdxIconInfoFilled"
							></cdx-icon>
							{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text() }}
						</span>
						<p>
							{{ $i18n( 'growthexperiments-homepage-impact-scores-best-streak-info-text', userName ).text() }}
						</p>
					</div>
				</template>
			</c-score-card>
		</div>
		<div
			class="ext-growthExperiments-NoEditsDisplay__content"
			:class="`ext-growthExperiments-NoEditsDisplay__content--${renderMode}`"
		>
			<div
				class="ext-growthExperiments-NoEditsDisplay__content__image"
				:class="`ext-growthExperiments-NoEditsDisplay__content__image--${renderMode}`"
			></div>
			<div :class="`ext-growthExperiments-NoEditsDisplay__content__messages--${renderMode}`">
				<c-text
					:size="[ 'xxl', 'xl', 'lg' ]"
					weight="bold"
				>
					{{ $i18n( 'growthexperiments-homepage-impact-unactivated-subheader-text' ).text() }}
				</c-text>
				<c-text
					v-i18n-html:growthexperiments-homepage-impact-unactivated-subheader-subtext="[ userName ]"
					class="ext-growthExperiments-NoEditsDisplay__content__messages__subtext"
					:size="[ null, null, 'sm' ]"
					:weight="subtextFontWeight"
				>
				</c-text>
				<div v-if="!isDisabled && renderMode === 'overlay'">
					<cdx-button
						data-link-id="impact-see-suggested-edits"
						weight="primary"
						action="progressive"
						@click="onSuggestedEditsClick"
					>
						{{ $i18n( 'growthexperiments-homepage-impact-unactivated-suggested-edits-link' ).text() }}
					</cdx-button>
				</div>
			</div>
		</div>
		<div
			class="ext-growthExperiments-NoEditsDisplay__footer"
			:class="`ext-growthExperiments-NoEditsDisplay__footer--${renderMode}`"
		>
			<c-text
				v-if="isDisabled"
				:size="footerFontSize"
				color="subtle"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-unactivated-description', userName ).text() }}
			</c-text>
			<c-text
				v-else
				v-i18n-html:growthexperiments-homepage-impact-unactivated-suggested-edits-footer="[ userName ]"
				:size="footerFontSize"
				color="subtle"
			>
			</c-text>
		</div>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const { CdxButton, CdxIcon } = require( '@wikimedia/codex' );
const {
	cdxIconUserTalk,
	cdxIconChart,
	cdxIconInfoFilled
} = require( '../../vue-components/icons.json' );
const CScoreCard = require( '../../vue-components/CScoreCard.vue' );
const CText = require( '../../vue-components/CText.vue' );
const { NO_DATA_CHARACTER } = require( '../constants.js' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon,
		CText,
		CScoreCard
	},
	props: {
		userName: {
			type: String,
			required: true
		},
		data: {
			type: Object,
			default: null
		},
		isDisabled: {
			type: Boolean,
			default: false
		},
		isActivated: {
			type: Boolean,
			default: false
		}
	},
	setup( props ) {
		const renderMode = inject( 'RENDER_MODE' );
		const log = inject( '$log' );
		const onSuggestedEditsClick = () => {
			if ( !props.isActivated ) {
				mw.track( 'growthexperiments.startediting', {
					moduleName: 'impact',
					trigger: 'impact'
				} );
				return;
			}

			window.history.replaceState( null, null, '#/homepage/suggested-edits' );
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		};
		return {
			log,
			renderMode,
			onSuggestedEditsClick,
			cdxIconUserTalk,
			cdxIconChart,
			cdxIconInfoFilled
		};
	},
	computed: {
		subtextFontWeight() {
			return this.renderMode !== 'overlay-summary' ? 'bold' : null;
		},
		footerFontSize() {
			return this.renderMode !== 'desktop' ? 'sm' : null;
		},
		receivedThanksCount() {
			return this.data ?
				this.$filters.convertNumber( this.data.receivedThanksCount ) :
				NO_DATA_CHARACTER;
		},
		thanksUrl() {
			return mw.util.getUrl( 'Special:Log', {
				type: 'thanks',
				page: this.userName
			} );
		}
	}
};
</script>

<style lang="less">
@import ( reference ) '../../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';
@import '../../utils/mixins.less';
@import '../../vue-components/mixins.less';

.ext-growthExperiments-NoEditsDisplay {
	&--desktop,
	&--overlay-summary {
		min-height: 320px;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}

	&--overlay-summary {
		min-height: 160px;
	}

	&--overlay {
		// negate the expanded margin-top in LayoutOverlay.vue
		margin-top: 16px;
	}

	&__scorecards {
		.scorecards-grid();

		&__link {
			.disabled-visited();
		}

		&--desktop {
			// Expand scores stripe over homepage modules padding
			margin: 0 -16px;
		}
	}

	&__content {
		display: flex;
		background-color: @background-color-interactive-subtle;
		padding-bottom: 16px;

		&--desktop {
			// Expand gray background over module padding
			margin: 0 -16px;
		}

		&--desktop,
		&--overlay {
			flex-direction: column;
			align-items: center;
			padding-right: 16px;
			padding-left: 16px;
		}

		&__image {
			background: url( ../../../images/intro-heart-article.png ) no-repeat center;

			&--desktop,
			&--overlay {
				// TODO review spacing size and find or create token
				margin: 7px auto;
				width: 160px;
				height: 130px;
				background-size: cover;
			}

			&--overlay-summary {
				.filter( drop-shadow( 0 0 2px rgba( 0, 0, 0, 0.25 ) ) );
				min-width: 64px;
				background-size: contain;
			}
		}

		&__messages {
			&--desktop,
			&--overlay {
				text-align: center;
			}

			&--overlay {
				> * {
					margin-top: 16px;
				}
			}

			&--overlay-summary {
				margin-left: 1em;
			}
		}
	}

	&__footer {
		background-color: @background-color-base;
		padding: 16px;

		&--desktop,
		&--overlay-summary {
			// expand white background over subtle gray background
			margin: 0 -16px -16px -16px;
		}

		&--overlay {
			position: fixed;
			bottom: 0;
			width: 100%;
		}
	}
}
</style>
