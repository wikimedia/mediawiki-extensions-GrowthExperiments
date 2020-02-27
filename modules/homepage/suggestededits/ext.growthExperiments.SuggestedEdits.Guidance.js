( function () {
	'use strict';
	// @see extensions/VisualEditor/modules/ve-mw/preinit/ve.init.mw.DesktopArticleTarget.init.js
	var Utils = require( '../../utils/ext.growthExperiments.Utils.js' ),
		/* eslint-disable-next-line no-jquery/no-global-selector */
		$caEdit = $( 'li#ca-edit, li#page-actions-edit' ),
		/* eslint-disable-next-line no-jquery/no-global-selector */
		$caVeEdit = $( 'li#ca-ve-edit' ),
		$caEditLink = $caEdit.find( 'a' ),
		$caVeEditLink = $caVeEdit.find( 'a' ),
		$edit = null,
		skin = mw.config.get( 'skin' ),
		url = new mw.Uri( window.location.href ),
		taskTypeId = url.query.getasktype,
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

	Utils.removeQueryParam( url, 'getasktype' );

	if ( guidancePrefValue[ skin ][ taskTypeId ] ) {
		// The user has already seen the blue dot for this task type and skin and
		// clicked "edit", don't do anything else.
		return;
	}

	if ( $caVeEditLink.length ) {
		$edit = $caVeEditLink;
	} else if ( $caEditLink.length ) {
		$edit = $caEditLink;
	}
	if ( $edit && $edit.length && taskTypeId ) {
		$edit.append( $( '<div>' ).addClass( 'mw-pulsating-dot' ) );
		guidancePrefValue[ skin ][ taskTypeId ] = true;
		$edit.on( 'click', function () {
			new mw.Api().saveOption( guidancePrefName, JSON.stringify( guidancePrefValue ) );
		} );
	}
}() );
