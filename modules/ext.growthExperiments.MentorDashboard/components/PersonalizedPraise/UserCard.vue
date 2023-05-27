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
					getNumberOfTalkPagePosts() !== undefined ? $filters.convertNumber( getNumberOfTalkPagePosts() ) : '...'
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
		weight="primary"
		action="progressive"
		@click="onPraiseButtonClicked"
	>
		{{ $i18n( 'growthexperiments-mentor-dashboard-personalized-praise-send-appreciation' ) }}
	</cdx-button>
	<skip-mentee-dialog :mentee="mentee" @skip="onMenteeSkipped"></skip-mentee-dialog>
</template>

<script>
const { inject } = require( 'vue' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconUserAvatar } = require( '../../../vue-components/icons.json' );
const CScoreCards = require( '../../../vue-components/CScoreCards.vue' );
const SkipMenteeDialog = require( './SkipMenteeDialog.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon,
		CScoreCards,
		SkipMenteeDialog
	},
	props: {
		mentee: { type: Array, required: true }
	},
	emits: [ 'skip' ],
	setup() {
		const log = inject( '$log' );

		return {
			log,
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

			const flowStatusByUsername = mw.config.get( 'GEPraiseworthyMenteesByFlowStatus' );
			const talkPageName = ( new mw.Title( this.mentee.userName, 3 ) ).getPrefixedText();

			if ( flowStatusByUsername[ this.mentee.userName ] ) {
				new mw.Api().get( {
					action: 'flow',
					format: 'json',
					submodule: 'view-topiclist',
					page: talkPageName,
					formatversion: 2
				} ).then( ( data ) => {
					this.numberOfTalkPagePosts = data.flow[ 'view-topiclist' ].result.topiclist.roots.length;
				} );
			} else {
				new mw.Api().get( {
					action: 'parse',
					page: talkPageName,
					formatversion: 2
				} ).then( ( data ) => {
					const talkPosts = data.parse.text.match( / mw-heading2 /g );
					// NOTE: When there is no 2nd level heading, .match() can return null.
					this.numberOfTalkPagePosts = talkPosts !== null ? talkPosts.length : 0;
				} ).catch( ( error ) => {
					if ( error === 'missingtitle' ) {
						this.numberOfTalkPagePosts = 0;
					} else {
						mw.log.error( error );
					}
				} );
			}

			return this.numberOfTalkPagePosts;
		},
		onPraiseButtonClicked() {
			const userName = this.mentee.userName;
			this.log( 'pp-praise-mentee', {
				menteeUserId: this.mentee.userId
			} );
			return new mw.Api().postWithToken( 'csrf', {
				action: 'growthinvalidatepersonalizedpraisesuggestion',
				mentee: userName,
				reason: 'praised'
			} ).then( function () {
				// redirect the user
				window.location.href = ( new mw.Title( userName, 3 ) ).getUrl() + '?' + $.param( {
					action: 'edit',
					section: 'new',
					dtpreload: 1,
					preloadtitle: mw.config.get( 'GEPraiseworthyMessageSubject' ),
					preload: mw.config.get( 'GEPraiseworthyMessageTitle' ),
					preloadparams: [
						userName,
						mw.user.getName()
					]
				} );
			} ).catch( function ( error ) {
				mw.notify(
					mw.msg( 'growthexperiments-mentor-dashboard-personalized-praise-send-appreciation-error-unknown' ),
					{ type: 'error' }
				);
				mw.log.error( error );
			} );
		},
		onMenteeSkipped( reason ) {
			return new mw.Api().postWithToken( 'csrf', {
				action: 'growthinvalidatepersonalizedpraisesuggestion',
				mentee: this.mentee.userName,
				reason: 'skipped',
				skipreason: reason
			} ).then( () => {
				// Pass the event up to PersonalizedPraise.vue, to remove the mentee w/o the need for a reload
				this.$emit( 'skip', this.mentee, reason );
			} );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

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
