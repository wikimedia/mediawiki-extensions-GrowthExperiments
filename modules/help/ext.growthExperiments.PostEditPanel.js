'use strict';

( function () {
	var SmallTaskCard = require( '../homepage/suggestededits/' +
		'ext.growthExperiments.SuggestedEdits.SmallTaskCard.js' );
	var SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' );

	/**
	 * @class
	 * @mixes OO.EventEmitter
	 *
	 * @constructor
	 * @param {Object} config
	 * @param {string} config.taskType Task type of the current task.
	 * @param {string} config.taskState State of the current task, as in SuggestedEditSession.taskState.
	 * @param {mw.libs.ge.TaskData|null} config.nextTask Data for the next suggested edit, as returned by
	 *   GrowthTasksApi, or null if there are no available tasks or fetching tasks failed.
	 * @param {Object} config.taskTypes Task type data, as returned by
	 *   HomepageHooks::getTaskTypesJson.
	 * @param {mw.libs.ge.NewcomerTaskLogger} config.newcomerTaskLogger
	 * @param {mw.libs.ge.HelpPanelLogger} config.helpPanelLogger
	 */
	function PostEditPanel( config ) {
		OO.EventEmitter.call( this );
		this.taskType = config.taskType;
		this.taskState = config.taskState;
		this.nextTask = config.nextTask;
		this.taskTypes = config.taskTypes;
		this.newcomerTaskLogger = config.newcomerTaskLogger;
		this.helpPanelLogger = config.helpPanelLogger;
		this.newcomerTaskToken = null;
		this.$taskCard = null;
	}
	OO.initClass( PostEditPanel );
	OO.mixinClass( PostEditPanel, OO.EventEmitter );

	/**
	 * Get the success message to be displayed on top of the panel.
	 *
	 * @return {OO.ui.MessageWidget}
	 */
	PostEditPanel.prototype.getSuccessMessage = function () {
		var type;

		if ( this.taskState === SuggestedEditSession.static.STATES.SAVED ) {
			type = mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ? 'published' : 'saved';
			if ( mw.config.get( 'wgStableRevisionId' ) &&
				mw.config.get( 'wgStableRevisionId' ) !== mw.config.get( 'wgRevisionId' )
			) {
				// FlaggedRevs wiki, the current revision needs review
				type = 'saved';
			}
		} else {
			type = 'notsaved';
		}

		return new OO.ui.MessageWidget( {
			type: 'success',
			classes: [ 'mw-ge-help-panel-postedit-message' ],
			// The following messages are used here:
			// * growthexperiments-help-panel-postedit-success-message-published
			// * growthexperiments-help-panel-postedit-success-message-saved
			// * growthexperiments-help-panel-postedit-success-message-notsaved
			label: mw.message( 'growthexperiments-help-panel-postedit-success-message-' + type ).text()
		} );
	};

	/**
	 * Get the links to display in the footer.
	 *
	 * @return {Array<jQuery>} A list of footer elements.
	 */
	PostEditPanel.prototype.getFooterButtons = function () {
		var title, footer, footer2,
			isSaved = ( this.taskState === SuggestedEditSession.static.STATES.SAVED ),
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
			label: isSaved ?
				mw.message( 'growthexperiments-help-panel-postedit-footer2' ).text() :
				mw.message( 'growthexperiments-help-panel-postedit-footer2-notsaved' ).text(),
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
	 *
	 * @return {jQuery|null} The main area, a jQuery object wrapping the card element.
	 *   Null if the panel should not have a main area (as no task should be displayed).
	 */
	PostEditPanel.prototype.getMainArea = function () {
		var subheaderMessage = ( this.taskState === SuggestedEditSession.static.STATES.SAVED ) ?
			mw.message( 'growthexperiments-help-panel-postedit-subheader' ).text() :
			mw.message( 'growthexperiments-help-panel-postedit-subheader-notsaved' ).text();

		if ( !this.nextTask ) {
			return null;
		}
		this.$taskCard = this.getCard( this.nextTask );
		return $( '<div>' )
			.addClass( 'mw-ge-help-panel-postedit-main' )
			.append(
				$( '<div>' )
					.addClass( 'mw-ge-help-panel-postedit-subheader' )
					.text( subheaderMessage ),
				this.$taskCard
			);
	};

	/**
	 * Create the card element.
	 *
	 * @param {mw.libs.ge.TaskData} task A task object, as returned by GrowthTasksApi
	 * @return {jQuery} A jQuery object wrapping the card element.
	 */
	PostEditPanel.prototype.getCard = function ( task ) {
		var params, url, taskCard;

		this.newcomerTaskToken = this.newcomerTaskLogger.log( task );
		params = {
			geclickid: this.helpPanelLogger.helpPanelSessionId,
			getasktype: task.tasktype,
			genewcomertasktoken: this.newcomerTaskToken
		};
		if ( task.url ) {
			// Override for developer setups
			url = task.url;
		} else if ( task.pageId ) {
			url = new mw.Title( 'Special:Homepage/newcomertask/' + task.pageId ).getUrl( params );
		} else {
			url = new mw.Title( task.title ).getUrl( params );
		}
		taskCard = new SmallTaskCard( {
			task: task,
			taskTypes: this.taskTypes,
			taskUrl: url
		} );
		taskCard.connect( this, { click: 'logTaskClick' } );
		return taskCard.$element;
	};

	/**
	 * Update the task card after some task fields have been lazy-loaded.
	 *
	 * @param {mw.libs.ge.TaskData} task
	 */
	PostEditPanel.prototype.updateTask = function ( task ) {
		this.$taskCard.replaceWith( this.getCard( task ) );
	};

	/**
	 * Log that the panel was displayed to the user.
	 * Needs to be called by the code displaying the panel.
	 *
	 * @param {Object} extraData
	 * @param {string} extraData.savedTaskType Type of the task for which the edit was just saved.
	 * @param {string} [extraData.errorMessage] Error message, only if there was a task loading error.
	 * @param {Array<string>} [extraData.userTaskTypes] User's task type filter settings,
	 *   only if the impression involved showing a task
	 * @param {Array<string>} [extraData.userTopics] User's topic filter settings,
	 *   only if the impression involved showing a task
	 */
	PostEditPanel.prototype.logImpression = function ( extraData ) {
		var data;

		if ( this.nextTask ) {
			data = {
				type: 'full',
				savedTaskType: extraData.savedTaskType,
				userTaskTypes: extraData.userTaskTypes,
				newcomerTaskToken: this.newcomerTaskToken
			};
			if ( extraData.userTopics && extraData.userTopics.length ) {
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
	 *
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
		var joinToken = this.newcomerTaskLogger.log( this.nextTask );
		this.helpPanelLogger.log( 'postedit-task-click', { newcomerTaskToken: joinToken } );
	};

	module.exports = PostEditPanel;
}() );
