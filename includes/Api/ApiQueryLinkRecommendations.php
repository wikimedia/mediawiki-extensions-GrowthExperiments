<?php

namespace GrowthExperiments\Api;

use ApiQuery;
use ApiQueryBase;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use MediaWiki\Api\ApiBase;
use MediaWiki\Config\Config;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module for retrieving link recommendations for a specific page.
 */
class ApiQueryLinkRecommendations extends ApiQueryBase {

	private LinkRecommendationStore $linkRecommendationStore;
	private TitleFactory $titleFactory;
	private Config $wikiConfig;

	/**
	 * Constructor for ApiQueryLinkRecommendations
	 *
	 * @param ApiQuery $query The API query object
	 * @param string $moduleName The name of this module
	 * @param LinkRecommendationStore $linkRecommendationStore The store for link recommendations
	 * @param TitleFactory $titleFactory Factory for creating Title objects
	 * @param Config $wikiConfig Configuration object
	 */
	public function __construct(
		ApiQuery $query,
		string $moduleName,
		LinkRecommendationStore $linkRecommendationStore,
		TitleFactory $titleFactory,
		Config $wikiConfig
	) {
		parent::__construct( $query, $moduleName, 'lr' );
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->titleFactory = $titleFactory;
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

	/**
	 * Generates a task URL for starting the Add Link session in VisualEditor.
	 *
	 * @param int $pageId The ID of the page for which to generate the task URL
	 * @return string The generated task URL
	 */
	private function getTaskUrl( $pageId ) {
		return SpecialPage::getTitleFor( 'Homepage', 'newcomertask/' . $pageId )->getFullURL() .
			   '?gesuggestededit=1&getasktype=link-recommendation';
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
