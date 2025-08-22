( function () {
	'use strict';
	/* eslint-disable no-jquery/no-global-selector */
	// We can't use $( 'li#ca-ve-edit, li#ca-edit, li#page-actions-edit' ).first() here,
	// because that returns the one that appears first in the DOM. Instead, we want to use
	// #ca-ve-edit if it exists, and only if that doesn't exist fall back to #ca-edit (T261001)
	const veEditLinkWrapper = $( 'li#ca-ve-edit' )[ 0 ],
		sourceEditingLinkWrapper = $( 'li#ca-edit' )[ 0 ],
		pageActionsEditWrapper = $( 'li#page-actions-edit' )[ 0 ],
		editLinkWrapper = veEditLinkWrapper ||
			sourceEditingLinkWrapper ||
			pageActionsEditWrapper,
		/* eslint-enable no-jquery/no-global-selector */
		$editLink = $( editLinkWrapper ).find( 'a' ),
		skin = mw.config.get( 'skin' ),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		taskTypeId = suggestedEditSession.taskType,
		guidancePrefName = 'growthexperiments-homepage-suggestededits-guidance-blue-dot',
		StructuredTaskPreEdit = require( 'ext.growthExperiments.StructuredTask.PreEdit' );

	if ( StructuredTaskPreEdit.shouldInitializeStructuredTask() ) {
		StructuredTaskPreEdit.checkTaskData().then( () => {
			if ( !mw.config.get( 'wgIsProbablyEditable' ) ) {
				// article is protected, show "no suggestions found" dialog
				throw new Error( 'Page is protected, abandoning structured task' );
			}
			if ( OO.ui.isMobile() ) {
				StructuredTaskPreEdit.showOnboardingIfEligible();
				// If we're on mobile and can add link recommendations, then rewrite the main
				// edit link on read mode to open "section=all", and hide section edit buttons.
				// The AddLink plugin only works when section=all is used.
				StructuredTaskPreEdit.updateEditLinkSection( $editLink, 'all' );
				// eslint-disable-next-line no-jquery/no-global-selector
				$( '#mw-content-text' ).find( '.mw-editsection' ).hide();
			}

			if ( sourceEditingLinkWrapper && sourceEditingLinkWrapper !== editLinkWrapper ) {
				// FIXME: When edit mode toggle is supported in WikiEditor, remove this (T285785)
				sourceEditingLinkWrapper.remove();
			}
			StructuredTaskPreEdit.loadEditModule();
		} ).catch( ( error, shouldBeLogged ) => {
			StructuredTaskPreEdit.showErrorDialogOnFailure( error, shouldBeLogged );
		} );
	}

	let guidancePrefValue;
	try {
		guidancePrefValue = JSON.parse( mw.user.options.get( guidancePrefName ) || {} );
	} catch ( e ) {
		// Pref value was mangled for whatever reason.
		mw.log.error( e );
		mw.errorLogger.logError( e, 'error.growthexperiments' );
		guidancePrefValue = {};
	}
	if ( !guidancePrefValue[ skin ] ) {
		guidancePrefValue[ skin ] = {};
	}

	if ( guidancePrefValue[ skin ][ taskTypeId ] ||
		taskTypeId === 'link-recommendation' ||
		taskTypeId === 'image-recommendation' ||
		taskTypeId === 'section-image-recommendation' ||
		taskTypeId === 'improve-tone'
	) {
		// The user has already seen the blue dot for this task type and skin and clicked
		// "edit", or the dot doesn't make sense for this task type. Don't do anything else.
		return;
	}

	if ( $editLink.length && taskTypeId ) {
		$editLink.prepend( $( '<div>' ).addClass( 'mw-pulsating-dot' ) );
		guidancePrefValue[ skin ][ taskTypeId ] = true;
		$editLink.on( 'click', function () {
			new mw.Api().saveOption( guidancePrefName, JSON.stringify( guidancePrefValue ) );
			$( this ).find( '.mw-pulsating-dot' ).remove();
		} );
	}
}() );
