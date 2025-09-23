const StructuredTaskMessageDialog = require( '../StructuredTaskMessageDialog.js' ),
	SuggestionInteractionLogger = require( '../SuggestionInteractionLogger.js' );

/**
 * Simple message dialog showing various image metadata.
 *
 * @class mw.libs.ge.AddImageDetailsDialog
 * @extends mw.libs.ge.StructuredTaskMessageDialog
 * @param {Object} config
 * @constructor
 */
function AddImageDetailsDialog( config ) {
	AddImageDetailsDialog.super.call( this, config );
	this.$element.addClass( [
		'mw-ge-addImageDetailsDialog',
		OO.ui.isMobile() ?
			'mw-ge-addImageDetailsDialog-mobile' :
			'mw-ge-addImageDetailsDialog-desktop',
	] );
}

OO.inheritClass( AddImageDetailsDialog, StructuredTaskMessageDialog );

AddImageDetailsDialog.static.name = 'addImageDetails';
AddImageDetailsDialog.static.size = OO.ui.isMobile() ? 'small' : 'medium';
AddImageDetailsDialog.static.title =
	mw.message( 'growthexperiments-addimage-detailsdialog-header' ).text();
AddImageDetailsDialog.static.actions = [
	{
		action: 'close',
		label: mw.message( 'growthexperiments-addimage-detailsdialog-close' ).text(),
	},
];

/** @inheritDoc **/
AddImageDetailsDialog.prototype.initialize = function () {
	AddImageDetailsDialog.super.prototype.initialize.call( this );
	/** @property {string} logSource */
	this.logSource = null;
	/** @property {number} imageIndex */
	this.imageIndex = null;
};

/** @inheritDoc **/
AddImageDetailsDialog.prototype.getSetupProcess = function ( data ) {
	return AddImageDetailsDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateDialog( data.recommendation );
			this.logSource = data.logSource;
			this.imageIndex = data.imageIndex;
			this.logEvent( 'impression' );
		}, this );
};

/** @inheritDoc **/
AddImageDetailsDialog.prototype.getTeardownProcess = function ( data ) {
	return AddImageDetailsDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( function () {
			this.logEvent( 'close' );
		}, this );
};

/**
 * The part of the initialization that needs data from getSetupProcess.
 *
 * @param {mw.libs.ge.ImageRecommendationImage} recommendation
 */
AddImageDetailsDialog.prototype.updateDialog = function ( recommendation ) {
	this.message.$element.empty().append(
		$( '<div>' ).addClass( 'mw-ge-addImageDetailsDialog-reason' ).text( recommendation.metadata.reason ),
		// A <dl> would be more semantic but it's hard to achieve the "run-in" layout style with it.
		$( '<ul>' ).addClass( 'mw-ge-addImageDetailsDialog-fields' ).append(
			this.addSpacers( [
				this.makeField( 'image', recommendation ),
				this.makeField( 'description', recommendation ),
				this.makeField( 'caption', recommendation ),
				this.makeSpacer(),
				this.makeField( 'license', recommendation ),
				this.makeField( 'date', recommendation ),
				this.makeField( 'author', recommendation ),
				this.makeSpacer(),
				this.makeField( 'categories', recommendation ),
			] ),
		),
		$( '<a>' )
			.addClass( 'mw-ge-filelink' )
			.attr( 'href', recommendation.metadata.descriptionUrl )
			.attr( 'target', '_new' )
			.text( mw.message( 'growthexperiments-addimage-detailsdialog-link' ).text() )
			.append(
				new OO.ui.IconWidget( { icon: 'linkExternal', flags: 'progressive' } ).$element,
			)
			.on( 'click', () => {
				this.logEvent( 'link_click' );
			} ),
	);
};

/**
 * Create a single item for the dialog.
 *
 * @param {string} field Name of the field in RecommendationImage.metadata
 * @param {mw.libs.ge.ImageRecommendationImage} recommendation
 * @return {jQuery|null}
 */
AddImageDetailsDialog.prototype.makeField = function ( field, recommendation ) {
	// Uses the following message keys:
	// * growthexperiments-addimage-detailsdialog-filename
	// * growthexperiments-addimage-detailsdialog-description
	// * growthexperiments-addimage-detailsdialog-caption
	// * growthexperiments-addimage-detailsdialog-license
	// * growthexperiments-addimage-detailsdialog-date
	// * growthexperiments-addimage-detailsdialog-author
	// * growthexperiments-addimage-detailsdialog-categories
	const fieldLabel = mw.message( 'growthexperiments-addimage-detailsdialog-' + field ).text();

	let fieldValue;
	if ( field === 'image' ) {
		fieldValue = recommendation.displayFilename;
	} else if ( field === 'categories' ) {
		const categories = recommendation.metadata.categories;
		fieldValue = ( categories && categories.length ) ?
			categories.join( mw.message( 'comma-separator' ).text() ) :
			null;
	} else {
		fieldValue = recommendation.metadata[ field ];
	}

	if ( fieldValue === null ) {
		return null;
	}

	// Strip all HTML.
	fieldValue = $.parseHTML( fieldValue ).map( ( node ) => {
		if ( node.nodeType === Node.ELEMENT_NODE ) {
			return node.innerText;
		} else if ( node.nodeType === Node.TEXT_NODE ) {
			return node.textContent;
		} else {
			return '';
		}
	} ).join( '' );

	return $( '<li>' ).append(
		$( '<span>' ).addClass( 'mw-ge-addImageDetailsDialog-label' ).text( fieldLabel ),
		$( '<span>' ).attr( 'dir', 'auto' ).text( fieldValue ),
	);
};

/**
 * Post-process a list to add spacer classes. Takes a list of <li> elements and spacer elements
 * (as returned by makeSpacer()); any spacers between two list items will be turned into an
 * 'mw-ge-spacer' class on the preceding list item.
 *
 * @param {(jQuery|null)[]} list
 * @return {jQuery[]}
 */
AddImageDetailsDialog.prototype.addSpacers = function ( list ) {
	const spacer = this.makeSpacer();

	// remove missing fields
	list = list.filter( ( el ) => el !== null );
	for ( let i = 0; i < list.length; i++ ) {
		if ( list[ i ] === spacer && i > 0 && i < list.length - 1 ) {
			const last = list[ i - 1 ];
			if ( last !== spacer ) {
				last.addClass( 'mw-ge-spacer' );
			}
		}
	}
	return Array.prototype.concat.apply( [], list.filter( ( el ) => el !== spacer ) );
};

/**
 * Create empty space between lines created with makeField().
 *
 * @return {string}
 */
AddImageDetailsDialog.prototype.makeSpacer = function () {
	return '{spacer}';
};

/**
 * @param {string} event
 */
AddImageDetailsDialog.prototype.logEvent = function ( event ) {
	const actionData = ve.init.target.getSuggestionLogActionData( this.imageIndex );
	actionData.source = this.logSource;
	// eslint-disable-next-line camelcase
	SuggestionInteractionLogger.log( event, actionData, { active_interface: 'imagedetails_dialog' } );
};

module.exports = AddImageDetailsDialog;
