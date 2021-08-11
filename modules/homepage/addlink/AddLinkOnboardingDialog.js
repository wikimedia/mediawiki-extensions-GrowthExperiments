var AddLinkOnboardingContent = require( './AddLinkOnboardingContent.js' ),
	MultiPaneDialog = require( '../../ui-components/MultiPaneDialog.js' );

/**
 * Dialog with onboarding screens for Add a Link
 *
 * @extends MultiPaneDialog
 *
 * @param {Object} [dialogConfig]
 * @param {boolean} [dialogConfig.hasSlideTransition] Use slide transition between panels
 * @param {mw.libs.ge.LinkSuggestionInteractionLogger} [dialogConfig.logger] Instrumentation logger for the dialog
 * @param {string} [dialogConfig.progressMessageKey] Key name for the progress indicator text
 * @param {string[]} [dialogConfig.classes] Classname(s) of the dialog
 *
 * @param {Object} [onboardingConfig]
 * @param {string} [onboardingConfig.prefName] Name of user preferences for Add a Link onboarding
 * @constructor
 */
function AddLinkOnboardingDialog( dialogConfig, onboardingConfig ) {
	dialogConfig = dialogConfig || {};
	dialogConfig.progressMessageKey = dialogConfig.progressMessageKey || 'growthexperiments-addlink-onboarding-dialog-progress';
	this.logger = dialogConfig.logger;
	if ( !Array.isArray( dialogConfig.classes ) ) {
		dialogConfig.classes = [];
	}
	dialogConfig.classes.push( 'addlink-onboarding-dialog' );
	AddLinkOnboardingDialog.super.call( this, dialogConfig );

	if ( onboardingConfig && onboardingConfig.prefName ) {
		this.prefName = onboardingConfig.prefName;
	}
	this.panels = AddLinkOnboardingContent.getPanels( { includeImage: true } );
}

OO.inheritClass( AddLinkOnboardingDialog, MultiPaneDialog );

AddLinkOnboardingDialog.static.name = 'AddLinkOnboardingDialog';
AddLinkOnboardingDialog.static.size = 'medium';
AddLinkOnboardingDialog.static.title = mw.msg( 'growthexperiments-addlink-onboarding-dialog-title' );
AddLinkOnboardingDialog.static.actions = [
	{
		action: 'cancel',
		label: mw.message( 'growthexperiments-addlink-onboarding-dialog-label-skip-all' ).text(),
		framed: false,
		flags: [ 'primary' ],
		classes: [ 'addlink-onboarding-dialog-skip-button' ]
	}
];

/** @inheritdoc */
AddLinkOnboardingDialog.prototype.getFooterElement = function () {
	this.checkBoxInput = new OO.ui.CheckboxInputWidget( {
		selected: false,
		value: 'dismissAddLinkOnboarding'
	} );

	if ( this.prefName ) {
		this.checkBoxInput.on( 'change', function ( isSelected ) {
			new mw.Api().saveOption( this.prefName, isSelected ? '1' : '0' );
		}.bind( this ) );
	}

	this.$dismissField = new OO.ui.FieldLayout( this.checkBoxInput, {
		label: mw.message( 'growthexperiments-addlink-onboarding-dialog-dismiss-checkbox' ).text(),
		align: 'inline',
		classes: [ 'addlink-onboarding-dialog-footer-widget' ]
	} ).$element;

	this.$prevButton = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-addlink-onboarding-dialog-label-previous' ).text(),
		icon: 'previous',
		invisibleLabel: true,
		classes: [ 'addlink-onboarding-dialog-footer-widget' ]
	} ).$element;
	this.$prevButton.on( 'click', function () {
		this.showPrevPanel();
	}.bind( this ) );

	this.$nextButton = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-addlink-onboarding-dialog-label-next' ).text(),
		icon: 'next',
		invisibleLabel: true,
		flags: [ 'progressive', 'primary' ],
		classes: [ 'addlink-onboarding-dialog-footer-widget', 'align-end' ]
	} ).$element;
	this.$nextButton.on( 'click', function () {
		this.showNextPanel();
	}.bind( this ) );

	this.$getStartedButton = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-addlink-onboarding-dialog-get-started-button' ).text(),
		flags: [ 'progressive', 'primary' ],
		classes: [ 'addlink-onboarding-dialog-footer-widget', 'align-end' ]
	} ).$element;
	this.$getStartedButton.on( 'click', function () {
		this.closeDialog( 'get_started' );
	}.bind( this ) );

	return new OO.ui.PanelLayout( {
		padded: true,
		expanded: false,
		content: [ this.$dismissField, this.$prevButton, this.$nextButton, this.$getStartedButton ],
		classes: [ 'addlink-onboarding-dialog-footer' ]
	} ).$element;
};

/** @inheritdoc */
AddLinkOnboardingDialog.prototype.getReadyProcess = function ( data ) {
	// Record an impression event when the dialog is opened
	this.logger.log( 'impression', this.getLogActionData(), this.getLogMetadata() );
	return AddLinkOnboardingDialog.super.prototype.getReadyProcess.call( this, data );
};

/** @inheritdoc */
AddLinkOnboardingDialog.prototype.getActionProcess = function ( action ) {
	return AddLinkOnboardingDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			this.closeDialog( action === 'cancel' ? 'skip_all' : 'close' );
		}, this );
};

/** @inheritdoc */
AddLinkOnboardingDialog.prototype.updateViewState = function () {
	var $skipButton = this.$head.find( '.addlink-onboarding-dialog-skip-button' );

	AddLinkOnboardingDialog.super.prototype.updateViewState.call( this );

	this.$getStartedButton.hide();
	this.$dismissField.hide();

	$skipButton.show();

	if ( this.currentPanelIndex === 1 ) {
		var $learnMoreLink = this.panels[ 1 ].$element.find( '.addlink-onboarding-content-link' );
		if ( $learnMoreLink ) {
			$learnMoreLink.on( 'click', function () {
				this.logger.log( 'link_click', this.getLogActionData(), this.getLogMetadata() );
			}.bind( this ) );
		}
	}

	if ( this.currentPanelIndex === this.panels.length - 1 ) {
		this.$getStartedButton.show();
		this.$nextButton.hide();
		$skipButton.hide();
	} else if ( this.currentPanelIndex === 0 ) {
		this.$dismissField.show();
		this.$prevButton.hide();
	} else {
		this.$prevButton.show();
		this.$nextButton.show();
	}
};

/** @inheritdoc */
AddLinkOnboardingDialog.prototype.showPanelIndex = function ( index ) {
	AddLinkOnboardingDialog.super.prototype.showPanelIndex.call( this, index );
	this.logger.log( 'impression', this.getLogActionData(), this.getLogMetadata() );
};

/**
 * Close dialog and fire an event indicating that onboarding has been completed
 *
 * @param {string} action One of 'skip_all', 'get_started', or 'close'
 */
AddLinkOnboardingDialog.prototype.closeDialog = function ( action ) {
	this.logger.log( action, this.getLogActionData(), this.getLogMetadata() );
	this.close();
	mw.hook( 'growthExperiments.addLinkOnboardingCompleted' ).fire();
};

/**
 * Get action_data to use with LinkSuggestionInteractionLogger
 *
 * @return {{dont_show_again: boolean}}
 */
AddLinkOnboardingDialog.prototype.getLogActionData = function () {
	return {
		// eslint-disable-next-line camelcase
		dont_show_again: this.checkBoxInput.selected
	};
};

/**
 * Get metadata override to use with LinkSuggestionInteractionLogger
 *
 * @return {{active_interface: string}}
 */
AddLinkOnboardingDialog.prototype.getLogMetadata = function () {
	return {
		// eslint-disable-next-line camelcase
		active_interface: 'onboarding_step_' + ( this.currentPanelIndex + 1 ) + '_dialog'
	};
};

module.exports = AddLinkOnboardingDialog;
