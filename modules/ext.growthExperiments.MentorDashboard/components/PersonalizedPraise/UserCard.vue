<template>
	<div class="ext-growthExperiments-PersonalizedPraise-UserInfo">
		<div class="ext-growthExperiments-PersonalizedPraise-UserInfo-Username">
			<cdx-icon class="ext-growthExperiments-PersonalizedPraise-UserInfo-Username__icon" :icon="cdxIconUserAvatar">
			</cdx-icon>
			<a class="ext-growthExperiments-PersonalizedPraise-UserInfo-Username__userpage" :href="userPageHref">
				{{ mentee.userName }}
			</a>
		</div>
		<p class="ext-growthExperiments-PersonalizedPraise-UserInfo-Footer">
			<a
				class="ext-growthExperiments-PersonalizedPraise-UserInfo-Footer__talk"
				:href="userTalkHref"
			>
				{{ $i18n(
					'growthexperiments-mentor-dashboard-personalized-praise-talk-topics',
					getNumberOfTalkPagePosts() !== undefined ? getNumberOfTalkPagePosts() : '...'
				) }}
			</a>
		</p>
	</div>
	<c-score-cards
		:user-name="mentee.userName"
		:data="mentee"
		render-third-person="true"
	></c-score-cards>
	<cdx-button
		class="ext-growthExperiments-PersonalizedPraise__praise_button"
		@click="onPraiseButtonClicked"
	>
		{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-send-appreciation' ) }}
	</cdx-button>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconUserAvatar } = require( '../../../vue-components/icons.json' );
const CScoreCards = require( '../../../vue-components/CScoreCards.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon,
		CScoreCards
	},
	props: {
		mentee: { type: Array, required: true }
	},
	setup() {
		return {
			cdxIconUserAvatar
		};
	},
	data() {
		return {
			numberOfTalkPagePosts: undefined
		};
	},
	computed: {
		userPageHref() {
			return ( new mw.Title( this.mentee.userName, 2 ) ).getUrl();
		},
		userTalkHref() {
			return ( new mw.Title( this.mentee.userName, 3 ) ).getUrl();
		}
	},
	methods: {
		getNumberOfTalkPagePosts() {
			// NOTE: This is not wrapper by numberOfTalkPagePosts !== undefined, to ensure it re-runs when
			// the username changes. Unfortunately, this means the API request currently runs twice.
			new mw.Api().get( {
				action: 'parse',
				page: ( new mw.Title( this.mentee.userName, 3 ) ).getPrefixedText(),
				formatversion: 2
			} ).then( ( data ) => {
				this.numberOfTalkPagePosts = data.parse.text.match( / mw-heading2 /g ).length;
			} ).catch( ( error ) => {
				if ( error === 'missingtitle' ) {
					this.numberOfTalkPagePosts = 0;
				}
			} );

			return this.numberOfTalkPagePosts;
		},
		onPraiseButtonClicked() {
			const userName = this.mentee.userName;
			return new mw.Api().postWithToken( 'csrf', {
				action: 'growthinvalidatepersonalizedpraisesuggestion',
				mentee: userName
			} ).then( function () {
				// redirect the user
				window.location.href = ( new mw.Title( userName, 3 ) ).getUrl() + '?' + $.param( {
					action: 'edit',
					section: 'new',
					dtpreload: 1,
					preloadtitle: mw.config.get( 'GEPraiseworthyMessageSubject' ),
					preload: mw.config.get( 'GEPraiseworthyMessageTitle' ),
					'preloadparams[]': userName
				} );
			} ).catch( function () {
				mw.notify(
					mw.msg( 'growthexperiments-mentor-dashboard-personalized-praise-send-appreciation-error-unknown' ),
					{ type: 'error' }
				);
			} );
		}
	}
};
</script>

<style lang="less">
@import ( reference ) '../../../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';

.ext-growthExperiments-PersonalizedPraise {
	&-UserInfo {
		&-Username {
			&__icon {
				width: 40px;
				height: 40px;
				opacity: 0.65;
				margin-right: 1em;
			}

			&__userpage {
				font-size: 1.2em;
			}
		}
	}

	&__praise_button {
		margin: 16px 0;
	}
}
</style>
