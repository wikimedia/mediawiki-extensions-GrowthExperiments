function StructuredTaskMessageDialog( config ) {
	config = config || {};
	config.classes = Array.isArray( config.classes ) ? config.classes : [];
	config.classes.push(
		'mw-ge-structuredTaskMessageDialog',
		OO.ui.isMobile() ?
			'mw-ge-structuredTaskMessageDialog-mobile' :
			'mw-ge-structuredTaskMessageDialog-desktop'
	);
	StructuredTaskMessageDialog.super.call( this, config );
}

OO.inheritClass( StructuredTaskMessageDialog, OO.ui.MessageDialog );

StructuredTaskMessageDialog.static.name = 'structuredTaskMessage';

module.exports = StructuredTaskMessageDialog;
