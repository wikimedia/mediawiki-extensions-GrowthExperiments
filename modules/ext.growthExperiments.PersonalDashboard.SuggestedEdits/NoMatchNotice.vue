<template>
	<cdx-message
		class="growthexperiments-nomatch-notice"
		:allow-user-dismiss="true"
		@user-dismissed="$emit( 'dismiss' )">
		<p
			v-i18n-html:growthexperiments-home-suggestededits-nomatch-notice
			class="growthexperiments-nomatch-notice__text"
		></p>
		<div class="growthexperiments-nomatch-notice__buttons">
			<cdx-button @click="$emit( 'select-interests' )">
				{{ interestsButtonLabel }}
			</cdx-button>
			<cdx-button @click="$emit( 'select-task-types' )">
				{{ taskTypesButtonLabel }}
			</cdx-button>
		</div>
	</cdx-message>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxMessage } = require( './codex.js' );

/**
 * Tells the user that no suggested edits match the selected interests and types
 * of edits (T443803). The parent opens the filter dialogs and saves the
 * dismissal, because it owns both the dialogs and the decision to show this
 * notice.
 */
module.exports = exports = defineComponent( {
	name: 'NoMatchNotice',
	components: { CdxButton, CdxMessage },
	emits: [ 'dismiss', 'select-interests', 'select-task-types' ],
	setup() {
		return {
			interestsButtonLabel: mw.msg( 'growthexperiments-home-suggestededits-nomatch-notice-interests-button' ),
			taskTypesButtonLabel: mw.msg( 'growthexperiments-home-suggestededits-nomatch-notice-tasktypes-button' ),
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.growthexperiments-nomatch-notice {
	margin-bottom: @spacing-75;

	// The two selectors below are nested to make them more specific than the
	// rules that they replace. The skin gives paragraphs a margin, for example
	// ".content p" in Minerva. Other modules also load ".cdx-button { margin: 0; }",
	// and they can load after this module.
	.growthexperiments-nomatch-notice__text {
		margin: @spacing-0;
	}

	.growthexperiments-nomatch-notice__buttons {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50;
		margin-top: @spacing-50;
	}
}
</style>
