const suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
	TASK_TYPES = require( 'ext.growthExperiments.DataStore' ).CONSTANTS.ALL_TASK_TYPES;

/**
 * @typedef LinkRecommendationLink
 * @property {string} link_text Text to look for
 * @property {string} link_target Name of the article the phrase should link to
 * @property {number} match_index Which occurrence of the phrase to look for, zero-indexed
 * @property {number} wikitext_offset The 0-based index of the first character of the link text
 *   in the wikitext, in Unicode characters.
 * @property {number} score Probability/confidence that this is a good link
 * @property {string} context_before Small amount of text that occurs immediately before the phrase
 * @property {string} context_after Small amount of text that occurs immediately after the phrase
 * @property {number} link_index Position in the order of all recommendations, zero-indexed
 */

/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by AddLinkDesktopArticleTarget and
 * AddLinkMobileArticleTarget.
 *
 * @param {mw.libs.ge.LinkSuggestionInteractionLogger} logger For testing purposes; desktop/mobile
 *  version of this class have a logger injected already.
 * @mixin mw.libs.ge.AddLinkArticleTarget
 * @extends ve.init.mw.ArticleTarget
 */
function AddLinkArticleTarget( logger ) {
	this.logger = logger;
	this.maximumLinksToShow = ( TASK_TYPES[ 'link-recommendation' ] || {} ).maximumLinksToShowPerTask;
}

/**
 * Implementations should call this in loadSuccess(), before calling the parent method.
 * It will modify the API response by adding link recommendation annotations to the
 * page HTML.
 *
 * @param {Object} response Response from the visualeditor or visualeditoredit API,
 *   passed to loadSuccess(). Will be modified.
 */
AddLinkArticleTarget.prototype.beforeLoadSuccess = function ( response ) {
	if ( !response ) {
		return;
	}
	const data = response.visualeditor || response.visualeditoredit;
	const doc = ve.createDocumentFromHtml( data.content );
	const addlinkData = suggestedEditSession.taskData;
	const recommendationId = new URL( window.location ).searchParams.get( 'gerecommendationid' );
	if ( recommendationId ) {
		// Since there's no UI support for a single link recommendation, create an array of one element
		addlinkData.links = [ suggestedEditSession.taskData.links[ recommendationId ] ];
	}
	// TODO start loading this earlier (T267691)
	this.annotateSuggestions( doc, addlinkData.links );
	data.content = '<!doctype html>' + ve.properOuterHtml( doc.documentElement );
};

/**
 * Set linkRecommendationFragments on the surface before it's ready
 *
 * @override
 */
AddLinkArticleTarget.prototype.beforeStructuredTaskSurfaceReady = function () {
	// HACK RecommendedLinkToolbarDialog doesn't have access to the target, so give it access to the
	// link recommendation data by adding a property to the ui.Surface
	this.getSurface().linkRecommendationFragments = this.findRecommendationFragments();
};

/**
 * Select the first recommendation after the surface is ready
 *
 * @override
 */
AddLinkArticleTarget.prototype.afterStructuredTaskSurfaceReady = function () {
	// On mobile, the surface is not yet attached to the DOM when this runs, so wait for that
	// to happen. On desktop, the surface is already attached, and we can do this immediately.
	if ( OO.ui.isMobile() ) {
		this.overlay.on( 'editor-loaded', this.selectFirstRecommendation.bind( this ) );
	} else {
		// On desktop, the recommendation is selected after onboarding has been completed
		mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).add(
			this.selectFirstRecommendation.bind( this )
		);
		mw.hook( 'growthExperiments.structuredTask.showOnboardingIfNeeded' ).fire();
	}
};

/** @inheritDoc */
AddLinkArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response );
	this.constructor.super.prototype.loadSuccess.call( this, response );
};

/**
 * Open RecommendedLinkToolbarDialog with the first recommendation selected
 */
AddLinkArticleTarget.prototype.selectFirstRecommendation = function () {
	this.getSurface().executeCommand( 'recommendedLink' );
};

/** @override **/
AddLinkArticleTarget.prototype.restoreScrollPosition = function () {
	// Don't restore the saved scroll position, because we've selected the first link recommendation
	// and scrolled to it
};

/**
 * Find all mwGeRecommendedLink annotations in the document, and build a data structure with their
 * IDs and SurfaceFragments representing the range they cover. If the annotations move around
 * because of changes elsewhere in the document, these SurfaceFragments update automatically.
 *
 * @private
 * @return {{ recommendationWikitextOffset: number, fragment: ve.dm.SurfaceFragment }[]}
 */
AddLinkArticleTarget.prototype.findRecommendationFragments = function () {
	let lastRecommendationWikitextOffset = null;
	const surfaceModel = this.getSurface().getModel(),
		data = surfaceModel.getDocument().data,
		dataLength = data.getLength(),
		recommendationRanges = {};

	for ( let i = 0; i < dataLength; i++ ) {
		// TODO maybe this could be more efficient (T267693)
		const annotations = data.getAnnotationsFromOffset( i ).getAnnotationsByName( 'mwGeRecommendedLink' );
		if ( annotations.getLength() ) {
			const thisRecommendationWikitextOffset = annotations.get( 0 )
				.getAttribute( 'recommendationWikitextOffset' );
			if ( thisRecommendationWikitextOffset === lastRecommendationWikitextOffset ) {
				// Continuation of the current annotation
				recommendationRanges[ lastRecommendationWikitextOffset ][ 1 ] = i + 1;
			} else {
				// Start of a new annotation
				recommendationRanges[ thisRecommendationWikitextOffset ] = [ i, i + 1 ];
				lastRecommendationWikitextOffset = thisRecommendationWikitextOffset;
			}
		} else {
			// If we had an activate annotation, mark it as having ended
			lastRecommendationWikitextOffset = null;
		}
	}

	return Object.keys( recommendationRanges )
		// Object.keys() is not guaranteed to return keys in insertion order, so sort the ranges
		// by start offset
		.sort( ( a, b ) => recommendationRanges[ a ][ 0 ] - recommendationRanges[ b ][ 0 ] )
		.map( ( recommendationWikitextOffset ) => ( {
			recommendationWikitextOffset: recommendationWikitextOffset,
			fragment: surfaceModel.getLinearFragment( new ve.Range(
				recommendationRanges[ recommendationWikitextOffset ][ 0 ],
				recommendationRanges[ recommendationWikitextOffset ][ 1 ]
			) )
		} ) );
};

/**
 * Find the suggested links in the document, and annotate them.
 *
 * Link recommendations are annotated by wrapping them in <span typeof="mw:RecommendedLink"> tags,
 * with the data-target attribute set to the suggested link target.
 *
 * When searching for phrases to annotate, word boundaries are implied on either side. For example,
 * if suggestions contains an element with { text: 'foo' }, this method will search for the regex
 * /\bfoo\b/
 *
 * @private
 * @param {HTMLDocument} doc Document to find and annotate links in
 * @param {LinkRecommendationLink[]} suggestions Description of suggested links
 */
AddLinkArticleTarget.prototype.annotateSuggestions = function ( doc, suggestions ) {
	const phraseMap = {},
		annotations = [],
		treeWalker = this.getTreeWalker( doc );
	let phraseMapKeys = [],
		numberOfLinksShown = 0;
	/**
	 * Build a regex that matches any of the given phrases.
	 *
	 * @private
	 * @param {string[]} phrases
	 * @return {RegExp} A regex that looks like /phrase one|phrase two|..../g
	 */
	function buildRegex( phrases ) {
		// FIXME or describe why it is okay

		return new RegExp( phrases.map( mw.util.escapeRegExp ).join( '|' ), 'g' );
	}

	/**
	 * Annotate a single suggestion in a DOM node
	 *
	 * @private
	 * @param {Object} annotation
	 * @param {Object} annotation.postText
	 * @param {Object} annotation.linkText
	 * @param {Object} annotation.suggestion
	 */
	function annotateSuggestion( annotation ) {
		// Wrap linkText in a <span typeof="mw:RecommendedLink"> tag
		const linkWrapper = doc.createElement( 'span' );
		linkWrapper.setAttribute( 'typeof', 'mw:RecommendedLink' );
		linkWrapper.setAttribute( 'data-target', annotation.suggestion.link_target );
		linkWrapper.setAttribute( 'data-text', annotation.suggestion.link_text );
		// TODO probably use wikitext offset
		linkWrapper.setAttribute( 'data-wikitext-offset', annotation.suggestion.wikitext_offset );
		linkWrapper.setAttribute( 'data-score', annotation.suggestion.score );
		linkWrapper.appendChild( annotation.linkText );
		annotation.postText.parentNode.insertBefore( linkWrapper, annotation.postText );
	}

	let phrase;
	// For each phrase, gather the link targets for that phrase and the occurrence number for each
	// link target, and start an occurrence counter. There will typically be only one link target
	// per phrase, but this data structure supports multiple link targets for different occurrences
	// of the same phrase.
	// If suggestions contains { text: 'foo', index: 2, target: 'bar' }, then
	// phraseMap will contain { 'foo': { occurrencesSeen: 0, linkTargets: { 2: 'bar' } } }
	for ( let i = 0; i < suggestions.length; i++ ) {
		if ( !phraseMap[ suggestions[ i ].link_text ] ) {
			phraseMap[ suggestions[ i ].link_text ] = {
				occurrencesSeen: 0,
				suggestions: {}
			};
		}
		phrase = phraseMap[ suggestions[ i ].link_text ];
		phrase.suggestions[ suggestions[ i ].match_index ] = suggestions[ i ];
	}

	// Build a regex that matches any phrase in phraseMap. We remove phrases from phraseMap when
	// we've found them, so if phraseMap is empty we'll know we're done.
	let regex = buildRegex( Object.keys( phraseMap ) );
	let anythingLeft = Object.keys( phraseMap ).length > 0;
	let textNode = treeWalker.nextNode();
	// TODO: deal with span-wrapped entities, and possibly with partially-annotated phrases
	//   (T267695)
	while ( anythingLeft && textNode ) {
		// Move the TreeWalker forward before we do anything. This avoids the need for confusing
		// trickery later if we change the DOM to add a wrapper
		const nextNode = treeWalker.nextNode();

		// Using .exec() and .lastIndex allows us to find multiple matches of the regex in a loops
		regex.lastIndex = 0;
		let match;
		while ( anythingLeft && ( match = regex.exec( textNode.data ) ) ) {
			phrase = phraseMap[ match[ 0 ] ];
			const suggestion = phrase.suggestions[ phrase.occurrencesSeen ];
			if ( suggestion ) {
				// Split the textNode in three parts: before the matched phrase (textNode),
				// the matched phrase (linkText), and the text after the matched phrase (postText)
				const linkText = textNode.splitText( match.index );
				const postText = linkText.splitText( match[ 0 ].length );

				// Save the matched phrase and the text after and the suggestion
				// instead of inserting the annotation so we can apply
				// filters and sorting to all the annotations in the document
				annotations.push( {
					postText: postText,
					linkText: linkText,
					suggestion: suggestion
				} );
				// In the next iteration of the loop, search postText for any additional phrase
				// matches, and reset regex.lastIndex accordingly
				textNode = postText;
				regex.lastIndex = 0;

				// Delete the link that we just found from phraseMap. If there are no more links for
				// this phrase, delete the phrase and rebuild the regex without it.
				delete phrase.suggestions[ phrase.occurrencesSeen ];
				if ( Object.keys( phrase.suggestions ).length === 0 ) {
					delete phraseMap[ match[ 0 ] ];
					regex = buildRegex( Object.keys( phraseMap ) );
					anythingLeft = Object.keys( phraseMap ).length > 0;
				}
			}
			phrase.occurrencesSeen++;
		}

		textNode = nextNode;
	}

	// Sort the annotations by highest accuracy (suggestion score) to show the
	// most relevant links. We might review this decision in the future, see
	// https://phabricator.wikimedia.org/T301095#7739231
	annotations.sort( ( a, b ) => b.suggestion.score - a.suggestion.score );
	// Annotate suggestions until the number of links to show is
	// reached or the are not more annotations found left
	while ( annotations.length > 0 && numberOfLinksShown < this.maximumLinksToShow ) {
		annotateSuggestion( annotations.shift() );
		numberOfLinksShown++;
	}
	phraseMapKeys = Object.keys( phraseMap );
	if ( phraseMapKeys.length > 0 ) {
		// If any items are remaining in the phrase map, that means we failed to locate them
		// in the document.
		phraseMapKeys.forEach( ( phraseItem ) => {
			mw.log.error( 'Failed to locate "' + phraseItem + '" (occurrences seen: ' +
				phraseMap[ phraseItem ].occurrencesSeen + ') in document.' );
		} );
		mw.errorLogger.logError( new Error(
			'Unable to find ' + phraseMapKeys.length + ' link recommendation phrase item(s) in document.'
		), 'error.growthexperiments' );
	}

	this.logger.log( 'impression', {
		/* eslint-disable camelcase */
		number_phrases_found: suggestions.length - phraseMapKeys.length,
		number_phrases_expected: suggestions.length,
		number_phrases_shown: numberOfLinksShown
	}, {
		active_interface: 'machinesuggestions_mode'
		/* eslint-enable camelcase */
	} );
};

/**
 * Creates a tree walker which will iterate all the DOM nodes where a link suggestion might appear.
 * The link recommendation service specifies the position of link suggestions in the document via
 * match count (e.g. "the third occurrence of the string 'Foo' in the text"), but it only counts
 * matches in plain text, not inside templates, tables etc. To interpret the match count correctly,
 * we need to reproduce that logic here (as much as possible, given that the service uses the
 * wikitext AST and we use the Parsoid DOM).
 *
 * @param {HTMLDocument} doc
 * @return {TreeWalker}
 */
AddLinkArticleTarget.prototype.getTreeWalker = function ( doc ) {
	function startsWith( str, prefix ) {
		return str && str.slice( 0, prefix.length ) === prefix;
	}

	return doc.createTreeWalker(
		doc.body,
		// eslint-disable-next-line no-bitwise
		NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT,
		{ acceptNode: function ( node ) {
			if ( node.nodeType === Node.TEXT_NODE ) {
				return NodeFilter.FILTER_ACCEPT;
			}

			// Exclude all DOM nodes produced by Parsoid. Partially based on
			// https://www.mediawiki.org/wiki/Specs/HTML/2.2.0
			if (
				// transcluded content, media, most other things
				startsWith( node.getAttribute( 'typeof' ), 'mw:' ) ||
				// links and link-like things
				startsWith( node.getAttribute( 'rel' ), 'mw:' ) ||
				// metadata (though it has no content so doesn't really matter)
				startsWith( node.getAttribute( 'property' ), 'mw:' ) ||
				// part of a transcluded DOM forest such as a template with multiple
				// top-level nodes; typeof is only present on the first node
				node.getAttribute( 'about' ) ||
				// HTML representation of some basic wikitext contructs
				// OL/UL are absent because of the weird way mwparserfromhell (the parser used
				// by the link recommendation service) handles them, see
				// https://github.com/earwig/mwparserfromhell/issues/46
				[ 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'TABLE', 'B', 'I', 'BLOCKQUOTE' ].indexOf( node.tagName ) !== -1
			) {
				return NodeFilter.FILTER_REJECT;
			}

			// An element we did not exclude above, probably <p> or <section>. SKIP means do
			// not return it (we only want to return text nodes), but descend into it.
			return NodeFilter.FILTER_SKIP;
		} }
	);
};

/**
 * Check whether any of the annotation states meet the specified condition
 *
 * @param {Function} checkFn
 * @return {boolean}
 */
AddLinkArticleTarget.prototype.checkAnnotationStates = function ( checkFn ) {
	if ( !this.getSurface().linkRecommendationFragments ) {
		// Too early; we can assume there are no changes to save.
		// FIXME need to re-check this if we fix restoring abandoned edits.
		return false;
	}
	return this.getAnnotationStates().some( ( state ) => checkFn( state ) );
};

/**
 * Check whether the user has accepted any suggestions
 *
 * @override
 */
AddLinkArticleTarget.prototype.hasEdits = function () {
	return this.checkAnnotationStates( ( state ) => state.accepted );
};

/**
 * Check whether the user has rejected any suggestions
 *
 * @return {boolean}
 */
AddLinkArticleTarget.prototype.hasRejectedSuggestions = function () {
	return this.checkAnnotationStates( ( state ) => state.rejected );
};

/**
 * Check whether the user has accepted or rejected any suggestions
 *
 * @override
 */
AddLinkArticleTarget.prototype.hasReviewedSuggestions = function () {
	return this.hasEdits() || this.hasRejectedSuggestions();
};

/** @inheritDoc */
AddLinkArticleTarget.prototype.save = function ( doc, options, isRetry ) {
	const acceptedTargets = [],
		rejectedTargets = [],
		skippedTargets = [],
		annotationStates = this.getAnnotationStates();
	annotationStates.forEach( ( state ) => {
		if ( state.accepted ) {
			acceptedTargets.push( state.title );
		} else if ( state.rejected ) {
			rejectedTargets.push( state.title );
		} else {
			skippedTargets.push( state.title );
		}
	} );
	// This data will be processed in VisualEditorHooks::onVisualEditorApiVisualEditorEditPostSave
	options[ 'data-ge-task-link-recommendation' ] = JSON.stringify( {
		taskType: 'link-recommendation',
		acceptedTargets: acceptedTargets,
		rejectedTargets: rejectedTargets,
		skippedTargets: skippedTargets
	} );
	options.plugins = 'ge-task-link-recommendation';
	return this.constructor.super.prototype.save.call( this, doc, options, isRetry )
		.done( () => {
			const hasAccepts = annotationStates.some( ( state ) => state.accepted );
			if ( !hasAccepts ) {
				this.madeNullEdit = true;
			}
		} );
};

/** @inheritDoc **/
AddLinkArticleTarget.prototype.saveErrorHookAborted = function ( data ) {
	const errors = data.errors || [],
		error = errors[ 0 ] || {},
		errorData = error.data || '',
		errorMessage = errorData.message || [],
		errorMessageKey = errorMessage[ 0 ] || '';
	// For the not-in-store & anonymous user paths, handle the errors ourselves, otherwise
	// let VE do the error handling.
	if ( errorMessageKey === 'growthexperiments-structuredtask-anonuser' ) {
		this.saveErrorNewUser();
		return;
	}
	if ( errorMessageKey !== 'growthexperiments-addlink-notinstore' ) {
		return this.constructor.super.prototype.saveErrorHookAborted.call( this, data );
	}
	this.logger.log( 'impression', {}, {
		// eslint-disable-next-line camelcase
		active_interface: 'outdatedsuggestions_dialog'
	} );
	this.getSurface().getDialogs().currentWindow.close();
	window.onbeforeunload = null;
	$( window ).off( 'beforeunload' );
	OO.ui.alert( mw.message( 'growthexperiments-addlink-suggestions-outdated' ).text(), {
		actions: [ { action: 'accept', label: mw.message( 'growthexperiments-structuredtask-no-suggestions-found-dialog-button' ).text(), flags: 'primary' } ]
	} ).done( () => {
		window.location.href = mw.Title.newFromText( 'Special:Homepage' ).getUrl();
	} );
};

/**
 * Get the current state of the recommendations (ie. the feedback the user gave on them).
 *
 * @return {Array} A list of objects with the following fields:
 *   - title: the link target
 *   - text: the link text
 *   - accepted/rejected/skipped: user feedback (exactly one of these will be true)
 *   - rejectionReason: the rejection option chosen by the user, when rejected
 */
AddLinkArticleTarget.prototype.getAnnotationStates = function () {
	const states = [];
	this.getSurface().linkRecommendationFragments.forEach( ( recommendation ) => {
		const annotations = recommendation.fragment
			.getAnnotations()
			.getAnnotationsByName( 'mwGeRecommendedLink' );

		if ( !annotations.storeHashes.length ) {
			// Avoid throwing errors further below when no store hashes exist (e.g. if the user
			// toggles No for a suggestion).
			return;
		}
		if ( annotations.getLength() !== 1 ) {
			mw.log.error( 'annotation not found for offset ' + recommendation.recommendationWikitextOffset );
			mw.errorLogger.logError( new Error( 'annotation not found for offset ' +
				recommendation.recommendationWikitextOffset ), 'error.growthexperiments' );
			return;
		}
		const annotation = annotations.get( 0 );

		// Despite the name, getDisplayTitle() is the title, not the display title.
		const state = {
			title: annotation.getDisplayTitle(),
			text: annotation.getOriginalDomElements( annotation.getStore() )
				.map( ( element ) => element.textContent ).join( '' )
		};
		if ( annotation.isAccepted() ) {
			state.accepted = true;
		} else if ( annotation.isRejected() ) {
			state.rejected = true;
			state.rejectionReason = annotation.getRejectionReason();
		} else {
			state.skipped = true;
		}
		states.push( state );
	} );
	return states;
};

/** @inheritDoc **/
AddLinkArticleTarget.prototype.onSaveComplete = function ( data ) {
	const linkRecWarningKey = 'gelinkrecommendationdailytasksexceeded',
		geWarnings = data.gewarnings || [];

	geWarnings.forEach( ( warning ) => {
		if ( warning[ linkRecWarningKey ] ) {
			suggestedEditSession.qualityGateConfig[ 'link-recommendation' ] = { dailyLimit: true };
			suggestedEditSession.save();
		}
	} );
};

module.exports = AddLinkArticleTarget;
