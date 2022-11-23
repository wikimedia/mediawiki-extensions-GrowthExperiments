<template>
	<div :class="`ext-growthExperiments-NoEditsDisplay--${renderMode}`">
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
const { CdxButton } = require( '@wikimedia/codex' );
const CText = require( '../../vue-components/CText.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CText
	},
	props: {
		userName: {
			type: String,
			required: true
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
			renderMode,
			onSuggestedEditsClick
		};
	},
	computed: {
		subtextFontWeight() {
			return this.renderMode !== 'overlay-summary' ? 'bold' : null;
		},
		footerFontSize() {
			return this.renderMode !== 'desktop' ? 'sm' : null;
		}
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';
@import '../../utils/mixins.less';

.ext-growthExperiments-NoEditsDisplay {
	&__content {
		display: flex;
		background-color: @background-color-framed;
		padding-bottom: 16px;

		&--desktop,
		&--overlay {
			flex-direction: column;
			align-items: center;
			padding-right: 16px;
			padding-left: 16px;
		}

		&--overlay {
			// negate the expanded margin-top in LayoutOverlay.vue
			margin-top: 16px;
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
