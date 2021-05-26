( function () {
	'use strict';
	/* eslint-disable no-jquery/no-global-selector */
	// We can't use $( 'li#ca-ve-edit, li#ca-edit, li#page-actions-edit' ).first() here,
	// because that returns the one that appears first in the DOM. Instead, we want to use
	// #ca-ve-edit if it exists, and only if that doesn't exist fall back to #ca-edit (T261001)
	var veEditLinkWrapper = $( 'li#ca-ve-edit' )[ 0 ],
		sourceEditingLinkWrapper = $( 'li#ca-edit' )[ 0 ],
		pageActionsEditWrapper = $( 'li#page-actions-edit' )[ 0 ],
		editLinkWrapper = veEditLinkWrapper ||
			sourceEditingLinkWrapper ||
			pageActionsEditWrapper,
		/* eslint-enable no-jquery/no-global-selector */
		$editLink = $( editLinkWrapper ).find( 'a' ),
		skin = mw.config.get( 'skin' ),
		AddLinkOnboarding = require( 'ext.growthExperiments.AddLink.onboarding' ),
		LinkSuggestionInteractionLogger = require( '../addlink/LinkSuggestionInteractionLogger.js' ),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		taskTypeId = suggestedEditSession.taskType,
		guidancePrefName = 'growthexperiments-homepage-suggestededits-guidance-blue-dot',
		errorDialogOnFailure = function () {
			var logger = new LinkSuggestionInteractionLogger( {
				/* eslint-disable camelcase */
				is_mobile: OO.ui.isMobile(),
				active_interface: 'nosuggestions_dialog'
				/* eslint-enable camelcase */
			} );
			logger.log( 'impression' );
			OO.ui.alert( mw.message( 'growthexperiments-addlink-no-suggestions-found-dialog-message' ).text(), {
				actions: [ { action: 'accept', label: mw.message( 'growthexperiments-addlink-no-suggestions-found-dialog-button' ).text(), flags: 'primary' } ]
			} ).done( function () {
				logger.log( 'close' );
				window.location.href = mw.Title.newFromText( 'Special:Homepage' ).getUrl();
			} );
		},
		guidancePrefValue;

	if ( taskTypeId === 'link-recommendation' &&
		mw.config.get( 'wgGELinkRecommendationsFrontendEnabled' ) &&
		suggestedEditSession.taskState === 'started' ) {
		if ( !suggestedEditSession.taskData ) {
			mw.log.error( 'Missing task data' );
			mw.errorLogger.logError( new Error( 'Missing task data' ) );
			errorDialogOnFailure();
		} else if ( suggestedEditSession.taskData.error ) {
			mw.log.error( suggestedEditSession.taskData.error );
			mw.errorLogger.logError( new Error( suggestedEditSession.taskData.error ) );
			errorDialogOnFailure();
		} else {

			if ( OO.ui.isMobile() ) {
				AddLinkOnboarding.showDialogIfEligible();
				// If we're on mobile and can add link recommendations, then rewrite the main
				// edit link on read mode to open "section=all", and hide section edit buttons.
				// The AddLink plugin only works when section=all is used.
				var editUri = new mw.Uri( $editLink.attr( 'href' ) );
				editUri.query.section = 'all';
				$editLink.attr( 'href', editUri.toString() );
				// eslint-disable-next-line no-jquery/no-global-selector
				$( '#mw-content-text' ).find( '.mw-editsection' ).hide();
			}

			if ( sourceEditingLinkWrapper && sourceEditingLinkWrapper !== editLinkWrapper ) {
				// FIXME: When edit mode toggle is implemented, remove this (T269653)
				sourceEditingLinkWrapper.remove();
			}
			mw.hook( 've.loadModules' ).add( function ( addPlugin ) {
				// Either the desktop or the mobile module will be registered, but not both.
				// Start with both, filter out the unregistered one, and add the remaining one
				// as a VE plugin.
				[
					'ext.growthExperiments.AddLink.desktop',
					'ext.growthExperiments.AddLink.mobile'
				].filter( mw.loader.getState ).forEach( function ( module ) {
					addPlugin( function () {
						return mw.loader.using( module );
					} );
				} );
			} );
		}
	}

	try {
		guidancePrefValue = JSON.parse( mw.user.options.get( guidancePrefName ) || {} );
	} catch ( e ) {
		// Pref value was mangled for whatever reason.
		mw.log.error( e );
		mw.errorLogger.logError( e );
		guidancePrefValue = {};
	}
	if ( !guidancePrefValue[ skin ] ) {
		guidancePrefValue[ skin ] = {};
	}

	if ( guidancePrefValue[ skin ][ taskTypeId ] ||
		taskTypeId === 'link-recommendation'
	) {
		// The user has already seen the blue dot for this task type and skin and clicked
		// "edit", or the dot doesn't make sense for this task type. Don't do anything else.
		return;
	}

	if ( $editLink.length && taskTypeId ) {
		$editLink.append( $( '<div>' ).addClass( 'mw-pulsating-dot' ) );
		guidancePrefValue[ skin ][ taskTypeId ] = true;
		$editLink.on( 'click', function () {
			new mw.Api().saveOption( guidancePrefName, JSON.stringify( guidancePrefValue ) );
			$( this ).find( '.mw-pulsating-dot' ).remove();
		} );
	}
}() );
