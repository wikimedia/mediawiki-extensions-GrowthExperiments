module.exports = ( function () {
	'use strict';

	var Utils = require( '../utils/ext.growthExperiments.Utils.js' ),
		addLinkOnboardingPrefName = 'growthexperiments-addlink-onboarding',
		addImageOnboardingPrefName = 'growthexperiments-addimage-onboarding',
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		taskTypes = require( '../homepage/suggestededits/TaskTypes.json' ),
		taskType = suggestedEditSession.taskType,
		isAddLink = taskType === 'link-recommendation' && taskType in taskTypes,
		isAddImage = taskType === 'image-recommendation' && taskType in taskTypes,
		dialogName,
		logger,
		shouldShowOnboarding = !mw.user.options.get( addLinkOnboardingPrefName ),
		StructuredTaskOnboardingDialog,
		LinkSuggestionInteractionLogger,
		ImageSuggestionInteractionLogger,
		windows = {},
		windowManager;

	/**
	 * Show onboarding dialog and update onboardingNeedsToBeShown in suggestedEditSession
	 *
	 * This prevents onboarding from being shown multiple times within a session.
	 */
	function showDialogForSession() {
		suggestedEditSession.onboardingNeedsToBeShown = false;
		suggestedEditSession.save();
		windowManager.openWindow( dialogName );
	}

	/**
	 * Show onboarding dialog if it hasn't been shown in the session and
	 * if the user hasn't checked "Don't show again"
	 *
	 * If the user has completed onboarding, fire an event so that actions
	 * that need to happen after onboarding can be invoked.
	 */
	function showOnboardingIfEligible() {
		if ( shouldShowOnboarding && suggestedEditSession.onboardingNeedsToBeShown ) {
			showDialogForSession();
		} else {
			mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).fire();
		}
	}

	/**
	 * Attach the window manager and set up dialogs and hooks for showing onboarding for the task
	 * if it needs to be shown
	 *
	 * @param {Object} config
	 * @param {OO.ui.PanelLayout[]} config.panels Onboarding panels to show
	 * @param {string} [config.prefName] Onboarding preference name
	 */
	function setupOnboarding( config ) {
		// In case onboarding is invoked from a different module (add link on desktop)
		mw.hook( 'growthExperiments.structuredTask.showOnboardingIfNeeded' ).add(
			showOnboardingIfEligible
		);

		if ( !shouldShowOnboarding ) {
			return;
		}
		// Only append window manager & construct dialog if onboarding should be shown
		StructuredTaskOnboardingDialog = require( './StructuredTaskOnboardingDialog.js' );
		windowManager = new OO.ui.WindowManager( { modal: true } );
		$( document.body ).append( windowManager.$element );

		windows[ dialogName ] = new StructuredTaskOnboardingDialog(
			{
				hasSlideTransition: true,
				logger: logger
			},
			config
		);
		windowManager.addWindows( windows );
	}

	/**
	 * Show "no suggestions found" error dialog and go back to Special:Homepage
	 *
	 * @param {string|Error} error Error code.
	 */
	function showErrorDialogOnFailure( error ) {
		mw.log.error( error );
		// Do not send to mw.errorLogger. The error message comes from the BeforePageDisplay
		// hook and was already logged there.

		// eslint-disable-next-line camelcase
		logger.log( 'impression', '', { active_interface: 'nosuggestions_dialog' } );

		OO.ui.alert( mw.message( 'growthexperiments-structuredtask-no-suggestions-found-dialog-message' ).text(), {
			actions: [ {
				action: 'accept',
				label: mw.message( 'growthexperiments-structuredtask-no-suggestions-found-dialog-button' ).text(),
				flags: 'primary'
			} ]
		} ).done( function () {
			// eslint-disable-next-line camelcase
			logger.log( 'close', '', { active_interface: 'nosuggestions_dialog' } );
			window.location.href = Utils.getSuggestedEditsFeedUrl();
		} );
	}

	/**
	 * Update the default section opened by the specified edit link
	 *
	 * @param {jQuery} $editLink Edit link element
	 * @param {string|number} section Zero-based index of the section to edit or "all"
	 */
	function updateEditLinkSection( $editLink, section ) {
		var editUri = new mw.Uri( $editLink.attr( 'href' ) );
		editUri.query.section = section;
		$editLink.attr( 'href', editUri.toString() );
	}

	/**
	 * Load platform-specific module for structured task editing flow
	 */
	function loadEditModule() {
		mw.hook( 've.loadModules' ).add( function ( addPlugin ) {
			// Either the desktop or the mobile module will be registered, but not both.
			// Start with both, filter out the unregistered one, and add the remaining one
			// as a VE plugin.
			[
				'ext.growthExperiments.StructuredTask.desktop',
				'ext.growthExperiments.StructuredTask.mobile'
			].filter( mw.loader.getState ).forEach( function ( module ) {
				addPlugin( function () {
					return mw.loader.using( module ).then( function () {
						require( module ).initializeTarget( taskType );
					} );
				} );
			} );
		} );
	}

	/**
	 * Check whether structured task editing flow should be invoked
	 *
	 * @return {boolean}
	 */
	function shouldInitializeStructuredTask() {
		if ( suggestedEditSession.taskState !== 'started' ) {
			return false;
		}
		return isAddImage ||
			( isAddLink && mw.config.get( 'wgGELinkRecommendationsFrontendEnabled' ) );
	}

	/**
	 * Check whether there is sufficient data to show structured task editing flow
	 *
	 * @return {jQuery.Promise} Promise that resolves if there is sufficient task data to
	 * show the editing flow and rejects with an error message/object if there isn't
	 */
	function checkTaskData() {
		var promise = $.Deferred();
		if ( !suggestedEditSession.taskData ) {
			promise.reject( 'Missing task data' );
		} else if ( suggestedEditSession.taskData.error ) {
			promise.reject( suggestedEditSession.taskData.error );
		} else {
			promise.resolve();
		}
		return promise;
	}

	if ( isAddLink ) {
		dialogName = 'addLinkOnboardingDialog';
		LinkSuggestionInteractionLogger = require(
			'../ext.growthExperiments.StructuredTask/addlink/LinkSuggestionInteractionLogger.js'
		);
		logger = new LinkSuggestionInteractionLogger( {
			// eslint-disable-next-line camelcase
			is_mobile: OO.ui.isMobile()
		} );
		shouldShowOnboarding = !mw.user.options.get( addLinkOnboardingPrefName );
		setupOnboarding( {
			prefName: addLinkOnboardingPrefName,
			panels: require( './addlink/AddLinkOnboardingContent.js' ).getPanels( {
				includeImage: true
			} )
		} );
	} else if ( isAddImage ) {
		ImageSuggestionInteractionLogger = require(
			'../ext.growthExperiments.StructuredTask/addimage/ImageSuggestionInteractionLogger.js'
		);
		logger = new ImageSuggestionInteractionLogger( {
			// eslint-disable-next-line camelcase
			is_mobile: OO.ui.isMobile()
		} );
		dialogName = 'addImageOnboardingDialog';
		shouldShowOnboarding = !mw.user.options.get( addImageOnboardingPrefName );
		setupOnboarding( {
			prefName: addImageOnboardingPrefName,
			panels: require( './addimage/AddImageOnboardingContent.js' ).getPanels( {
				includeImage: true
			} )
		} );
	}

	return {
		showOnboardingIfEligible: showOnboardingIfEligible,
		showErrorDialogOnFailure: showErrorDialogOnFailure,
		updateEditLinkSection: updateEditLinkSection,
		loadEditModule: loadEditModule,
		shouldInitializeStructuredTask: shouldInitializeStructuredTask,
		checkTaskData: checkTaskData
	};

}() );
