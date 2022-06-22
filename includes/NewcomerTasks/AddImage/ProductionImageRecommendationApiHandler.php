<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
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
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		string $url,
		string $wikiId,
		?int $requestTimeout
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->url = $url;
		$this->wikiId = $wikiId;
		$this->requestTimeout = $requestTimeout;
	}

	/** @inheritDoc */
	public function getApiRequest( Title $title, TaskType $taskType ) {
		if ( !$this->url ) {
			return StatusValue::newFatal( 'rawmessage',
				'Image Suggestions API URL is not configured' );
		}

		$pathArgs = [
			'public',
			'image_suggestions',
			'suggestions',
			$this->wikiId,
			$title->getArticleID()
		];
		$request = $this->httpRequestFactory->create(
			$this->url . '/' . implode( '/', array_map( 'rawurlencode', $pathArgs ) ),
			[
				'method' => 'GET',
				'originalRequest' => RequestContext::getMain()->getRequest(),
				'timeout' => $this->requestTimeout,
			],
			__METHOD__
		);
		$request->setHeader( 'Accept', 'application/json' );
		return $request;
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
				implode( ',', $suggestion['found_on'] ),
				$suggestion['id']
			);
		}
		return $imageData;
	}
}
