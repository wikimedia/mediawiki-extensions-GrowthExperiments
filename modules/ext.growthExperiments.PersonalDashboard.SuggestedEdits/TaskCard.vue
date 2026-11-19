<template>
	<feed-card
		class="growthexperiments-suggested-edits__card"
		:url="url"
		:aria-label="task.title"
		:thumbnail="thumbnail"
		thumbnail-size="large"
		force-thumbnail
		@click.capture="showQualityGateFeedback"
	>
		<template #header>
			<span class="growthexperiments-suggested-edits__card__title">
				{{ task.title }}
			</span>
		</template>

		<template v-if="task.description" #description>
			{{ task.description }}
		</template>

		<template #supporting-text>
			<cdx-icon :icon="cdxIconLightbulb" size="small"></cdx-icon>
			{{ taskTypeName }}
		</template>
	</feed-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxIcon } = require( './codex.js' );
const { FeedCard } = require( 'ext.personalDashboard.common' );
const NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' );
const { cdxIconLightbulb } = require( './icons.json' );
const QualityGate = require( '../ext.growthExperiments.Homepage.SuggestedEdits/QualityGate.js' );

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
	emits: [ 'quality-gate-failed' ],
	setup( props, { emit } ) {
		function showQualityGateFeedback( event ) {
			// The dashboard does not fill the task store, so the gate uses the
			// config of the task.
			const qualityGate = QualityGate.newFromTask( props.task, {
				pageviewToken: mw.config.get( 'wgPersonalDashboardPageviewToken' ),
			} );
			const gate = qualityGate.getFailedGate( props.task.tasktype );
			if ( gate ) {
				event.preventDefault();
				qualityGate.logImpression( props.task.tasktype, gate );
				// The parent owns the dialog, so that all cards share one dialog.
				emit( 'quality-gate-failed', props.task.tasktype );
			}
		}
		return { cdxIconLightbulb, showQualityGateFeedback };
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
@import 'mediawiki.skin.variables.less';

// FeedCard has the card chrome: the whole-card link and the row layout. This file
// styles only what is specific to a suggested edit.
.growthexperiments-suggested-edits__card {
	// The design shows the task type in the progressive color, not the Codex gray.
	.cdx-card__text__supporting-text {
		&,
		.cdx-icon {
			color: @color-progressive;
		}
	}
}
</style>
