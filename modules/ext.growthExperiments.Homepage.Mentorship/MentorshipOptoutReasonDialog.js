( function () {
	'use strict';

	var SelectWithTextInputWidget = require( '../ui-components/SelectWithTextInputWidget.js' ),
		HomepageModuleLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		);

	/**
	 * @class
	 *
	 * @param {Object} config
	 * @constructor
	 */
	function MentorshipOptoutReasonDialog( config ) {
		MentorshipOptoutReasonDialog.super.call( this, config );

		this.mode = config.mode;
	}

	OO.inheritClass( MentorshipOptoutReasonDialog, OO.ui.MessageDialog );

	MentorshipOptoutReasonDialog.static.name = 'mentorshipOptoutReasonDialog';
	MentorshipOptoutReasonDialog.static.size = 'small';
	MentorshipOptoutReasonDialog.static.title = mw.msg( 'growthexperiments-homepage-mentorship-optout-confirmation-header' );
	MentorshipOptoutReasonDialog.static.message = mw.msg( 'growthexperiments-homepage-mentorship-optout-confirmation-pretext' );
	MentorshipOptoutReasonDialog.static.actions = [
		{
			flags: 'safe',
			label: mw.msg( 'growthexperiments-homepage-mentorship-optout-confirmation-done' ),
			action: 'done'
		}
	];

	/**
	 * List of valid reasons for opting out of mentorship.
	 *
	 * @type {string[]}
	 */
	MentorshipOptoutReasonDialog.static.optoutReasons = [
		'different-mentor', 'no-mentor', 'other'
	];

	/** @inheritDoc **/
	MentorshipOptoutReasonDialog.prototype.initialize = function () {
		MentorshipOptoutReasonDialog.super.prototype.initialize.call( this );

		var selectOptions = this.constructor.static.optoutReasons.map( function ( reason ) {
			return {
				data: reason,
				// Messages used:
				// * growthexperiments-homepage-mentorship-optout-confirmation-reason-different-mentor
				// * growthexperiments-homepage-mentorship-optout-confirmation-reason-no-mentor
				// * growthexperiments-homepage-mentorship-optout-confirmation-reason-other
				label: mw.msg( 'growthexperiments-homepage-mentorship-optout-confirmation-reason-' + reason )
			};
		} );
		this.reasonSelect = new SelectWithTextInputWidget( {
			options: selectOptions,
			isMultiSelect: false
		} );
		this.text.$element.append( this.reasonSelect.getElement() );
	};

	/** @inheritDoc **/
	MentorshipOptoutReasonDialog.prototype.getActionProcess = function ( action ) {
		if ( action === 'done' ) {
			homepageModuleLogger.log( 'mentorship', this.mode, 'mentorship-optout', {
				reasons: this.reasonSelect.findSelection()
			} );
		}

		return MentorshipOptoutReasonDialog.super.prototype.getActionProcess.call( this, action );
	};

	module.exports = MentorshipOptoutReasonDialog;
}() );
