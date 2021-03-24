( function () {
	'use strict';
	/* eslint-disable no-jquery/no-global-selector */
	// We can't use $( 'li#ca-ve-edit, li#ca-edit, li#page-actions-edit' ).first() here,
	// because that returns the one that appears first in the DOM. Instead, we want to use
	// #ca-ve-edit if it exists, and only if that doesn't exist fall back to #ca-edit (T261001)
	var editLinkWrapper = $( 'li#ca-ve-edit' )[ 0 ] ||
			$( 'li#ca-edit' )[ 0 ] ||
			$( 'li#page-actions-edit' )[ 0 ],
		/* eslint-enable no-jquery/no-global-selector */
		$editLink = $( editLinkWrapper ).find( 'a' ),
		skin = mw.config.get( 'skin' ),
		AddLinkOnboarding = require( 'ext.growthExperiments.AddLink.onboarding' ),
		router = require( 'mediawiki.router' ),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		taskTypeId = suggestedEditSession.taskType,
		guidancePrefName = 'growthexperiments-homepage-suggestededits-guidance-blue-dot',
		errorDialogOnFailure = function () {
			OO.ui.alert( mw.message( 'growthexperiments-addlink-no-suggestions-found-dialog-message' ).text(), {
				actions: [ { action: 'accept', label: mw.message( 'growthexperiments-addlink-no-suggestions-found-dialog-button' ).text(), flags: 'primary' } ]
			} ).done( function () {
				// TODO: Instrumentation (T278112)
				router.back();
			} );
		},
		guidancePrefValue,
		uri;

	if ( taskTypeId === 'link-recommendation' && mw.config.get( 'wgGELinkRecommendationsFrontendEnabled' ) ) {
		if ( !suggestedEditSession.taskData ) {
			mw.log.error( 'Missing task data' );
			errorDialogOnFailure();
		} else if ( suggestedEditSession.taskData.error ) {
			mw.log.error( suggestedEditSession.taskData.error );
			errorDialogOnFailure();
		} else {
			AddLinkOnboarding.showDialogIfEligible();

			mw.hook( 've.loadModules' ).add( function ( addPlugin ) {
				// Either the desktop or the mobile module will be registered, but not both.
				// Start with both, filter out the unregistered one, and add the remaining one
				// as a VE plugin.
				[
					'ext.growthExperiments.AddLink.desktop',
					'ext.growthExperiments.AddLink.mobile'
				].filter( mw.loader.getState ).forEach( function ( module ) {
					addPlugin( module );
				} );
			} );

			if ( suggestedEditSession.shouldOpenArticleInEditMode && $editLink.length ) {
				uri = new mw.Uri();
				if ( uri.query.section ) {
					$editLink.attr(
						'href',
						uri.clone().extend( { section: uri.query.section } )
					);
				}
				$editLink[ 0 ].click();
			}
		}
	}

	try {
		guidancePrefValue = JSON.parse( mw.user.options.get( guidancePrefName ) || {} );
	} catch ( e ) {
		// Pref value was mangled for whatever reason.
		guidancePrefValue = {};
	}
	if ( !guidancePrefValue[ skin ] ) {
		guidancePrefValue[ skin ] = {};
	}

	if ( guidancePrefValue[ skin ][ taskTypeId ] ) {
		// The user has already seen the blue dot for this task type and skin and
		// clicked "edit", don't do anything else.
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
