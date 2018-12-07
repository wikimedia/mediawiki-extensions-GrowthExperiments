( function () {

	/**
	 * Confirmation dialog shown after a user has successfully
	 * submitted the Welcome Survey.
	 *
	 * @param {Object} config
	 * @cfg {string} privacyStatementUrl URL of the survey privacy statement
	 * @constructor
	 */
	function WelcomeSurveyConfirmationDialog( config ) {
		WelcomeSurveyConfirmationDialog.parent.call( this, {
			classes: [ 'welcome-survey-confirmation-dialog' ],
			size: 'large'
		} );
		this.privacyStatementUrl = config.privacyStatementUrl;
	}
	OO.inheritClass( WelcomeSurveyConfirmationDialog, OO.ui.ProcessDialog );

	WelcomeSurveyConfirmationDialog.static.name = 'WelcomeSurveyConfirmationDialog';
	WelcomeSurveyConfirmationDialog.static.title = mw.msg( 'welcomesurvey', mw.user.getName() );
	WelcomeSurveyConfirmationDialog.static.actions = [
		{
			action: 'close',
			label: mw.msg( 'welcomesurvey-popup-close-btn' ),
			flags: [ 'primary', 'progressive' ]
		}
	];

	/**
	 * @inheritdoc
	 */
	WelcomeSurveyConfirmationDialog.prototype.initialize = function () {
		var mainPanel;

		WelcomeSurveyConfirmationDialog.parent.prototype.initialize.apply( this, arguments );

		mainPanel = new OO.ui.PanelLayout( { padded: true } );
		mainPanel.$element.append(
			$( '<div>' )
				.addClass( 'confirmation-section' )
				.append(
					$( '<div>' ).addClass( 'section-title' ).text( mw.msg( 'welcomesurvey-save-confirmation-title' ) ),
					new mw.libs.ge.WelcomeSurvey.PrivacyNoticeWidget( {
						url: this.privacyStatementUrl,
						classes: [ 'section-text' ]
					} ).$element
				),
			$( '<div>' )
				.addClass( 'editing-section' )
				.append(
					$( '<div>' ).addClass( 'section-title' ).text( mw.msg( 'welcomesurvey-sidebar-editing-title' ) ),
					$( '<p>' ).addClass( 'section-text' ).text( mw.msg( 'welcomesurvey-sidebar-editing-text' ) ),
					new mw.libs.ge.WelcomeSurvey.GettingStartedLinksWidget( 'survey-popup' ).$element
				)
		);
		this.$body.append( mainPanel.$element );
	};

	/**
	 * @inheritdoc
	 */
	WelcomeSurveyConfirmationDialog.prototype.getBodyHeight = function () {
		return 500;
	};

	/**
	 * @inheritdoc
	 */
	WelcomeSurveyConfirmationDialog.prototype.getActionProcess = function ( action ) {
		if ( action === 'close' ) {
			return new OO.ui.Process( function () {
				this.close( { action: action } );
			}, this );
		}

		return WelcomeSurveyConfirmationDialog.parent.prototype.getActionProcess.call(
			this, action
		);
	};

	OO.setProp(
		mw, 'libs', 'ge', 'WelcomeSurvey', 'WelcomeSurveyConfirmationDialog',
		WelcomeSurveyConfirmationDialog
	);

}() );
