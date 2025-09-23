( function () {
	'use strict';

	/**
	 * Keep track of the state of the selected recommendation
	 * This is used to animate the annotation view from different states.
	 * The annotation view cannot be updated. When the recommendation's acceptance state changes,
	 * a new view is rendered with the new state (the old view is destroyed) so animating
	 * state changes requires the old state to be re-constructed first. Additionally, views of the
	 * same type in the paragraph are re-rendered as well, so AnnotationAnimation also tracks which
	 * recommendation is selected so that ceRecommendedLinkAnnotation can determine upon
	 * construction whether to animate state changes.
	 *
	 * @type {Object}
	 */
	let state = {};

	/**
	 * Get the prior state of the selected annotation
	 *
	 * @return {Object}
	 */
	function getLastState() {
		return state;
	}

	/**
	 * Track the state of the selected annotation
	 *
	 * @param {Object} lastState
	 * @param {string} lastState.recommendationWikitextOffset WikitextOffset of the selection
	 * @param {string} lastState.oldState Prior acceptance state of the selection
	 * @param {boolean} [lastState.isDeselect] Whether the current change is a deselection
	 */
	function setLastState( lastState ) {
		state = lastState;
	}

	/**
	 * Un-track the state of the selected annotation
	 */
	function clearLastState() {
		state = {};
	}

	module.exports = {
		getLastState: getLastState,
		setLastState: setLastState,
		clearLastState: clearLastState,
	};
}() );
