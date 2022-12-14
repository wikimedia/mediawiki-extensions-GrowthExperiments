( function () {

	// internal methods

	/**
	 * Update the user preferences
	 *
	 * @private
	 * @param {Object} prefData Updated preferences
	 * @return {jQuery.Promise}
	 */
	function saveOptions( prefData ) {
		return mw.loader.using( 'mediawiki.api' ).then( function () {
			return new mw.Api().saveOptions( prefData );
		} );
	}

	/**
	 * Update the user preferences, reset the task cache and reload the page.
	 *
	 * @private
	 * @param {Object} prefData Updated preferences
	 * @return {jQuery.Promise}
	 */
	function updateTaskPreference( prefData ) {
		return $.when( saveOptions( prefData ), mw.loader.using( 'mediawiki.util' ) ).then( function () {
			// Do a cache reset as a variant switch will mess up caching.
			// FIXME T278123 remove when done.
			return $.get( mw.util.getUrl( 'Special:Homepage', { resetTaskCache: 1 } ) );
		} ).then( function () {
			window.location.reload();
		} );
	}

	// module exports used in other modules

	/**
	 * Serialize data for use with action_data event logging property.
	 *
	 * @param {Object|string|boolean|number|Array} data
	 * @return {string|*}
	 */
	function serializeActionData( data ) {
		if ( !data ) {
			return '';
		}

		if ( Array.isArray( data ) ) {
			return data.join( ';' );
		}

		if ( typeof data === 'object' ) {
			return Object.keys( data )
				.map( function ( key ) {
					return key + '=' + data[ key ];
				} )
				.join( ';' );
		}

		// assume it is string or number or bool
		return data;
	}

	/**
	 * Remove a query parameter from the URL, so the user does not see ugly URLs.
	 *
	 * @param {Object} url
	 *  Object created by mw.Uri()
	 * @param {string|string[]} queryParam
	 *   The query param(s) to remove from the URL.
	 * @param {boolean} [useLiteralFragment]
	 *   Whether to keep the fragment as is (instead of encoding it)
	 */
	function removeQueryParam( url, queryParam, useLiteralFragment ) {
		var newUrl, fragment = '', queryParams;
		if ( Array.isArray( queryParam ) ) {
			queryParams = queryParam;
		} else {
			queryParams = [ queryParam ];
		}

		if ( !queryParams.length ) {
			return;
		}
		queryParams.forEach( function ( param ) {
			delete url.query[ param ];
		} );

		if ( Object.keys( url.query ).length === 1 && url.query.title ) {
			// After removing the param only title remains. Rewrite to a prettier URL.
			newUrl = mw.util.getUrl( url.query.title );
		} else {
			newUrl = url;
		}

		// mw.uri.toString encodes fragment by default.
		if ( useLiteralFragment && url.fragment ) {
			fragment = '#' + url.fragment;
			newUrl.fragment = '';
		}

		if ( history.replaceState ) {
			history.replaceState( history.state, document.title, newUrl.toString() + fragment );
		}
	}

	/**
	 * Checks whether an editor name is accepted by the EventLogging schemas used by the extension.
	 *
	 * @param {string} editor
	 * @return {boolean}
	 */
	function isValidEditor( editor ) {
		return [
			'wikitext',
			'wikitext-2017',
			'visualeditor',
			'other'
		].indexOf( editor ) >= 0;
	}

	/**
	 * Get the variant the user is assigned to, for A/B testing and gradual rollouts.
	 *
	 * @return {string}
	 */
	function getUserVariant() {
		var variant = mw.user.options.get( 'growthexperiments-homepage-variant' );
		if ( variant === null ||
			mw.config.get( 'wgGEUserVariants' ).indexOf( variant ) === -1
		) {
			variant = mw.config.get( 'wgGEDefaultUserVariant' );
		}
		return variant;
	}

	/**
	 * @param {string|string[]} variants
	 * @return {boolean}
	 */
	function isUserInVariant( variants ) {
		if ( typeof variants === 'string' ) {
			variants = [ variants ];
		}
		return variants.indexOf( getUserVariant() ) !== -1;
	}

	/**
	 * Format title to be used in URLs
	 *
	 * @param {string} title
	 * @return {string}
	 */
	function formatTitle( title ) {
		return encodeURIComponent( title.replace( / /g, '_' ) );
	}

	/**
	 * Get the URL to the suggested edits feed (Special:Homepage on desktop and suggested edits
	 * overlay on top of Special:Homepage on mobile)
	 *
	 * @param {string} [source] How the user arrived Special:Homepage; value should correspond to
	 *  referer_route enum in HomepageVisit schema
	 *  @return {string}
	 */
	function getSuggestedEditsFeedUrl( source ) {
		var titleHash = '',
			queryParams = {};
		if ( source ) {
			queryParams.source = source;
		}
		if ( OO.ui.isMobile() ) {
			titleHash = '#/homepage/suggested-edits';
			queryParams.overlay = 1;
		}
		return mw.Title.newFromText(
			'Special:Homepage' + titleHash
		).getUrl( queryParams );
	}

	/**
	 * Get a local object for use with Intl. Should only be called when the browser supports Intl.
	 *
	 * @return {Intl.Locale}
	 */
	function getIntlLocale() {
		// Only specify user language and leave locale resolution to Intl instead of
		// using the MediaWiki fallback chain. This might or might not be a good idea.
		var language = mw.config.get( 'wgUserLanguage' ),
			languageOptions = mw.config.get( 'wgTranslateNumerals' ) ? {} : { numberingSystem: 'latn' };
		// eslint-disable-next-line compat/compat
		return new Intl.Locale( language, languageOptions );
	}

	// debug / QA helpers exposed via ge.utils

	/**
	 * Set the variant the user is assigned to, for A/B testing and gradual rollouts.
	 *
	 * @private For debug/QA purposes only.
	 * @param {string|null} variant The new variant, or null to unset.
	 * @return {jQuery.Promise}
	 */
	function setUserVariant( variant ) {
		return updateTaskPreference( {
			'growthexperiments-homepage-variant': variant
		} );
	}

	/**
	 * Opt the user into growth-glam-2022 campaign
	 *
	 * @private For debug/QA purposes only.
	 * @param {string} id
	 * @return {jQuery.Promise|undefined}
	 */
	function enableCampaign( id ) {
		return saveOptions( {
			'growthexperiments-campaign': id
		} );
	}

	// Expose some methods for debugging.
	window.ge = window.ge || {};
	ge.utils = {
		getUserVariant: getUserVariant,
		setUserVariant: setUserVariant,
		enableCampaign: enableCampaign
	};

	module.exports = {
		serializeActionData: serializeActionData,
		removeQueryParam: removeQueryParam,
		isValidEditor: isValidEditor,
		isUserInVariant: isUserInVariant,
		getUserVariant: getUserVariant,
		formatTitle: formatTitle,
		getSuggestedEditsFeedUrl: getSuggestedEditsFeedUrl,
		getIntlLocale: getIntlLocale
	};

}() );
