( function () {

	/**
	 * Filter out GLAM topics for non-GLAM users since GLAM topics are included as a regular topic
	 * but should only be shown for users in the campaign
	 *
	 * FIXME remove when GLAM campaign is over
	 *
	 * @return {Object} Suggested edits topics, used in FiltersWidget and TopicSelectionWidget
	 */
	var getFormattedTopics = function () {
		var topicData = require( './Topics.json' ),
			topicsToExclude = mw.config.get( 'wgGETopicsToExclude' ) || [];
		topicsToExclude.forEach( function ( topic ) {
			delete topicData[ topic ];
		} );
		return topicData;
	};

	module.exports = getFormattedTopics();
}() );
