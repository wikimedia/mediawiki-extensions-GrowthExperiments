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
	 *   HomepageHooks::getTaskTypesJson.
	 * @param {mw.libs.ge.NewcomerTaskLogger} config.newcomerTaskLogger
	 * @param {mw.libs.ge.HelpPanelLogger} config.helpPanelLogger
	 */
	function PostEditPanel( config ) {
		OO.EventEmitter.call( this );
		this.nextTask = config.nextTask;
		this.taskTypes = config.taskTypes;
		this.newcomerTaskLogger = config.newcomerTaskLogger;
		this.helpPanelLogger = config.helpPanelLogger;
	}
	OO.initClass( PostEditPanel );
	OO.mixinClass( PostEditPanel, OO.EventEmitter );

	/**
	 * Get the success message to be displayed on top of the panel.
	 * @return {OO.ui.MessageWidget}
	 */
	PostEditPanel.prototype.getSuccessMessage = function () {
		var type = mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ? 'published' : 'saved';
		if ( mw.config.get( 'wgStableRevisionId' ) &&
			mw.config.get( 'wgStableRevisionId' ) !== mw.config.get( 'wgRevisionId' )
		) {
			// FlaggedRevs wiki, the current revision needs review
			type = 'saved';
		}
		return new OO.ui.MessageWidget( {
			type: 'success',
			classes: [ 'mw-ge-help-panel-postedit-message' ],
			// The following messages are used here:
			// * growthexperiments-help-panel-postedit-success-message-published
			// * growthexperiments-help-panel-postedit-success-message-saved
			label: mw.message( 'growthexperiments-help-panel-postedit-success-message-' + type ).text()
		} );
	};

	/**
	 * Get the links to display in the footer.
	 * @return {Array<jQuery>} A list of footer elements.
	 */
	PostEditPanel.prototype.getFooterButtons = function () {
		var title, footer, footer2,
			self = this;

		title = new mw.Title( 'Special:Homepage' );
		footer = new OO.ui.ButtonWidget( {
			href: title.getUrl( { source: 'postedit-panel' } ) +
				( OO.ui.isMobile() ? '#/homepage/suggested-edits' : '' ),
			label: mw.message( 'growthexperiments-help-panel-postedit-footer' ).text(),
			framed: false,
			classes: [ 'mw-ge-help-panel-postedit-footer' ]
		} );
		footer.$element.on( 'click', this.logLinkClick.bind( this, 'homepage' ) );

		footer2 = new OO.ui.ButtonWidget( {
			href: '#',
			label: mw.message( 'growthexperiments-help-panel-postedit-footer2' ).text(),
			framed: false,
			classes: [ 'mw-ge-help-panel-postedit-footer' ]
		} );
		footer2.$element.on( 'click', function () {
			self.logLinkClick( 'edit' );
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
		var $image, url, params, $title, $description, $pageviews, $taskType, $cardTextContainer, $card,
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

		params = {
			geclickid: this.helpPanelLogger.helpPanelSessionId,
			getasktype: task.tasktype
		};
		if ( task.url ) {
			// Override for developer setups
			url = task.url;
		} else if ( task.pageId ) {
			url = new mw.Title( 'Special:Homepage/newcomertask/' + task.pageId ).getUrl( params );
		} else {
			url = new mw.Title( task.title ).getUrl( params );
		}
		$title = $( '<span>' )
			.addClass( 'mw-ge-help-panel-postedit-card-title' )
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
		$card = $( '<a>' )
			.addClass( 'mw-ge-help-panel-postedit-card' )
			.attr( 'href', url )
			.on( 'click', this.logTaskClick.bind( this ) )
			.append( $image, $cardTextContainer );
		return $card;
	};

	/**
	 * Log that the panel was displayed to the user.
	 * Needs to be called by the code displaying the panel.
	 * @param {Object} extraData
	 * @param {string} extraData.savedTaskType Type of the task for which the edit was just saved.
	 * @param {string} [extraData.errorMessage] Error message, only if there was a task loading error.
	 * @param {Array<string>} [extraData.userTaskTypes] User's task type filter settings,
	 *   only if the impression involved showing a task
	 * @param {Array<string>} [extraData.userTopics] User's topic filter settings,
	 *   only if the impression involved showing a task
	 */
	PostEditPanel.prototype.logImpression = function ( extraData ) {
		var joinToken, data;

		if ( this.nextTask ) {
			joinToken = this.newcomerTaskLogger.log( 'postedit-impression', this.nextTask );
			data = {
				type: 'full',
				savedTaskType: extraData.savedTaskType,
				userTaskTypes: extraData.userTaskTypes,
				newcomerTaskToken: joinToken
			};
			if ( extraData.userTopics.length ) {
				data.userTopics = extraData.userTopics;
			}
			this.helpPanelLogger.log( 'postedit-impression', data );
		} else if ( extraData.errorMessage ) {
			this.helpPanelLogger.log( 'postedit-impression', {
				type: 'error',
				savedTaskType: extraData.savedTaskType,
				error: extraData.errorMessage
			} );
		} else {
			this.helpPanelLogger.log( 'postedit-impression', {
				type: 'small',
				savedTaskType: extraData.savedTaskType
			} );
		}
	};

	/**
	 * Log that the panel was closed.
	 * Needs to be set up by the (device-dependent) wrapper code that handles displaying the panel.
	 */
	PostEditPanel.prototype.logClose = function () {
		this.helpPanelLogger.log( 'postedit-close', '' );
	};

	/**
	 * Log that one of the footer buttons was clicked.
	 * This is handled automatically by the class.
	 * @param {string} linkName Symbolic link name ('homepage' or 'edit').
	 */
	PostEditPanel.prototype.logLinkClick = function ( linkName ) {
		this.helpPanelLogger.log( 'postedit-link-click', linkName );
	};

	/**
	 * Log that the task card was clicked.
	 * This is handled automatically by the class.
	 */
	PostEditPanel.prototype.logTaskClick = function () {
		var joinToken = this.newcomerTaskLogger.log( 'postedit-task-click', this.nextTask );
		this.helpPanelLogger.log( 'postedit-task-click', { newcomerTaskToken: joinToken } );
	};

	module.exports = PostEditPanel;
}() );
