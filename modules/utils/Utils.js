( function () {

	// internal methods

	/**
	 * Update the user preferences
	 *
	 * @private
	 * @param {Object} prefData Updated preferences
	 * @return {JQuery.Promise<unknown>}
	 */
	function saveOptions( prefData ) {
		return mw.loader.using( 'mediawiki.api' ).then( () => new mw.Api().saveOptions( prefData ) );
	}

	/**
	 * Update the user preferences, reset the task cache and reload the page.
	 *
	 * @private
	 * @param {Object} prefData Updated preferences
	 * @return {JQuery.Promise<unknown>}
	 */
	function updateTaskPreference( prefData ) {
		return $.when( saveOptions( prefData ), mw.loader.using( 'mediawiki.util' ) ).then(
			// Do a cache reset as a variant switch will mess up caching.
			// FIXME T278123 remove when done.
			() => $.get( mw.util.getUrl( 'Special:Homepage', { resetTaskCache: 1 } ) ),
		).then( () => {
			window.location.reload();
		} );
	}

	// module exports used in other modules

	/**
	 * Serialize data for use with action_data event logging property.
	 *
	 * @param {Record<string,string|number>|string|boolean|number|Array<string|number>} data
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
				.map( ( key ) => key + '=' + data[ key ] )
				.join( ';' );
		}

		// assume it is string or number or bool
		return data;
	}

	/**
	 * Remove a query parameter from the URL, so the user does not see ugly URLs.
	 *
	 * @param {URL} url - Object created by new URL()
	 * @param {string|string[]} queryParam
	 *   The query param(s) to remove from the URL.
	 */
	function removeQueryParam( url, queryParam ) {
		let queryParams;
		if ( Array.isArray( queryParam ) ) {
			queryParams = queryParam;
		} else {
			queryParams = [ queryParam ];
		}

		if ( !queryParams.length ) {
			return;
		}

		queryParams.forEach( ( param ) => {
			url.searchParams.delete( param );
		} );

		let newUrl;
		if ( url.searchParams.size === 1 && url.searchParams.has( 'title' ) ) {
			// After removing the param only title remains. Rewrite to a prettier URL.
			const hash = url.hash;
			newUrl = mw.util.getUrl( /** @type {string} */ ( url.searchParams.get( 'title' ) + hash ) );
		} else {
			newUrl = url;
		}

		history.replaceState( history.state, document.title, newUrl.toString() );
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
			'other',
		].includes( editor );
	}

	/**
	 * Get the variant the user is assigned to, for A/B testing and gradual rollouts.
	 *
	 * @return {string}
	 */
	function getUserVariant() {
		const growthVariants = mw.config.get( 'wgGEUserVariants' );
		let variant = null;
		if ( mw.config.get( 'wgGEUseTestKitchenExtension' ) ) {
			const assignments = mw.testKitchen.getAssignments();
			const growthFormattedAssignments = Object.keys( assignments )
				.map( ( k ) => `${ k }_${ assignments[ k ] }` );
			// Should only be one
			const geExperimentVariants = growthFormattedAssignments
				.filter( ( value ) => growthVariants.includes( value ) );
			variant = geExperimentVariants.pop() || null;
		} else {
			variant = mw.user.options.get( 'growthexperiments-homepage-variant' );
		}

		if ( variant === null || !growthVariants.includes( variant ) ) {
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
		return variants.includes( getUserVariant() );
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
		let titleHash = '';
		const queryParams = {};
		if ( source ) {
			queryParams.source = source;
		}
		// @ts-expect-error OO.ui.isMobile() is not yet available in the upstream type definitions
		if ( OO.ui.isMobile() ) {
			titleHash = '#/homepage/suggested-edits';
			queryParams.overlay = 1;
		}
		// @ts-expect-error mw.Title.newFromText is not yet available in the upstream type definitions
		return mw.Title.newFromText(
			'Special:Homepage' + titleHash,
		).getUrl( queryParams );
	}

	/**
	 * Check wether Intl object and the APIs we use are present in the browser.
	 *
	 * @return {boolean}
	 */
	function hasIntl() {
		return typeof Intl !== 'undefined' &&
			Intl !== null &&
			( 'DateTimeFormat' in Intl ) &&
			( 'NumberFormat' in Intl ) &&
			( 'Locale' in Intl );
	}

	/**
	 * Get a local object for use with Intl. Should only be called when the browser supports Intl.
	 *
	 * @return {Intl.Locale}
	 */
	function getIntlLocale() {
		// Only specify user language and leave locale resolution to Intl instead of
		// using the MediaWiki fallback chain. This might or might not be a good idea.
		const language = mw.config.get( 'wgUserLanguage' ),
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
	 * @return {JQuery.Promise<unknown>}
	 */
	function setUserVariant( variant ) {
		if ( mw.config.get( 'wgGEUseTestKitchenExtension' ) ) {
			const growthVariants = mw.config.get( 'wgGEUserVariants' );
			if ( growthVariants.includes( variant ) ) {
				mw.testKitchen.overrideExperimentGroup( ...variant.split( '_' ) );
				return $.Deferred().resolve( true ).promise();
			} else {
				const warnMsg =
					'Failed attempt to set unrecognized variant. See allowed values in wgGEUserVariants';
				mw.log.warn( warnMsg );
				return $.Deferred().reject( warnMsg ).promise();
			}
		} else {
			return updateTaskPreference( {
				'growthexperiments-homepage-variant': variant,
			} );

		}
	}

	/**
	 * Normalize a label for statistics by replacing dots and hyphens with underscores.
	 *
	 * @param {string} label The label to normalize.
	 * @return {string} The normalized label.
	 */
	function normalizeLabelForStats( label ) {
		if ( !label ) {
			return label;
		}
		return label.replace( /\.|-/g, '_' );
	}

	// Expose some methods for debugging.
	// @ts-expect-error for debugging only, should not be used in production
	window.ge = window.ge || {};
	// @ts-expect-error for debugging only, should not be used in production
	ge.utils = { getUserVariant: getUserVariant, setUserVariant: setUserVariant };

	module.exports = {
		normalizeLabelForStats,
		serializeActionData: serializeActionData,
		removeQueryParam: removeQueryParam,
		isValidEditor: isValidEditor,
		isUserInVariant: isUserInVariant,
		getUserVariant: getUserVariant,
		formatTitle: formatTitle,
		getSuggestedEditsFeedUrl: getSuggestedEditsFeedUrl,
		getIntlLocale: getIntlLocale,
		hasIntl: hasIntl,
	};

}() );
