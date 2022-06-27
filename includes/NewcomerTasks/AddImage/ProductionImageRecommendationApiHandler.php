<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use RequestContext;
use StatusValue;
use Title;

/**
 * Handler for production image suggestion API
 * Documentation: https://www.mediawiki.org/wiki/Platform_Engineering_Team/Data_Value_Stream/
 * Data_Gateway#Image_Suggestions
 */
class ProductionImageRecommendationApiHandler implements ImageRecommendationApiHandler {

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $url;

	/** @var string */
	private $wikiId;

	/** @var int|null */
	private $requestTimeout;

	/** @var bool */
	private $useTitles;

	/** @var bool */
	private $shouldVerifySsl;

	private const KIND_TO_SOURCE = [
		'istype-lead-image' => ImageRecommendationImage::SOURCE_WIKIPEDIA,
		'istype-wikidata-image' => ImageRecommendationImage::SOURCE_WIKIDATA,
		'istype-commons-category' => ImageRecommendationImage::SOURCE_COMMONS
	];

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $url Image recommendation service root URL
	 * @param string $wikiId Project ID (for example, 'enwiki')
	 * @param int|null $requestTimeout Service request timeout in seconds
	 * @param bool $useTitles Query image suggestions by title instead of by article ID;
	 * 	used in non-production environments
	 * @param bool $shouldVerifySsl Whether the HTTP requests should verify SSL certificate and host
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		string $url,
		string $wikiId,
		?int $requestTimeout,
		bool $useTitles = false,
		bool $shouldVerifySsl = true
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->url = $url;
		$this->wikiId = $wikiId;
		$this->requestTimeout = $requestTimeout;
		$this->useTitles = $useTitles;
		$this->shouldVerifySsl = $shouldVerifySsl;
	}

	/** @inheritDoc */
	public function getApiRequest( Title $title, TaskType $taskType ) {
		if ( !$this->url ) {
			return StatusValue::newFatal( 'rawmessage',
				'Image Suggestions API URL is not configured' );
		}

		$articleId = $this->useTitles ?
			$this->getArticleIdFromTitle( $title ) :
			$title->getArticleID();

		if ( $articleId instanceof StatusValue ) {
			return $articleId;
		}

		return $this->getRequest( [
			'public',
			'image_suggestions',
			'suggestions',
			$this->wikiId,
			$articleId
		] );
	}

	/**
	 * Get the value of the source field based on "kind" field in the API response
	 *
	 * @param array $kind
	 * @return string
	 */
	private function getSourceFromKind( array $kind ): string {
		foreach ( $kind as $key ) {
			if ( array_key_exists( $key, self::KIND_TO_SOURCE ) ) {
				return self::KIND_TO_SOURCE[ $key ];
			}
		}
		return '';
	}

	/** @inheritDoc */
	public function getSuggestionDataFromApiResponse( array $apiResponse ): array {
		if ( !$apiResponse['rows'] ) {
			return [];
		}
		$imageData = [];
		foreach ( $apiResponse['rows'] as $suggestion ) {
			$imageData[] = new ImageRecommendationData(
				$suggestion['image'],
				$this->getSourceFromKind( $suggestion['kind'] ),
				implode( ',', $suggestion['found_on'] ?? [] ),
				$suggestion['id']
			);
		}
		return $imageData;
	}

	/**
	 * Get the production article ID for the given title.
	 * The API retrieves image suggestions for a given production article ID, so for non-production
	 * environments, the title needs to be mapped to the corresponding production ID.
	 *
	 * @param Title $title
	 * @return StatusValue|int
	 */
	private function getArticleIdFromTitle( Title $title ) {
		$titleText = $title->getDBkey();
		$request = $this->getRequest( [
			'private',
			'image_suggestions',
			'title_cache',
			$this->wikiId,
			$titleText
		] );
		$status = $request->execute();
		if ( !$status->isOK() ) {
			return StatusValue::newFatal( 'rawmessage',
				'Failed to fetch production article ID for ' . $titleText );
		}
		$responseData = json_decode( $request->getContent(), true );
		$articleData = $responseData['rows'][0] ?? [];
		if ( array_key_exists( 'page_id', $articleData ) ) {
			return $articleData['page_id'];
		}
		return StatusValue::newFatal( 'rawmessage',
			'Invalid response from title_cache for ' . $titleText );
	}

	private function getRequest( array $pathArgs = [] ): MWHttpRequest {
		$request = $this->httpRequestFactory->create(
			$this->url . '/' . implode( '/', array_map( 'rawurlencode', $pathArgs ) ),
			[
				'method' => 'GET',
				'originalRequest' => RequestContext::getMain()->getRequest(),
				'timeout' => $this->requestTimeout,
				'sslVerifyCert' => $this->shouldVerifySsl,
				'sslVerifyHost' => $this->shouldVerifySsl,
			],
			__METHOD__
		);
		$request->setHeader( 'Accept', 'application/json' );
		return $request;
	}
}
