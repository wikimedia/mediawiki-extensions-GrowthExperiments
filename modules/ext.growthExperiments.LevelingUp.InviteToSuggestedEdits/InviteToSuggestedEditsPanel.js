'use strict';

var Utils = require( '../utils/Utils.js' );

/**
 * Panel that invites the user to try out suggested edits.
 *
 * @class mw.libs.ge.InviteToSuggestedEditsPanel
 * @mixes OO.EventEmitter
 * @constructor
 */
function InviteToSuggestedEditsPanel() {
	OO.EventEmitter.call( this );
}
OO.initClass( InviteToSuggestedEditsPanel );
OO.mixinClass( InviteToSuggestedEditsPanel, OO.EventEmitter );

/**
 * @return {string}
 */
InviteToSuggestedEditsPanel.prototype.getHeaderText = function () {
	return mw.message( 'growthexperiments-help-panel-postedit-nonsuggested-header' ).text();
};

/**
 * @return {Array<jQuery>} Main panel content.
 */
InviteToSuggestedEditsPanel.prototype.getMainArea = function () {
	return [
		$( '<div>' ).addClass( 'mw-ge-inviteToSuggestedEditsDrawer-body' )
			.text( mw.message( 'growthexperiments-help-panel-postedit-nonsuggested-body' ).text() ),
		$( '<div>' ).addClass( 'mw-ge-inviteToSuggestedEditsDrawer-image' )
	];
};

/**
 * @return {Array<jQuery>} A list of footer elements.
 */
InviteToSuggestedEditsPanel.prototype.getFooterButtons = function () {
	var trySuggestedEditsButtonWidget = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-help-panel-postedit-nonsuggested-try-button-text' ).text(),
		flags: [ 'primary', 'progressive' ]
	} );
	var noThanksButtonWidget = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-help-panel-postedit-nonsuggested-nothanks-button-text' ).text(),
		framed: false,
		flags: [ 'progressive' ]
	} );
	trySuggestedEditsButtonWidget.connect( this, { click: 'trySuggestedEditsButtonClicked' } );
	noThanksButtonWidget.connect( this, { click: 'noThanksButtonClicked' } );
	return [ new OO.ui.HorizontalLayout( {
		items: [ noThanksButtonWidget, trySuggestedEditsButtonWidget ],
		classes: [ 'mw-ge-inviteToSuggestedEditsDrawer-footer' ]
	} ).$element ];
};

InviteToSuggestedEditsPanel.prototype.trySuggestedEditsButtonClicked = function () {
	window.location.href = Utils.getSuggestedEditsFeedUrl();
};

InviteToSuggestedEditsPanel.prototype.noThanksButtonClicked = function () {
	this.emit( 'close' );
};

module.exports = InviteToSuggestedEditsPanel;
