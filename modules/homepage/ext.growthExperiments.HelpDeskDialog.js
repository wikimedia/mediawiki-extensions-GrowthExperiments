( function () {

	/**
	 * @class HelpDeskDialog
	 * @extends HelpPanelProcessDialog
	 *
	 * @param {Object} config
	 * @cfg {mw.libs.ge.HelpPanelLogger} logger
	 * @constructor
	 */
	var HelpDeskDialog = function helpDeskDialog( config ) {
			HelpDeskDialog.super.call( this, $.extend( {}, config, {
				showCogMenu: false
			} ) );
			this.logger = config.logger;
		},
		HelpPanelProcessDialog = require( 'ext.growthExperiments.Help' ).HelpPanelProcessDialog;

	OO.inheritClass( HelpDeskDialog, HelpPanelProcessDialog );

	HelpDeskDialog.static.name = 'HomepageHelpDeskDialog';
	HelpDeskDialog.static.title = mw.message( 'growthexperiments-help-panel-home-title' ).text();
	HelpDeskDialog.static.actions = [
		{
			label: OO.ui.deferMsg( 'growthexperiments-help-panel-submit-question-button-text' ),
			modes: [ 'questionreview' ],
			framed: true,
			flags: [ 'progressive', 'primary' ],
			action: 'questioncomplete'
		},
		{
			label: OO.ui.deferMsg( 'growthexperiments-homepage-help-cancel' ),
			modes: [ 'questionreview' ],
			framed: true,
			flags: 'safe'
		},
		{
			label: mw.message( 'growthexperiments-help-panel-close' ).text(),
			modes: [ 'questioncomplete' ],
			framed: true,
			flags: 'safe'
		}
	];

	HelpDeskDialog.prototype.swapPanel = function ( panel ) {
		HelpDeskDialog.super.prototype.swapPanel.call( this, panel );

		if ( panel === 'questionreview' ) {
			this.questionReviewFooterPanel.toggle( false );
			this.homeFooterPanel.toggle( false );
			this.questionIncludeFieldLayout.toggle( false );
			this.questionReviewTextInput.setValue( mw.storage.get( 'help-panel-question-text' ) );
			this.getActions().setAbilities( {
				questioncomplete: this.questionReviewTextInput.getValue()
			} );
		}
	};

	HelpDeskDialog.prototype.getSetupProcess = function ( data ) {
		return HelpDeskDialog.super.prototype.getSetupProcess
			.call( this, data )
			.next( function () {
				this.setMode( 'questionreview' );
			}, this );
	};

	/**
	 * Connected to the questionReviewTextInput field.
	 */
	HelpDeskDialog.prototype.onTextInputChange = function () {
		var reviewTextInputValue = this.questionReviewTextInput.getValue();
		// Enable the "Submit" button on the review step if there's text input.
		this.getActions().setAbilities( {
			questioncomplete: this.questionReviewTextInput.getValue()
		} );
		if ( mw.storage.get( 'help-panel-question-text' ) === '' ) {
			this.logger.log( 'enter-question-text' );
		}
		// Save the draft text in local storage in case the user reloads their page.
		mw.storage.set( 'help-panel-question-text', reviewTextInputValue );
	};

	/**
	 * Connected to the change event on this.questionReviewAddEmail.
	 */
	HelpDeskDialog.prototype.onEmailInput = function () {
		var reviewTextInputValue = this.questionReviewTextInput.getValue();
		// If user has typed in the email field, disable the submit button until the
		// email address is valid.
		if ( this.questionReviewAddEmail.getValue() ) {
			this.questionReviewAddEmail.getValidity()
				.then( function () {
					// Enable depending on whether the question text is input.
					this.getActions().setAbilities( { questioncomplete: reviewTextInputValue } );
				}.bind( this ), function () {
					// Always disable if email is invalid.
					// TODO: If you enter an invalid email, then update the question entry text, the
					// submit button is enabled again.
					this.getActions().setAbilities( { questioncomplete: false } );
				}.bind( this ) );
		} else {
			// If no email value, set submit button state based on review text input
			this.getActions().setAbilities( { questioncomplete: reviewTextInputValue } );
		}
	};

	module.exports = HelpDeskDialog;
}() );
