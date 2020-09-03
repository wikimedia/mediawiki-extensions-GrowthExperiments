( function () {

	/**
	 * @param {string} difficulty
	 * @param {string} timeestimate
	 * @return {jQuery}
	 */
	function getDifficultyAndTime( difficulty, timeestimate ) {
		return $( '<div>' ).addClass( 'suggested-edits-taskexplanation-difficulty-and-time' ).html(
			$( '<div>' ).addClass( 'suggested-edits-difficulty-time-estimate' ).append(
				$( '<div>' ).addClass( 'suggested-edits-difficulty-indicator' )
					.addClass( 'suggested-edits-difficulty-indicator-' + difficulty )
					// The following messages are used here:
					// * growthexperiments-homepage-suggestededits-difficulty-indicator-label-easy
					// * growthexperiments-homepage-suggestededits-difficulty-indicator-label-medium
					// * growthexperiments-homepage-suggestededits-difficulty-indicator-label-hard
					.text( mw.message(
						'growthexperiments-homepage-suggestededits-difficulty-indicator-label-' +
						difficulty
					) ),
				$( '<div>' )
					.addClass(
						'suggested-edits-difficulty-level suggested-edits-difficulty-level-' +
						difficulty
					)
					.text( timeestimate )
			) );
	}

	/**
	 * Provides a component that shows the suggested edit task type with a short
	 * description, time estimate and the suggested edits icon. Currently used
	 * in the "mobile peek" feature as well as the help panel's suggested edits
	 * subpanel.
	 *
	 * @param {string} wrapperClass
	 *   The CSS class name to use for wrapping the component.
	 * @param {Object} messages
	 *   An object of i18n strings.
	 * @param {string} messages.name
	 *   The i18n string name to use for the heading.
	 * @param {string} messages.timeestimate
	 *   The i18n string name to use for the time estimate on a task type.
	 * @param {string} difficulty
	 *   The difficulty level for the current task, e.g. 'easy', 'medium', 'hard'
	 * @return {jQuery}
	 */
	function getSuggestedEditsPeek( wrapperClass, messages, difficulty ) {
		return $( '<div>' ).addClass( wrapperClass )
			.append(
				$( '<div>' ).addClass( 'suggested-edits-header-text' )
					.append(
						$( '<h4>' )
							.addClass( 'suggested-edits-task-explanation-heading' )
							.text( messages.name ),
						getDifficultyAndTime( difficulty, messages.timeestimate )
					)
			).append( $( '<div>' ).addClass( 'suggested-edits-icon' ) );
	}

	module.exports = {
		getSuggestedEditsPeek: getSuggestedEditsPeek,
		getDifficultyAndTime: getDifficultyAndTime
	};

}() );
