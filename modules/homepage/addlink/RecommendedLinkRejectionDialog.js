function RecommendedLinkRejectionDialog( config ) {
	RecommendedLinkRejectionDialog.super.call( this, config );

	this.$element.addClass( 'mw-ge-recommendedLinkRejectionDialog' );
}

OO.inheritClass( RecommendedLinkRejectionDialog, OO.ui.MessageDialog );

RecommendedLinkRejectionDialog.static.name = 'recommendedLinkRejection';
RecommendedLinkRejectionDialog.static.size = 'medium';
RecommendedLinkRejectionDialog.static.title = function () {
	return mw.message( 'growthexperiments-addlink-rejectiondialog-title' ).parseDom();
};
RecommendedLinkRejectionDialog.static.message = mw.msg( 'growthexperiments-addlink-rejectiondialog-message' );
RecommendedLinkRejectionDialog.static.actions = [
	{
		action: 'done',
		label: mw.msg( 'growthexperiments-addlink-rejectiondialog-action-done' ),
		flags: 'primary'
	}
];

RecommendedLinkRejectionDialog.prototype.initialize = function () {
	var editIcon;

	// Parent method
	RecommendedLinkRejectionDialog.super.prototype.initialize.call( this );

	editIcon = new OO.ui.IconWidget( {
		icon: 'edit',
		flags: [ 'progressive' ]
	} );

	this.reasonSelect = new OO.ui.RadioSelectWidget( {
		items: [
			new OO.ui.RadioOptionWidget( {
				data: 'everyday',
				label: mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-everyday' )
			} ),
			new OO.ui.RadioOptionWidget( {
				data: 'already-linked',
				label: mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-already-linked' )
			} ),
			new OO.ui.RadioOptionWidget( {
				data: 'wrong-target',
				label: $( '<span>' ).append(
					$( '<span>' )
						.addClass( 'mw-ge-recommendedLinkRejectionDialog-reason-wrong-target-label' )
						.text( mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-wrong-target' ) ),
					$( '<p>' )
						.addClass( 'mw-ge-recommendedLinkRejectionDialog-reason-wrong-target-tip' )
						.append(
							mw.message( 'growthexperiments-addlink-rejectiondialog-reason-wrong-target-tip' )
								.params( [ editIcon.$element ] )
								.parseDom()
						)
				),
				classes: [ 'mw-ge-recommendedLinkRejectionDialog-reason-wrong-target' ]
			} ),
			new OO.ui.RadioOptionWidget( {
				data: 'other',
				label: mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-other' )
			} )
		]
	} );
	this.text.$element.append( this.reasonSelect.$element );
};

RecommendedLinkRejectionDialog.prototype.getSetupProcess = function ( data ) {
	return RecommendedLinkRejectionDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.reasonSelect.selectItem( null );
		}, this );
};

RecommendedLinkRejectionDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'done' ) {
		return new OO.ui.Process( function () {
			var selectedItem = this.reasonSelect.findSelectedItem();
			this.close( { action: 'done', reason: selectedItem && selectedItem.getData() } );
		}, this );
	}
	return RecommendedLinkRejectionDialog.super.prototype.getActionProcess.call( this, action );
};

module.exports = RecommendedLinkRejectionDialog;
