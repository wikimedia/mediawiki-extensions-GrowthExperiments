/**
 * Send the time when the user can use the onboarding UI to Test Kitchen.
 *
 * @param {mw.testKitchen.ExperimentInterface} experiment
 * @param {number} readyMs Milliseconds from the navigation start until the user can act
 */
function sendOnboardingReadyEvent( experiment, readyMs ) {
	experiment.send(
		'welcome_survey_account_setup_ready',
		{
			// eslint-disable-next-line camelcase
			action_context: JSON.stringify( {
				// eslint-disable-next-line camelcase
				ready_ms: Math.round( readyMs ),
			} ),
		},
	);
}

module.exports = sendOnboardingReadyEvent;
