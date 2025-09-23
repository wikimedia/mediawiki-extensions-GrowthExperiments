/**
 * MessageDialog with custom styling for structured task editing flows
 *
 * @class mw.libs.ge.StructuredTaskMessageDialog
 * @extends OO.ui.MessageDialog
 *
 * @param {Object} [config]
 * @param {string[]} [config.classes] Array of custom classnames to use for the dialog
 *
 * @constructor
 */
function StructuredTaskMessageDialog( config ) {
	config = config || {};
	config.classes = Array.isArray( config.classes ) ? config.classes : [];
	config.classes.push(
		'mw-ge-structuredTaskMessageDialog',
		OO.ui.isMobile() ?
			'mw-ge-structuredTaskMessageDialog-mobile' :
			'mw-ge-structuredTaskMessageDialog-desktop',
	);
	StructuredTaskMessageDialog.super.call( this, config );
}

OO.inheritClass( StructuredTaskMessageDialog, OO.ui.MessageDialog );

StructuredTaskMessageDialog.static.name = 'structuredTaskMessage';

module.exports = StructuredTaskMessageDialog;
