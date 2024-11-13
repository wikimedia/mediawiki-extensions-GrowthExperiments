const ArticleTextManipulator = require( './ArticleTextManipulator.js' );

class StructuredTaskSurfacer {

	/**
	 * @param {InstanceType<typeof mw.Api>} mwApi
	 * @param {ArticleTextManipulator} articleTextManipulator
	 * @param { { maxLinks: number; minScore:number } } config
	 */
	constructor( mwApi, articleTextManipulator, config ) {
		this.api = mwApi;
		this.articleTextManipulator = articleTextManipulator;
		this.config = config;
	}

	/**
	 * @typedef {Object} LinkRecommendation
	 * @property {string} link_text
	 * @property {number} score
	 */

	/**
	 * @typedef {Object} LinkRecommendationResponse
	 * @property {Array<LinkRecommendation>} recommendations
	 * @property {string} taskURL
	 */

	async loadRecommendations() {
		const articleId = mw.config.get( 'wgArticleId' );
		const response = await this.api.get( {
			action: 'query',
			list: 'linkrecommendations',
			lrpageid: articleId,
		} );

		const linkRecommendationResponse = this.extractRecommendationsFromResponse( response );
		if ( !linkRecommendationResponse ) {
			/**
			 * @type {Error & { customErrorContext?: Record<string,any> } }
			 */
			const error = new Error( 'recommendations response did not have the expected structure' );
			error.customErrorContext = { originalResponse: response };
			// @ts-ignore type to be added in https://github.com/wikimedia/typescript-types/pull/53
			mw.errorLogger.logError( error, 'error.growthexperiments' );
			return;
		}

		const recs = linkRecommendationResponse.recommendations;
		if ( recs.length === 0 ) {
			return;
		}

		const taskUrl = linkRecommendationResponse.taskURL;
		recs.sort(
			/**
			 * @param {{score: number}} a
			 * @param {{score: number}} b
			 * @return {number}
			 */
			( a, b ) => b.score - a.score,
		);

		const topRecs = recs.filter(
			( recommendation ) => recommendation.score >= this.config.minScore,
		).slice(
			0,
			this.config.maxLinks,
		);

		const wikitextRootElement = document.getElementById( 'mw-content-text' );
		if ( wikitextRootElement === null ) {
			throw new Error( 'wikitext root-element not found!' );
		}

		topRecs.forEach(
			/**
			 * @param {{link_text: string}} rec
			 */
			( rec ) => {
				const textToLink = rec.link_text;
				const buttonNode = this.createButtonNode( textToLink, taskUrl );
				const paragraphWithWord = this.articleTextManipulator.findFirstParagraphContainingText(
					wikitextRootElement,
					textToLink,
				);
				if ( paragraphWithWord === null ) {
					// REVIEW: This should never happen. Should we log it as an error?
					return;
				}
				this.articleTextManipulator.replaceDirectTextWithElement(
					paragraphWithWord,
					textToLink,
					buttonNode,
				);
			},
		);
	}

	/**
	 * @private
	 * @param { { query: { linkrecommendations: LinkRecommendationResponse } } } response
	 *
	 * @return {LinkRecommendationResponse|null}
	 */
	extractRecommendationsFromResponse( response ) {
		if ( !response.query ) {
			return null;
		}
		if ( !response.query.linkrecommendations ) {
			return null;
		}
		if ( !response.query.linkrecommendations.recommendations ) {
			return null;
		}
		return response.query.linkrecommendations;
	}

	/**
	 * @private
	 * @param {string} textToLink
	 * @param {string} taskUrl
	 * @return {Element}
	 */
	createButtonNode( textToLink, taskUrl ) {
		const textNode = document.createTextNode( textToLink );
		const iconNode = document.createElement( 'span' );
		iconNode.classList.add( 'cdx-button__icon', 'growth-surfaced-task-button__icon' );
		iconNode.ariaHidden = 'true';
		const buttonNode = document.createElement( 'button' );
		// REVIEW: not sure if it makes sense to base this on a cdx-button, given how different it is styled.
		buttonNode.classList.add( 'cdx-button', 'cdx-button--weight-quiet', 'growth-surfaced-task-button' );
		buttonNode.insertBefore( textNode, null );
		buttonNode.insertBefore( iconNode, null );
		buttonNode.addEventListener( 'click', () => {
			mw.notify(
				'TODO: show surfacing tasks popup now! Redirecting to edit-session...',
				{ type: 'success' },
			);
			buttonNode.classList.toggle( 'growth-surfaced-task-popup-visible' );
			window.setTimeout( () => {
				window.location.href = taskUrl;
			}, 3000 );
		} );
		return buttonNode;
	}
}

mw.loader.using( 'mediawiki.api', async () => {
	const api = new mw.Api( { format: 'json', formatversion: '2' } );
	const surfacer = new StructuredTaskSurfacer(
		api,
		new ArticleTextManipulator(),
		mw.config.get( 'wgGrowthExperimentsLinkRecommendationTask' ),
	);
	surfacer.loadRecommendations();
} );
