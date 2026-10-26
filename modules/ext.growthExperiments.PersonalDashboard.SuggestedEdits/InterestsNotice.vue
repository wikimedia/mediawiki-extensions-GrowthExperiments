<template>
	<cdx-message
		class="growthexperiments-interests-notice"
		:icon="icon"
		:allow-user-dismiss="true"
		@user-dismissed="$emit( 'dismiss' )">
		<p class="growthexperiments-interests-notice__text">
			{{ text }}
		</p>
		<cdx-button
			class="growthexperiments-interests-notice__button"
			@click="$emit( 'select-interests' )">
			{{ buttonLabel }}
		</cdx-button>
	</cdx-message>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxMessage } = require( './codex.js' );
const { cdxIconConfigure } = require( './icons.json' );

/**
 * Asks a user with no interests to select some (T441150). The parent opens the
 * interest selector and saves the dismissal, because it owns both the dialog
 * and the decision to show this notice.
 */
module.exports = exports = defineComponent( {
	name: 'InterestsNotice',
	components: { CdxButton, CdxMessage },
	emits: [ 'dismiss', 'select-interests' ],
	setup() {
		return {
			// The same icon as the "Interests" item in the module header menu.
			icon: cdxIconConfigure,
			text: mw.msg( 'growthexperiments-home-suggestededits-interests-notice' ),
			buttonLabel: mw.msg( 'growthexperiments-home-suggestededits-interests-notice-button' ),
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.growthexperiments-interests-notice {
	margin-bottom: @spacing-75;

	// The two selectors below are nested to make them more specific than the
	// rules that they replace. The skin gives paragraphs a margin, for example
	// ".content p" in Minerva. Other modules also load ".cdx-button { margin: 0; }",
	// and they can load after this module.
	.growthexperiments-interests-notice__text {
		margin: @spacing-0;
	}

	.growthexperiments-interests-notice__button {
		margin-top: @spacing-50;
	}
}
</style>
