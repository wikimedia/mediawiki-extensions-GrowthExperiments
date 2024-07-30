( function () {

	/**
	 * Filter out campaign topics for non-campaign users since campaign topics are included as a regular topic
	 * but should only be shown for users in the campaign
	 *
	 * @return {Object} Suggested edits topics, used in FiltersWidget and TopicSelectionWidget
	 */
	var getFormattedTopics = function () {
		var topicData = require( './Topics.json' ),
			topicsToExclude = mw.config.get( 'wgGETopicsToExclude' ) || [];
		topicsToExclude.forEach( ( topic ) => {
			delete topicData[ topic ];
		} );
		return topicData;
	};

	module.exports = getFormattedTopics();
}() );
