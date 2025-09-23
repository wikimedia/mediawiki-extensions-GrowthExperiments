/**
 * Dialog with multiple panels
 *
 * @extends OO.ui.ProcessDialog
 *
 * @param {Object} [config]
 * @param {boolean} [config.hasSlideTransition] Use slide transition between panels
 * @param {string} [config.progressMessageKey] Key name for text in progress indicator
 * @param {string[]} [config.classes] Classname(s) of the dialog
 * @constructor
 */
function MultiPaneDialog( config ) {
	const processDialogClasses = [
		'growthexperiments-multi-pane-dialog',
		OO.ui.isMobile() ? 'growthexperiments-multi-pane-dialog-mobile' : 'growthexperiments-multi-pane-dialog-desktop',
	];
	config = config || {};
	config.classes = processDialogClasses.concat( config.classes || [] );
	// The following classes are used here:
	// * growthexperiments-multi-pane-dialog
	// * growthexperiments-multi-pane-dialog-mobile
	// * growthexperiments-multi-pane-dialog-desktop
	MultiPaneDialog.super.call( this, config );
	this.currentPanelIndex = 0;
	this.hasSlideTransition = config && config.hasSlideTransition;
	this.progressMessageKey = config && config.progressMessageKey;
	this.panels = [];
}

OO.inheritClass( MultiPaneDialog, OO.ui.ProcessDialog );

/**
 * Initialize OOUI ProcessDialog
 *
 * @override
 */
MultiPaneDialog.prototype.initialize = function () {
	MultiPaneDialog.super.prototype.initialize.call( this );

	this.progressIndicator = new OO.ui.Element( {
		content: [
			this.getProgressDots(),
			$( '<span>' ).attr( 'class', 'growthexperiments-multi-pane-dialog-progress-text' ),
		],
		classes: [ 'growthexperiments-multi-pane-dialog-progress-indicator' ],
	} );

	this.fixedContent = new OO.ui.Element( {
		$content: this.progressIndicator.$element,
		classes: [ 'growthexperiments-multi-pane-dialog-fixed-content' ],
	} );

	this.stack = new OO.ui.StackLayout( {
		items: this.panels,
		classes: [ 'growthexperiments-multi-pane-dialog-stack-layout' ],
	} );

	this.$body.append( this.fixedContent.$element, this.stack.$element );
	this.$foot.append( this.getFooterElement() );

	this.updateViewState();

	if ( this.hasSlideTransition ) {
		this.updatePanelTransitionClasses();
		// HACK Elements need to be visible to slide in/out, remove the
		// hidden attribute added by OOUI StackLayout.
		this.$body.find( '[hidden="hidden"]' ).removeAttr( 'hidden' );
	}
};

/**
 * Construct content for dialog footer
 *
 * @return {jQuery|string}
 */
MultiPaneDialog.prototype.getFooterElement = function () {
	return '';
};

/**
 * Construct dots based on the number of panels for progress indicator
 *
 * @return {jQuery}
 */
MultiPaneDialog.prototype.getProgressDots = function () {
	const dots = [];
	for ( let i = 0; i < this.panels.length; i++ ) {
		dots.push( $( '<span>' ).addClass( 'growthexperiments-multi-pane-dialog-dot' ) );
	}
	return $( '<div>' ).addClass( 'growthexperiments-multi-pane-dialog-dots-container' ).append( dots );
};

/**
 * Update dot states and text (if applicable) for progress indicator
 */
MultiPaneDialog.prototype.updateProgressIndicator = function () {
	const currentIndex = this.currentPanelIndex,
		$progressIndicator = this.progressIndicator.$element;

	if ( this.progressMessageKey ) {
		// The following keys are used here:
		// * growthexperiments-addlink-onboarding-dialog-progress
		// * other keys can be added when MultiPaneDialog is used in more places
		const progressText = mw.msg( this.progressMessageKey,
			mw.language.convertNumber( currentIndex + 1 ),
			mw.language.convertNumber( this.panels.length ),
		);
		$progressIndicator.find( '.growthexperiments-multi-pane-dialog-progress-text' ).text( progressText );
	}

	$progressIndicator.find( '.growthexperiments-multi-pane-dialog-dot' ).each( function ( index ) {
		$( this ).toggleClass( 'dot-completed', index <= currentIndex );
	} );
};

/**
 * Update UI states when the panel changes
 */
MultiPaneDialog.prototype.updateViewState = function () {
	this.updateProgressIndicator();
};

/**
 * Show panel at the specified index and update UI states
 *
 * @param {number} index
 */
MultiPaneDialog.prototype.showPanelIndex = function ( index ) {
	this.currentPanelIndex = index;
	if ( this.hasSlideTransition ) {
		this.updatePanelTransitionClasses();
	} else {
		this.stack.setItem( this.panels[ this.currentPanelIndex ] );
	}
	this.updateViewState();
};

/**
 * Add classes to panels for slide transition
 */
MultiPaneDialog.prototype.updatePanelTransitionClasses = function () {
	const currentIndex = this.currentPanelIndex;
	this.panels.forEach( ( panel, index ) => {
		panel.$element.toggleClass( 'offscreen-content-prev', index < currentIndex )
			.toggleClass( 'offscreen-content-next', index > currentIndex );
	} );
};

/**
 * Advance to the next panel if possible
 */
MultiPaneDialog.prototype.showNextPanel = function () {
	if ( this.currentPanelIndex === this.panels.length - 1 ) {
		return;
	}

	if ( this.hasSlideTransition ) {
		this.panels[ this.currentPanelIndex ].$element.addClass( 'offscreen-content-prev' );
	}
	this.showPanelIndex( this.currentPanelIndex + 1 );
};

/**
 * Go back to the previous panel if possible
 */
MultiPaneDialog.prototype.showPrevPanel = function () {
	if ( this.currentPanelIndex === 0 ) {
		return;
	}

	if ( this.hasSlideTransition ) {
		this.panels[ this.currentPanelIndex ].$element.addClass( 'offscreen-content-next' );
	}
	this.showPanelIndex( this.currentPanelIndex - 1 );
};

module.exports = MultiPaneDialog;
