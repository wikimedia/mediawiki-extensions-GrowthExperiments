( function () {

	/**
	 * @class QuestionPosterDialog
	 * @extends HelpPanelProcessDialog
	 *
	 * @param {Object} config
	 * @cfg {string} name Logical name for this dialog instance
	 * @constructor
	 */
	var QuestionPosterDialog = function QuestionPosterDialog( config ) {
			QuestionPosterDialog.super.call( this, $.extend( {}, config, {
				showCogMenu: false,
				storageKey: 'homepage-questionposter-question-text-' + config.name,
				source: 'homepage-' + config.name
			} ) );
		},
		HelpPanelProcessDialog = require( 'ext.growthExperiments.Help' ).HelpPanelProcessDialog;

	OO.inheritClass( QuestionPosterDialog, HelpPanelProcessDialog );

	QuestionPosterDialog.static.name = 'HomepageQuestionPosterDialog';
	QuestionPosterDialog.static.actions = [
		{
			label: OO.ui.deferMsg( 'growthexperiments-help-panel-submit-question-button-text' ),
			modes: [ 'ask-help' ],
			flags: [ 'progressive', 'primary' ],
			action: 'questioncomplete'
		},
		{
			label: OO.ui.deferMsg( 'growthexperiments-homepage-help-cancel' ),
			modes: [ 'ask-help' ],
			flags: [ 'safe', 'back' ]
		},
		{
			label: mw.message( 'growthexperiments-help-panel-close' ).text(),
			modes: [ 'questioncomplete' ],
			flags: [ 'primary' ],
			action: 'helppanelclose'
		}
	];

	QuestionPosterDialog.prototype.swapPanel = function ( panel ) {
		QuestionPosterDialog.super.prototype.swapPanel.call( this, panel );

		if ( panel === 'ask-help' ) {
			// N.B. this intentionally differs from the main help panel where
			// the footer is enabled.
			this.askhelpFooterPanel.toggle( false );
			this.questionIncludeFieldLayout.toggle( false );
			this.askhelpTextInput.setValue( mw.storage.get( this.storageKey ) );
			this.getActions().setAbilities( {
				questioncomplete: this.askhelpTextInput.getValue()
			} );
		}
	};

	QuestionPosterDialog.prototype.getSetupProcess = function ( data ) {
		return QuestionPosterDialog.super.prototype.getSetupProcess
			.call( this, data )
			.next( function () {
				this.setMode( 'ask-help' );
			}, this );
	};

	/**
	 * Connected to the askhelpTextInput field.
	 */
	QuestionPosterDialog.prototype.onTextInputChange = function () {
		var reviewTextInputValue = this.askhelpTextInput.getValue();
		// Enable the "Submit" button on the review step if there's text input.
		this.getActions().setAbilities( {
			questioncomplete: this.askhelpTextInput.getValue()
		} );
		if ( reviewTextInputValue && mw.storage.get( this.storageKey ) === '' ) {
			this.logger.log( 'enter-question-text' );
		}
		// Save the draft text in local storage in case the user reloads their page.
		mw.storage.set( this.storageKey, reviewTextInputValue );
	};

	QuestionPosterDialog.prototype.getBodyHeight = function () {
		return this.panels.getCurrentItem().$element.outerHeight( true );
	};

	module.exports = QuestionPosterDialog;
}() );
