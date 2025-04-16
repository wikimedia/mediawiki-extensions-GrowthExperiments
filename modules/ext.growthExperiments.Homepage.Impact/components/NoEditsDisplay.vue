<template>
	<div :class="`ext-growthExperiments-NoEditsDisplay--${renderMode}`">
		<div
			v-if="renderMode !== 'mobile-summary'"
			class="ext-growthExperiments-NoEditsDisplay__scorecards"
			:class="`ext-growthExperiments-NoEditsDisplay__scorecards--${renderMode}`"
		>
			<c-score-card
				:icon="cdxIconUserTalk"
				:label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
				:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
				:info-header-icon="cdxIconInfoFilled"
				:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-label' ).text()"
				@open="$log( 'impact', 'open-thanks-info-tooltip' );"
				@close="$log( 'impact', 'close-thanks-info-tooltip' );"
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
						<p>
							{{ receivedThanksInfoText }}
						</p>
					</div>
				</template>
			</c-score-card>
			<c-score-card
				:icon="cdxIconChart"
				:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
				:icon-label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
				:info-header-icon="cdxIconInfoFilled"
				:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-streak-info-label' ).text()"
				@open="$log( 'impact', 'open-streak-info-tooltip' );"
				@close="$log( 'impact', 'close-streak-info-tooltip' );"
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
						<p>
							{{ longestEditingStreakFirstParagraph }}
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
					v-i18n-html:growthexperiments-homepage-impact-unactivated-subheader-subtext
					class="ext-growthExperiments-NoEditsDisplay__content__messages__subtext"
					:size="[ null, null, 'sm' ]"
					:weight="subtextFontWeight"
				>
				</c-text>
				<div
					v-if="!isDisabled && renderMode === 'mobile-overlay' || renderMode === 'mobile-details'"
					class="ext-growthExperiments-NoEditsDisplay__content__messages__cta"
				>
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
				{{ $i18n( 'growthexperiments-homepage-impact-unactivated-description' ).text() }}
			</c-text>
			<c-text
				v-else
				v-i18n-html:growthexperiments-homepage-impact-unactivated-suggested-edits-footer
				:size="footerFontSize"
				color="subtle"
			>
			</c-text>
		</div>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
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
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxButton,
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
		const renderThirdPerson = inject( 'RENDER_IN_THIRD_PERSON' );
		const maxEdits = inject( 'IMPACT_MAX_EDITS' );
		const maxThanks = inject( 'IMPACT_MAX_THANKS' );
		const onSuggestedEditsClick = () => {
			if ( !props.isActivated ) {
				mw.track( 'growthexperiments.startediting', {
					moduleName: 'impact',
					trigger: 'impact'
				} );
				return;
			}

			if ( renderMode === 'mobile-details' ) {
				window.location.href = mw.Title.newFromText( 'Special:Homepage/suggested-edits' ).getUrl();
				return;
			}

			window.history.replaceState( null, null, '#/homepage/suggested-edits' );
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		};
		return {
			renderMode,
			renderThirdPerson,
			onSuggestedEditsClick,
			cdxIconUserTalk,
			cdxIconChart,
			cdxIconInfoFilled,
			maxEdits,
			maxThanks
		};
	},
	computed: {
		subtextFontWeight() {
			return this.renderMode !== 'mobile-summary' ? 'bold' : null;
		},
		footerFontSize() {
			return this.renderMode !== 'desktop' ? 'sm' : null;
		},
		receivedThanksCount() {
			return this.data ?
				this.$filters.convertNumber( this.data.receivedThanksCount ) :
				NO_DATA_CHARACTER;
		},
		receivedThanksInfoText() {
			return this.renderThirdPerson ?
				this.$i18n(
					'growthexperiments-homepage-impact-scores-thanks-info-text-third-person',
					this.$filters.convertNumber( this.maxThanks )
				).text() :
				this.$i18n(
					'growthexperiments-homepage-impact-scores-thanks-info-text',
					'', // used to be the username
					this.$filters.convertNumber( this.maxThanks )
				).text();
		},
		longestEditingStreakFirstParagraph() {
			return this.renderThirdPerson ?
				this.$i18n(
					'growthexperiments-homepage-impact-scores-best-streak-info-text-third-person',
					this.$filters.convertNumber( this.maxEdits )
				).text() :
				this.$i18n(
					'growthexperiments-homepage-impact-scores-best-streak-info-text',
					'', // used to be the username
					this.$filters.convertNumber( this.maxEdits )
				).text();
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
@import 'mediawiki.skin.variables.less';
@import '../../utils/mixins.less';
@import '../../vue-components/mixins.less';

.ext-growthExperiments-NoEditsDisplay {
	&--desktop,
	&--mobile-summary {
		min-height: 320px;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}

	&--mobile-summary {
		min-height: 160px;
	}

	&--mobile-overlay {
		// negate the expanded margin-top in LayoutOverlay.vue
		margin-top: 16px;
	}

	&--mobile-details {
		margin-left: -16px;
		margin-right: -16px;
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
		&--mobile-details,
		&--mobile-overlay {
			flex-direction: column;
			align-items: center;
			padding-right: 16px;
			padding-left: 16px;
		}

		&__image {
			background: url( ../../../images/intro-heart-article.png ) no-repeat center;

			&--desktop,
			&--mobile-details,
			&--mobile-overlay {
				// TODO review spacing size and find or create token
				margin: 7px auto;
				width: 160px;
				height: 130px;
				background-size: cover;
			}

			&--mobile-summary {
				.filter( drop-shadow( 0 0 2px rgba( 0, 0, 0, 0.25 ) ) );
				min-width: 64px;
				background-size: contain;
			}
		}

		&__messages {
			&--desktop,
			&--mobile-details,
			&--mobile-overlay {
				text-align: center;
			}

			&--mobile-overlay {
				> * {
					margin-top: 16px;
				}
			}

			&--mobile-summary {
				margin-left: 1em;
			}

			&__cta {
				margin-top: 1em;
			}
		}
	}

	&__footer {
		background-color: @background-color-base;
		padding: 16px;

		&--desktop,
		&--mobile-summary {
			// expand white background over subtle gray background
			margin: 0 -16px -16px -16px;
		}

		&--mobile-overlay {
			position: fixed;
			bottom: 0;
			width: 100%;
		}
	}
}
</style>
