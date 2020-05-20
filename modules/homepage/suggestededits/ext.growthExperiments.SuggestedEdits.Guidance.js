( function () {
	'use strict';
	/* eslint-disable-next-line no-jquery/no-global-selector */
	var $edit = $( 'li#ca-ve-edit, li#ca-edit, li#page-actions-edit' ).first().find( 'a' ),
		skin = mw.config.get( 'skin' ),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		taskTypeId = suggestedEditSession.taskType,
		guidancePrefName = 'growthexperiments-homepage-suggestededits-guidance-blue-dot',
		guidancePrefValue;

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

	if ( $edit.length && taskTypeId ) {
		$edit.append( $( '<div>' ).addClass( 'mw-pulsating-dot' ) );
		guidancePrefValue[ skin ][ taskTypeId ] = true;
		$edit.on( 'click', function () {
			new mw.Api().saveOption( guidancePrefName, JSON.stringify( guidancePrefValue ) );
			$( this ).find( '.mw-pulsating-dot' ).remove();
		} );
	}
}() );
