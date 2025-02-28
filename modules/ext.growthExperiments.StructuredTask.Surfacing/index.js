const ArticleTextManipulator = require( './ArticleTextManipulator.js' );
const SurfacedTaskPopup = require( './SurfacedTaskPopup.js' );
const PageSummaryRepository = require( './PageSummaryRepository.js' );
const LinkSuggestionInteractionLogger = require(
	'../ext.growthExperiments.StructuredTask/addlink/LinkSuggestionInteractionLogger.js',
);

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
		this.trackingCounterPrefix = 'counter.growthExperiments.SuggestedEdits.Surfacing.LinkRecommendation.' +
			mw.config.get( 'wgDBname' );
		this.newcomerTaskToken = crypto.randomUUID();
		this.clickId = crypto.randomUUID();
		this.userVariant = ge.utils.getUserVariant();
		/**
		 * @type any
		 */
		this.logger = new LinkSuggestionInteractionLogger( {
			/* eslint-disable camelcase */
			is_mobile: this.isMobile(),
			active_interface: 'readmode_article_page',
			homepage_pageview_token: this.clickId,
			newcomer_task_token: this.newcomerTaskToken,
			/* eslint-enable camelcase */
		} );
	}

	/**
	 * @typedef {Object} LinkRecommendation
	 * @property {string} link_text
	 * @property {string} link_target
	 * @property {number} link_index
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

		const taskUrl = new URL( linkRecommendationResponse.taskURL, window.location.origin );
		taskUrl.searchParams.append( 'geclickid', this.clickId );
		taskUrl.searchParams.append( 'genewcomertasktoken', this.newcomerTaskToken );
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

		const pageContentSummaryRepository = new PageSummaryRepository( new mw.Api() );
		const linkPageData = await pageContentSummaryRepository.loadPageSummariesAndThumbnails(
			topRecs.map( ( rec ) => rec.link_target ),
		);

		if ( topRecs.length ) {
			mw.track( this.trackingCounterPrefix + '.highlight.page.impression', 1 );
			this.incrementPerformanceCounter( 'page_impression' );
			// eslint-disable-next-line camelcase
			this.logger.log( 'impression', { user_variant: this.userVariant } );
		}

		let aHighlightHasBeenSeenByUser = false;

		topRecs.forEach(
			/**
			 * @param {{link_text: string; link_target: string, link_index: number }} rec
			 */
			( rec ) => {
				const textToLink = rec.link_text;
				const highlightNode = this.createHighlightNode( textToLink, taskUrl, linkPageData[ rec.link_target ], rec.link_index );

				// eslint-disable-next-line compat/compat -- IntersectionObserver is widely available according to baseline
				const highlightObserver = new IntersectionObserver( ( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting && !aHighlightHasBeenSeenByUser ) {
							aHighlightHasBeenSeenByUser = true;
							mw.track( this.trackingCounterPrefix + '.highlight.viewport.impression', 1 );
							this.incrementPerformanceCounter( 'viewport_impression' );
							// eslint-disable-next-line camelcase
							this.logger.log( 'viewport_impression', { user_variant: this.userVariant } );
						}
					} );
				} );
				highlightObserver.observe( highlightNode );

				const paragraphWithWord = this.articleTextManipulator.findFirstContentElementContainingText(
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
					highlightNode,
				);
			},
		);
	}

	/**
	 * @private
	 * @param {string} metricName
	 */
	incrementPerformanceCounter( metricName ) {
		mw.track(
			'stats.mediawiki_GrowthExperiments_surfacing_addlink_' + metricName + '_total',
			1,
			// @ts-expect-error signature update pending in https://github.com/wikimedia/typescript-types/pull/54
			{
				wiki: mw.config.get( 'wgDBname' ),
				platform: this.isMobile() ? 'mobile' : 'desktop',
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
	 * @param {URL} taskUrl
	 * @param { { title: string; description: string?; thumbnail: any } | null } extraData
	 * @param {number} recommendationId
	 * @return {Element}
	 */
	createHighlightNode( textToLink, taskUrl, extraData, recommendationId ) {
		const highlightButtonElement = this.createButtonNode( textToLink );
		const popup = new SurfacedTaskPopup( textToLink, extraData );

		const popupElement = popup.getElementToInsert();

		/**
		 * @param {Event} event
		 */
		const handleClickOutsidePopup = ( event ) => {
			if ( event.target instanceof HTMLElement &&
				( popupElement === event.target || popupElement.contains( event.target ) )
			) {
				// click inside popup => do nothing
				return;
			}

			mw.track( this.trackingCounterPrefix + '.popup.clickOutside', 1 );
			this.incrementPerformanceCounter( 'popup_click_outside' );
			highlightButtonElement.classList.remove( 'growth-surfaced-task-popup-visible' );
			popup.toggle( false );
			document.removeEventListener( 'click', handleClickOutsidePopup, true );
		};

		highlightButtonElement.addEventListener( 'click',
			() => {
				mw.track( this.trackingCounterPrefix + '.highlight.click', 1 );
				this.incrementPerformanceCounter( 'highlight_click' );
				highlightButtonElement.classList.add( 'growth-surfaced-task-popup-visible' );
				document.addEventListener( 'click', handleClickOutsidePopup, true );
				popup.toggle( true );
			},
		);
		popup.addYesButtonClickHandler(
			() => {
				mw.track( this.trackingCounterPrefix + '.popup.Yes.click', 1 );
				this.incrementPerformanceCounter( 'popup_yes_click' );
				// eslint-disable-next-line camelcase
				this.logger.log( 'suggestion_accept_yes', { user_variant: this.userVariant }, {
					/* eslint-disable camelcase */
					active_interface: 'readmode_suggestion_dialog',
					/* eslint-enable camelcase */
				} );
				// Use the `link_index` as returned by ApiQueryLinkRecommendations
				// as a recommendation id (gerecommendationid), expecting it to match the order
				// in the taskData array the suggested edit session
				taskUrl.searchParams.append( 'gerecommendationid', recommendationId.toString() );
				taskUrl.searchParams.append( 'surfaced', '1' );
				window.location.href = taskUrl.href;
				document.removeEventListener( 'click', handleClickOutsidePopup, true );
			},
		);
		popup.addNoButtonClickHandler(
			() => {
				mw.track( this.trackingCounterPrefix + '.popup.No.click', 1 );
				this.incrementPerformanceCounter( 'popup_no_click' );
				highlightButtonElement.classList.remove( 'growth-surfaced-task-popup-visible' );
				popup.toggle( false );
				document.removeEventListener( 'click', handleClickOutsidePopup, true );
			},
		);
		popup.addXButtonClickHandler(
			() => {
				mw.track( this.trackingCounterPrefix + '.popup.X.click', 1 );
				this.incrementPerformanceCounter( 'popup_x_click' );
				highlightButtonElement.classList.remove( 'growth-surfaced-task-popup-visible' );
				document.removeEventListener( 'click', handleClickOutsidePopup, true );
			},
		);

		// eslint-disable-next-line compat/compat -- IntersectionObserver is widely available according to baseline
		const observer = new IntersectionObserver( ( entries ) => {
			entries.forEach( ( entry ) => {
				if ( !entry.isIntersecting ) {
					highlightButtonElement.classList.remove( 'growth-surfaced-task-popup-visible' );
					popup.toggle( false );
					document.removeEventListener( 'click', handleClickOutsidePopup, true );
				}
			} );
		} );

		// Start observing the target element
		observer.observe( popupElement );

		const highlightWrapper = document.createElement( 'span' );
		highlightWrapper.insertBefore( highlightButtonElement, null );
		highlightWrapper.insertBefore( popupElement, null );
		return highlightWrapper;
	}

	/**
	 * @private
	 * @param {string} textToLink
	 * @return {HTMLButtonElement}
	 */
	createButtonNode( textToLink ) {
		const textNode = document.createTextNode( textToLink );
		const iconNode = document.createElement( 'span' );
		iconNode.classList.add( 'cdx-button__icon', 'growth-surfaced-task-button__icon' );
		iconNode.ariaHidden = 'true';
		const buttonNode = document.createElement( 'button' );
		// REVIEW: not sure if it makes sense to base this on a cdx-button, given how different it is styled.
		buttonNode.classList.add( 'cdx-button', 'cdx-button--weight-quiet', 'growth-surfaced-task-button' );
		buttonNode.insertBefore( textNode, null );
		buttonNode.insertBefore( iconNode, null );

		return buttonNode;
	}

	/**
	 * @private
	 * @return {boolean}
	 */
	isMobile() {
		// @ts-expect-error type to be added in wikimedia/typescript-types#55
		return OO.ui.isMobile();
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
