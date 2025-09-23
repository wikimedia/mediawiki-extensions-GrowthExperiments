<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Title\Title;
use StatusValue;

/**
 * Handler for MVP image suggestion API.
 * Documentation: https://image-suggestion-api.wmcloud.org/?doc#/Image%20Suggestions
 * This API should not be further used in production.
 * Configuration of constructor parameters:
 * - $url: GEImageRecommendationServiceUrl
 * - $proxyUrl: GEImageRecommendationServiceHttpProxy
 * - $useTitles: GEImageRecommendationServiceUseTitles
 */
class MvpImageRecommendationApiHandler implements ImageRecommendationApiHandler {

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $url;

	/** @var string|null */
	private $proxyUrl;

	/** @var string */
	private $wikiProject;

	/** @var string */
	private $wikiLanguage;

	/** @var int|null */
	private $requestTimeout;

	/** @var bool */
	private $useTitles;

	private const CONFIDENCE_RATING_TO_NUMBER = [
		'high' => 3,
		'medium' => 2,
		'low' => 1,
	];

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $url Image recommendation service root URL
	 * @param string $wikiProject Wiki project (e.g. 'wikipedia')
	 * @param string $wikiLanguage Wiki language code
	 * @param string|null $proxyUrl HTTP proxy to use for $url
	 * @param int|null $requestTimeout Service request timeout in seconds
	 * @param bool $useTitles Use titles (the /:wiki/:lang/pages/:title API endpoint)
	 *   instead of IDs (the /:wiki/:lang/pages endpoint)?
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		string $url,
		string $wikiProject,
		string $wikiLanguage,
		?string $proxyUrl,
		?int $requestTimeout,
		bool $useTitles = false
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->url = $url;
		$this->proxyUrl = $proxyUrl;
		$this->wikiProject = $wikiProject;
		$this->wikiLanguage = $wikiLanguage;
		$this->requestTimeout = $requestTimeout;
		$this->useTitles = $useTitles;
	}

	/** @inheritDoc */
	public function getApiRequest( Title $title, TaskType $taskType ) {
		if ( !$this->url ) {
			return StatusValue::newFatal( 'rawmessage',
				'Image Suggestions API URL is not configured' );
		}

		$pathArgs = [
			'image-suggestions',
			'v0',
			$this->wikiProject,
			$this->wikiLanguage,
			'pages',
		];
		$queryArgs = [
			'source' => 'ima',
		];
		if ( $this->useTitles ) {
			$pathArgs[] = $title->getPrefixedDBkey();
		} else {
			$queryArgs['id'] = $title->getArticleID();
		}
		$request = $this->httpRequestFactory->create(
			wfAppendQuery(
				$this->url . '/' . implode( '/', array_map( 'rawurlencode', $pathArgs ) ),
				$queryArgs
			),
			[
				'method' => 'GET',
				'proxy' => $this->proxyUrl,
				'originalRequest' => RequestContext::getMain()->getRequest(),
				'timeout' => $this->requestTimeout,
			],
			__METHOD__
		);
		$request->setHeader( 'Accept', 'application/json' );
		return $request;
	}

	/** @inheritDoc */
	public function getSuggestionDataFromApiResponse( array $apiResponse, TaskType $taskType ): array {
		if ( $taskType->getId() !== ImageRecommendationTaskTypeHandler::TASK_TYPE_ID ) {
			// The MVP API does not provide section-level recommendations.
			return [];
		}

		if ( !$apiResponse['pages'] || !$apiResponse['pages'][0]['suggestions'] ) {
			return [];
		}
		$imageData = [];
		$sortedSuggestions = $this->sortSuggestions( $apiResponse['pages'][0]['suggestions'] );
		foreach ( $sortedSuggestions as $suggestion ) {
			$filename = $suggestion['filename'] ?? null;
			$source = $suggestion['source']['details']['from'] ?? null;
			$projects = $suggestion['source']['details']['found_on'] ?? null;
			$datasetId = $suggestion['source']['details']['dataset_id'] ?? null;
			$imageData[] = new ImageRecommendationData(
				$filename,
				$source,
				$projects,
				$datasetId
			);
		}
		return $imageData;
	}

	/**
	 * Get numeric value of the suggestion's confidence rating
	 *
	 * @param array $suggestion
	 * @return int
	 */
	private function getConfidence( array $suggestion ): int {
		if ( array_key_exists( 'confidence_rating', $suggestion ) ) {
			return self::CONFIDENCE_RATING_TO_NUMBER[$suggestion['confidence_rating']] ?? 0;
		}
		return 0;
	}

	/**
	 * Sort the suggestions in decreasing order based on confidence rating
	 *
	 * @param array $suggestions
	 * @return array
	 */
	private function sortSuggestions( array $suggestions ): array {
		$compare = function ( array $a, array $b ) {
			return $this->getConfidence( $a ) < $this->getConfidence( $b ) ? 1 : -1;
		};
		usort( $suggestions, $compare );
		return $suggestions;
	}
}
