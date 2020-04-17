'use strict';

( function () {

	/**
	 * @class
	 * @mixins OO.EventEmitter
	 *
	 * @constructor
	 * @param {Object} config
	 * @param {Object|null} config.nextTask Data for the next suggested edit, as returned by
	 *   GrowthTasksApi, or null if there are no available tasks or fetching tasks failed.
	 * @param {Object} config.taskTypes Task type data, as returned by
	 *    HomepageHooks::getTaskTypesJson.
	 */
	function PostEditPanel( config ) {
		OO.EventEmitter.call( this );
		this.nextTask = config.nextTask;
		this.taskTypes = config.taskTypes;
	}
	OO.initClass( PostEditPanel );
	OO.mixinClass( PostEditPanel, OO.EventEmitter );

	/**
	 * Get the success message to be displayed on top of the panel.
	 * @return {OO.ui.MessageWidget}
	 */
	PostEditPanel.prototype.getSuccessMessage = function () {
		return new OO.ui.MessageWidget( {
			type: 'success',
			classes: [ 'mw-ge-help-panel-postedit-message' ],
			label: mw.message( 'growthexperiments-help-panel-postedit-success-message' ).text()
		} );
	};

	/**
	 * Get the links to display in the footer.
	 * @return {Array<jQuery>} A list of footer elements.
	 */
	PostEditPanel.prototype.getFooterButtons = function () {
		var title, footer, footer2,
			self = this;

		title = new mw.Title( 'Special:Homepage' + ( OO.ui.isMobile() ? '#/homepage/suggested-edits' : '' ) );
		footer = new OO.ui.ButtonWidget( {
			href: title.getUrl(),
			label: mw.message( 'growthexperiments-help-panel-postedit-footer' ).text(),
			framed: false,
			classes: [ 'mw-ge-help-panel-postedit-footer' ]
		} );

		footer2 = new OO.ui.ButtonWidget( {
			href: '#',
			label: mw.message( 'growthexperiments-help-panel-postedit-footer2' ).text(),
			framed: false,
			classes: [ 'mw-ge-help-panel-postedit-footer' ]
		} );
		footer2.$element.on( 'click', function () {
			// When the user clicks the edit link, close the panel. (Actually opening
			// the editor would not be a great user experience as we can't predict whether
			// the user wants to edit a section or the whole article.) Since it could be a
			// dialog or a drawer, closing is handled by the caller.
			self.emit( 'edit-link-clicked' );
			return false;
		} );

		return [ footer.$element, footer2.$element ];
	};

	/**
	 * Get the main area of the panel (the card with a subheader).
	 * @return {jQuery|null} The main area, a jQuery object wrapping the card element.
	 *   Null if the panel should not have a main area (as no task should be displayed).
	 */
	PostEditPanel.prototype.getMainArea = function () {
		if ( !this.nextTask ) {
			return null;
		}

		return $( '<div>' )
			.addClass( 'mw-ge-help-panel-postedit-main' )
			.append(
				$( '<div>' )
					.addClass( 'mw-ge-help-panel-postedit-subheader' )
					.text( mw.message( 'growthexperiments-help-panel-postedit-subheader' ).text() ),
				this.getCard( this.nextTask )
			);
	};

	/**
	 * Create the card element.
	 * @param {Object} task A task object, as returned by GrowthTasksApi
	 * @return {jQuery} A jQuery object wrapping the card element.
	 */
	PostEditPanel.prototype.getCard = function ( task ) {
		var $image, title, $title, $description, $pageviews, $taskType, $cardTextContainer, $card,
			taskTypeData = this.taskTypes[ task.tasktype ];

		if ( task.thumbnailSource ) {
			$image = $( '<img>' )
				.addClass( 'mw-ge-help-panel-postedit-card-image' )
				.attr( 'src', task.thumbnailSource );
		} else {
			$image = $( '<div>' )
				.addClass( 'mw-ge-help-panel-postedit-card-image' )
				.addClass( 'mw-ge-help-panel-postedit-card-image-placeholder' );
		}

		if ( task.pageId ) {
			title = new mw.Title( 'Special:Homepage/newcomertask/' + task.pageId );
		} else {
			title = new mw.Title( task.title );
		}
		$title = $( '<a>' )
			.addClass( 'mw-ge-help-panel-postedit-card-title' )
			.attr( 'href', title.getUrl() )
			.text( task.title );

		if ( task.pageviews ) {
			$pageviews = $( '<span>' )
				.addClass( 'mw-ge-help-panel-postedit-card-pageviews' )
				.text( mw.message( 'growthexperiments-homepage-suggestededits-pageviews',
					mw.language.convertNumber( task.pageviews ) ).text() )
				.prepend( new OO.ui.IconWidget( { icon: 'chart' } ).$element );
		}

		if ( task.description ) {
			$description = $( '<span>' )
				.addClass( 'mw-ge-help-panel-postedit-card-description' )
				.text( task.description );
		}

		$taskType = $( '<span>' )
			.addClass( 'mw-ge-help-panel-postedit-card-tasktype' )
			.addClass( 'mw-ge-help-panel-postedit-card-tasktype-difficulty-' + taskTypeData.difficulty )
			.text( taskTypeData.messages.name )
			.prepend( new OO.ui.IconWidget( { icon: 'difficulty-' + taskTypeData.difficulty } ).$element );

		$cardTextContainer = $( '<div>' )
			.addClass( 'mw-ge-help-panel-postedit-card-text-container' )
			.append( $title, $description, $pageviews, $taskType );
		$card = $( '<div>' )
			.addClass( 'mw-ge-help-panel-postedit-card' )
			.append( $image, $cardTextContainer );
		return $card;
	};

	module.exports = PostEditPanel;
}() );
