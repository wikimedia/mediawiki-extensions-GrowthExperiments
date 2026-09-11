<template>
	<feed-card
		class="growthexperiments-suggested-edits__card"
		:url="url"
		:aria-label="task.title"
		:thumbnail="thumbnail"
		force-thumbnail
	>
		<template #header>
			<span class="growthexperiments-suggested-edits__card__title">
				{{ task.title }}
			</span>
		</template>

		<template #meta>
			<cdx-icon :icon="cdxIconLightbulb" size="small"></cdx-icon>
			{{ taskTypeName }}
		</template>

		<template v-if="task.description" #description>
			{{ task.description }}
		</template>
	</feed-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxIcon } = require( './codex.js' );
const { FeedCard } = require( 'ext.personalDashboard.common' );
const NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' );
const { cdxIconLightbulb } = require( './icons.json' );

const newcomerTaskLogger = new NewcomerTaskLogger();

// The exports alias is what lets vue3-jest attach the compiled render
// function, so we can mount this in a test at all.
module.exports = exports = defineComponent( {
	name: 'TaskCard',
	components: { CdxIcon, FeedCard },
	props: {
		// The whole task, rather than its fields spread as props: GrowthTasksApi
		// lazy-loads several of them, so the set a card receives varies.
		task: { type: Object, required: true },
		// Rank within the feed, which is the relevance order the suggester
		// returned; the NewcomerTask schema records it as ordinal_position.
		position: { type: Number, default: 0 },
	},
	setup() {
		return { cdxIconLightbulb };
	},
	computed: {
		// null rather than omitted where the article has no image: paired with
		// force-thumbnail, that is what gets the placeholder, so a column of
		// cards keeps one left edge whether or not the articles have images.
		thumbnail() {
			return this.task.thumbnailSource ? { url: this.task.thumbnailSource } : null;
		},
		url() {
			/*
			 * task.url is set only where article URL overrides are configured
			 * ($wgGENewcomerTasksRemoteArticleOrigin), which is development
			 * setups. It points at another wiki, where the parameters below
			 * would mean nothing.
			 */
			if ( this.task.url ) {
				return this.task.url;
			}
			const params = {
				geclickid: mw.config.get( 'wgPersonalDashboardPageviewToken' ),
				getasktype: this.task.tasktype,
				genewcomertasktoken: this.task.token,
				// Brings the help panel whether or not the preference is on.
				// Only load-bearing on the fallback: Special:Homepage sets it itself.
				gesuggestededit: 1,
			};
			// A Test Kitchen enrollment override has to survive the trip, or the
			// article sees a different group than the dashboard did.
			const mpo = new URLSearchParams( window.location.search ).get( 'mpo' );
			if ( mpo !== null ) {
				params.mpo = mpo;
			}
			/*
			 * We hand the rest to Special:Homepage, which resolves the editing
			 * context the task type wants, counts the click and redirects. A
			 * task can carry a null pageId, so fall back to the article itself:
			 * that still starts a session, it just goes uncounted.
			 */
			if ( this.task.pageId ) {
				return mw.util.getUrl( 'Special:Homepage/newcomertask/' + this.task.pageId, params );
			}
			return mw.util.getUrl( this.task.title, params );
		},
		taskTypeName() {
			// The following messages are used here:
			// * growthexperiments-homepage-suggestededits-tasktype-name-copyedit
			// * growthexperiments-homepage-suggestededits-tasktype-name-expand
			// * growthexperiments-homepage-suggestededits-tasktype-name-image-recommendation
			// * growthexperiments-homepage-suggestededits-tasktype-name-link-recommendation
			// * growthexperiments-homepage-suggestededits-tasktype-name-links
			// * growthexperiments-homepage-suggestededits-tasktype-name-references
			// * growthexperiments-homepage-suggestededits-tasktype-name-revise-tone
			// * growthexperiments-homepage-suggestededits-tasktype-name-section-image-recommendation
			// * growthexperiments-homepage-suggestededits-tasktype-name-update
			return mw.msg(
				'growthexperiments-homepage-suggestededits-tasktype-name-' + this.task.tasktype,
			);
		},
	},
	mounted() {
		// The card rendering is the impression, logged once per task the way the
		// Homepage module's carousel logs one at first sight. Growing the list
		// from the summary to the full view keys the existing cards unchanged, so
		// they are not remounted; the logger's own stamp on the task covers the
		// rest.
		newcomerTaskLogger.log( this.task, this.position );
	},
} );
</script>

<style lang="less">
// The card chrome (the whole-card link, the visited state and the row layout)
// lives in FeedCard; only what is specific to a suggested edit is here.
.growthexperiments-suggested-edits__card {
	// The design reads title, then description, then the task type, where
	// FeedCard stacks meta above description. Reordered here rather than
	// there: the text-only feeds want the order FeedCard already has.
	.personal-dashboard-feed__card__meta {
		order: 1;
	}
}
</style>
