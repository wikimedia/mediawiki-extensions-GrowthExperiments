var StartEditingDialog = function StartEditingDialog( config ) {
	StartEditingDialog.super.call( this, config );
};

OO.inheritClass( StartEditingDialog, OO.ui.Dialog );

StartEditingDialog.static.name = 'startediting';
StartEditingDialog.static.size = 'large';

// HACK: Since the topic panel is not implemented yet, the difficulty panel currently shows the
// topic-back and difficulty-forward buttons. Once the topic panel has been implemented, we'll
// make the changes spelled out in the comments below, so that the topic panel shows the
// topic-* buttons and the difficulty panel the difficulty-* ones.
StartEditingDialog.static.actions = [
	{
		label: mw.msg( 'growthexperiments-homepage-startediting-dialog-topic-back' ),
		action: 'close',
		framed: true,
		// Once the topic panel is implemented, change this to modes: [ 'topic' ]
		modes: [ 'difficulty' ]
	},
	{
		label: mw.msg( 'growthexperiments-homepage-startediting-dialog-difficulty-back' ),
		action: 'back',
		framed: true,
		// Once the topic panel is implemented, change this to modes: [ 'difficulty' ]
		modes: []
	},
	{
		label: mw.msg( 'growthexperiments-homepage-startediting-dialog-topic-forward' ),
		action: 'difficulty',
		flags: [ 'progressive', 'primary' ],
		framed: true,
		modes: [ 'topic' ]
	},
	{
		label: mw.msg( 'growthexperiments-homepage-startediting-dialog-difficulty-forward' ),
		action: 'activate',
		flags: [ 'progressive', 'primary' ],
		framed: true,
		modes: [ 'difficulty' ]
	}
];

StartEditingDialog.prototype.initialize = function () {
	StartEditingDialog.super.prototype.initialize.call( this );

	this.topicPanel = new OO.ui.PanelLayout( { padded: false, expanded: false } );
	// TODO implement the topic panel
	this.topicPanel.$element.append( $( '<p>' ).text( 'Topic panel' ) );
	this.difficultyPanel = this.buildDifficultyPanel();

	this.panels = new OO.ui.StackLayout();
	this.panels.addItems( [ this.topicPanel, this.difficultyPanel ] );
	this.$body.append( this.panels.$element );

	this.$actions = $( '<div>' ).addClass( 'mw-ge-startediting-dialog-actions' );
	this.$foot.append( this.$actions );

	this.$element.addClass( 'mw-ge-startediting-dialog' );
};

StartEditingDialog.prototype.swapPanel = function ( panel ) {
	if ( ( [ 'topic', 'difficulty' ].indexOf( panel ) ) === -1 ) {
		throw new Error( 'Unknown panel: ' + panel );
	}

	this.panels.setItem( this[ panel + 'Panel' ] );
	this.actions.setMode( panel );
};

StartEditingDialog.prototype.attachActions = function () {
	var i, len, actionWidgets = this.actions.get();

	// Parent method
	StartEditingDialog.super.prototype.attachActions.call( this );

	for ( i = 0, len = actionWidgets.length; i < len; i++ ) {
		this.$actions.append( actionWidgets[ i ].$element );
		// Find the 'activate' button so that we can make it the pending element later
		// (see getActionProcess)
		if ( actionWidgets[ i ].action === 'activate' ) {
			this.$activateButton = actionWidgets[ i ].$button;
		}
	}
};

StartEditingDialog.prototype.getSetupProcess = function ( data ) {
	return StartEditingDialog.super.prototype.getSetupProcess
		.call( this, data )
		.next( function () {
			// Once the topic panel is implemented, change this to 'topic'
			this.swapPanel( 'difficulty' );
		}, this );
};

StartEditingDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	return StartEditingDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' ) {
				this.close();
			}
			if ( action === 'difficulty' ) {
				this.swapPanel( 'difficulty' );
			}
			if ( action === 'back' ) {
				this.swapPanel( 'topic' );
			}
			if ( action === 'activate' ) {
				// HACK: by default, the pending element is the head, but our head has height 0.
				// So make the 'activate' button the pending element instead, but don't do that in
				// initialization to avoid brief flashes of pending state when switching panels
				// or closing the dialog.
				this.setPendingElement( this.$activateButton );
				return new mw.Api().saveOption( 'growthexperiments-homepage-suggestededits-activated', 1 )
					.then( function () {
						dialog.close( { action: 'activate' } );
					} );
			}
		}, this );
};

StartEditingDialog.prototype.buildDifficultyPanel = function () {
	var difficultyPanel = new OO.ui.PanelLayout( { padded: false, expanded: false } ),
		levels = [ 'easy', 'medium', 'hard' ],
		legendRows = levels.map( function ( level ) {
			var classPrefix = 'mw-ge-startediting-dialog-difficulty-',
				// growthexperiments-homepage-startediting-dialog-difficulty-level-easy-label
				// growthexperiments-homepage-startediting-dialog-difficulty-level-medium-label
				// growthexperiments-homepage-startediting-dialog-difficulty-level-hard-label
				labelMsg = 'growthexperiments-homepage-startediting-dialog-difficulty-level-' +
					level + '-label',
				// growthexperiments-homepage-startediting-dialog-difficulty-level-easy-header
				// growthexperiments-homepage-startediting-dialog-difficulty-level-medium-header
				// growthexperiments-homepage-startediting-dialog-difficulty-level-hard-header
				headerMsg = 'growthexperiments-homepage-startediting-dialog-difficulty-level-' +
					level + '-description-header',
				// growthexperiments-homepage-startediting-dialog-difficulty-level-easy-body
				// growthexperiments-homepage-startediting-dialog-difficulty-level-medium-body
				// growthexperiments-homepage-startediting-dialog-difficulty-level-hard-body
				bodyMsg = 'growthexperiments-homepage-startediting-dialog-difficulty-level-' +
					level + '-description-body';
			return $( '<div>' )
				.addClass( [ classPrefix + 'legend-row', classPrefix + 'legend-' + level ] )
				.append(
					$( '<div>' )
						.addClass( [ classPrefix + 'legend-cell', classPrefix + 'legend-label' ] )
						.append(
							new OO.ui.IconWidget( { icon: 'difficulty-' + level } ).$element,
							$( '<span>' ).text( mw.msg( labelMsg ) )
						),
					$( '<div>' )
						.addClass( [ classPrefix + 'legend-cell', classPrefix + 'legend-description' ] )
						.append(
							$( '<p>' )
								.addClass( classPrefix + 'legend-description-header' )
								.text( mw.msg( headerMsg ) ),
							$( '<p>' )
								.addClass( classPrefix + 'legend-description-body' )
								.text( mw.msg( bodyMsg ) )
						)
				);
		} );

	difficultyPanel.$element.append(
		$( '<div>' )
			.addClass( 'mw-ge-startediting-dialog-difficulty-banner' )
			.append(
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-difficulty-header' )
					.append( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-header' ).parse() ),
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-difficulty-subheader' )
					.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-subheader' ).text() )
			),
		$( '<div>' )
			.addClass( 'mw-ge-startediting-dialog-difficulty-legend' )
			.append( legendRows )
	);
	return difficultyPanel;
};

module.exports = StartEditingDialog;
