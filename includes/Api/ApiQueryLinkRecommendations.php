<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Config\Config;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Stats\StatsFactory;

/**
 * API module for retrieving link recommendations for a specific page.
 */
class ApiQueryLinkRecommendations extends ApiQueryBase {

	private LinkRecommendationStore $linkRecommendationStore;
	private LinkRecommendationUpdater $linkRecommendationUpdater;
	private TitleFactory $titleFactory;
	private StatsFactory $statsFactory;
	private Config $wikiConfig;

	/**
	 * Constructor for ApiQueryLinkRecommendations
	 *
	 * @param ApiQuery $query The API query object
	 * @param string $moduleName The name of this module
	 * @param LinkRecommendationStore $linkRecommendationStore The store for link recommendations
	 * @param LinkRecommendationUpdater $linkRecommendationUpdater
	 * @param TitleFactory $titleFactory Factory for creating Title objects
	 * @param StatsFactory $statsFactory
	 * @param Config $wikiConfig Configuration object
	 */
	public function __construct(
		ApiQuery $query,
		string $moduleName,
		LinkRecommendationStore $linkRecommendationStore,
		LinkRecommendationUpdater $linkRecommendationUpdater,
		TitleFactory $titleFactory,
		StatsFactory $statsFactory,
		Config $wikiConfig
	) {
		parent::__construct( $query, $moduleName, 'lr' );
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->linkRecommendationUpdater = $linkRecommendationUpdater;
		$this->titleFactory = $titleFactory;
		$this->statsFactory = $statsFactory;
		$this->wikiConfig = $wikiConfig;
	}

	/**
	 * Main execution function for the API module.
	 * Retrieves and returns link recommendations for a given page ID.
	 */
	public function execute() {
		if ( !$this->wikiConfig->get( 'GESurfacingStructuredTasksEnabled' ) ) {
			return;
		}

		$params = $this->extractRequestParams();
		/**
		 * @var int $pageId
		 */
		$pageId = $params[ 'pageid' ];

		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic' );
		}

		$title = $this->titleFactory->newFromID( $pageId );
		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', $pageId ] );
		}

		$linkRecommendation = $this->linkRecommendationStore->getByPageId( $pageId );
		if ( !$linkRecommendation ) {
			$this->incrementCounter( 'no_preexisting_recommendation_found' );

			// This is not yet production ready, see T382251
			if ( $this->wikiConfig->get( 'GESurfacingStructuredTasksReadModeUpdateEnabled' ) ) {
				$linkRecommendation = $this->tryLoadingMoreLinkRecommendations( $title );
			}
		} else {
			$this->incrementCounter( 'preexisting_recommendations_found' );
		}

		$result = $this->getResult();
		$path = [ 'query', $this->getModuleName() ];
		$recommendations = [];

		if ( !$linkRecommendation ) {
			$result->addValue( $path, 'recommendations', $recommendations );
			return;
		}

		$links = $linkRecommendation->getLinks();
		foreach ( $links as $link ) {
			$recommendations[] = [
				'context_before' => $link->getContextBefore(),
				'context_after' => $link->getContextAfter(),
				'link_text' => $link->getText(),
				'link_target' => $link->getLinkTarget(),
				'link_index' => $link->getLinkIndex(),
				'score' => $link->getScore(),
				'wikitext_offset' => $link->getWikitextOffset(),
			];
		}

		$result->addValue( $path, 'recommendations', $recommendations );
		$result->addValue( $path, 'taskURL', $this->getTaskUrl( $pageId ) );
	}

	private function incrementCounter( string $labelForEventToBeCounted ): void {
		$wiki = WikiMap::getCurrentWikiId();
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'surfacing_link_recommendation_api_total' )
			->setLabel( 'wiki', $wiki )
			->setLabel( 'event', $labelForEventToBeCounted )
			->increment();
	}

	/**
	 * Generates a task URL for starting the Add Link session in VisualEditor.
	 *
	 * @param int $pageId The ID of the page for which to generate the task URL
	 * @return string The generated task URL
	 */
	private function getTaskUrl( $pageId ) {
		return SpecialPage::getTitleFor( 'Homepage', 'newcomertask/' . $pageId )->getLocalURL( [
			'gesuggestededit' => 1,
			'getasktype' => LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
		] );
	}

	private function tryLoadingMoreLinkRecommendations( Title $title ): ?LinkRecommendation {
		$processingCandidateStartTimeSeconds = microtime( true );
		$updateStatus = $this->linkRecommendationUpdater->processCandidate( $title->toPageIdentity(), false );
		if ( $updateStatus->isOK() ) {
			$this->incrementCounter( 'new_recommendations_added' );
			$linkRecommendation = $this->linkRecommendationStore->getByPageId(
				$title->getArticleID(),
				IDBAccessObject::READ_LATEST
			);
		} else {
			$linkRecommendation = null;
			$this->incrementCounter( 'no_recommendations_added' );
		}
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getTiming( 'surfacing_link_recommendation_api_processing_candidate_seconds' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->observeSeconds( microtime( true ) - $processingCandidateStartTimeSeconds );
		return $linkRecommendation;
	}

	/**
	 * Defines the allowed parameters for this API module.
	 *
	 * @return array An array of allowed parameters and their properties
	 */
	protected function getAllowedParams() {
		return [
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				ApiBase::PARAM_HELP_MSG => 'apihelp-query+linkrecommendations-param-pageid',
			],
		];
	}

	/**
	 * This API module is for internal use only.
	 *
	 * @return bool Always returns true
	 */
	public function isInternal() {
		return true;
	}

	/**
	 * Provides example queries for this API module.
	 *
	 * @return array An array of example queries and their descriptions
	 */
	public function getExamplesMessages() {
		return [
			'action=query&list=linkrecommendations&lrpageid=123'
				=> 'apihelp-query+linkrecommendations-example-1',
			'action=query&list=linkrecommendations&lrpageid=456&lrlimit=5'
				=> 'apihelp-query+linkrecommendations-example-2',
		];
	}
}
