<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Title\Title;
use MWHttpRequest;
use StatusValue;
use Wikimedia\UUID\GlobalIdGenerator;

/**
 * Handler for production image suggestion API.
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * Documentation: https://www.mediawiki.org/wiki/Platform_Engineering_Team/Data_Value_Stream/Data_Gateway#Image_Suggestions
 * Configuration of constructor parameters:
 * - $url: GEImageRecommendationServiceUrl
 * - $wiki: GEImageRecommendationServiceWikiIdMasquerade (or the actual wiki ID if not set)
 * - $useTitles: GEImageRecommendationServiceUseTitles
 * - $shouldVerifySsl: opposite of GEDeveloperSetup
 */
class ProductionImageRecommendationApiHandler implements ImageRecommendationApiHandler {

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $url;

	/** @var string */
	private $wikiId;

	/** @var GlobalIdGenerator */
	private $globalIdGenerator;

	/** @var int|null */
	private $requestTimeout;

	/** @var bool */
	private $useTitles;

	/** @var bool */
	private $shouldVerifySsl;

	private const KIND_TO_SOURCE = [
		'istype-lead-image' => ImageRecommendationImage::SOURCE_WIKIPEDIA,
		'istype-wikidata-image' => ImageRecommendationImage::SOURCE_WIKIDATA,
		'istype-commons-category' => ImageRecommendationImage::SOURCE_COMMONS,
		'istype-section-topics' => ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_TOPICS,
		'istype-section-topics-p18' => ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_TOPICS,
		'istype-section-alignment' => ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_ALIGNMENT,
		// WIKIDATA_SECTION_INTERSECTION is handled by one-off code as it's based on two kinds
		'istype-depicts' => 'unknown',
	];

	// FIXME not used for now as kinds change too often.
	private const KIND_TO_TASK_TYPE_ID = [
		'istype-lead-image' => ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		'istype-wikidata-image' => ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		'istype-commons-category' => ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		'istype-section-topics' => SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		'istype-section-topics-p18' => SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		'istype-section-alignment' => SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		'istype-depicts' => 'ignored',
	];

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $url Image recommendation service root URL
	 * @param string $wikiId Project ID (for example, 'enwiki')
	 * @param GlobalIdGenerator $globalIdGenerator GlobalIdGenerator, used to convert UUID to timestamp
	 * 	when sorting the suggestions
	 * @param int|null $requestTimeout Service request timeout in seconds
	 * @param bool $useTitles Query image suggestions by title instead of by article ID;
	 * 	used in non-production environments
	 * @param bool $shouldVerifySsl Whether the HTTP requests should verify SSL certificate and host
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		string $url,
		string $wikiId,
		GlobalIdGenerator $globalIdGenerator,
		?int $requestTimeout,
		bool $useTitles = false,
		bool $shouldVerifySsl = true
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->url = $url;
		$this->wikiId = $wikiId;
		$this->globalIdGenerator = $globalIdGenerator;
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

	/** @inheritDoc */
	public function getSuggestionDataFromApiResponse( array $apiResponse, TaskType $taskType ): array {
		if ( !$apiResponse['rows'] ) {
			return [];
		}
		$imageData = [];
		$sortedSuggestions = $this->sortSuggestions( $apiResponse['rows'] );
		// Since the suggestions are sorted based on the dataset ID, the id of the first suggestion
		// is that of the most recent dataset.
		$validDatasetId = $sortedSuggestions[0]['id'] ?? '';

		foreach ( $sortedSuggestions as $suggestion ) {
			// Discard suggestions from other datasets
			if ( $suggestion['id'] !== $validDatasetId ) {
				break;
			}

			// Ideally we'd have a list of kinds relevant for each task type but kinds are
			// still in flux. Just treat everything with a non-null section_heading as a
			// section-level recommendation.
			$recommendationTaskTypeId = isset( $suggestion['section_heading'] ) ?
				SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID :
				ImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
			if ( $recommendationTaskTypeId !== $taskType->getId() ) {
				continue;
			}

			$knownKinds = array_values( array_intersect( $suggestion['kind'], array_keys( self::KIND_TO_SOURCE ) ) );
			foreach ( array_diff( $suggestion['kind'], $knownKinds ) as $unknownKind ) {
				Util::logException( new WikiConfigException(
					"Unknown image suggestions API kind: $unknownKind"
				), [
					'page_id' => $suggestion['page_id'] ?? 0,
					'dataset-id' => $suggestion['id'] ?? 'unknown',
				] );
			}
			if ( $knownKinds ) {
				$knownSources = array_map( static fn ( $kind ) => self::KIND_TO_SOURCE[$kind], $knownKinds );
				$intersectionSources = [
					ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_TOPICS,
					ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_ALIGNMENT,
				];
				if ( array_diff( $intersectionSources, $knownSources ) === [] ) {
					$source = ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_INTERSECTION;
				} else {
					$source = self::KIND_TO_SOURCE[ $knownKinds[0] ];
				}
			} else {
				// FIXME we should probably ignore unknown types of suggestions once the API is more stable
				$source = [
					ImageRecommendationTaskTypeHandler::TASK_TYPE_ID
						=> ImageRecommendationImage::SOURCE_WIKIDATA,
					SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID
						=> ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_TOPICS,
				][ $taskType->getId()];
			}

			$imageData[] = new ImageRecommendationData(
				$suggestion['image'],
				$source,
				implode( ',', $suggestion['found_on'] ?? [] ),
				$suggestion['id'],
				$suggestion['section_index'],
				$suggestion['section_heading'],
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

	/**
	 * Sort the suggestions in decreasing order based on confidence and timestamp
	 *
	 * @param array $suggestions
	 * @return array
	 */
	private function sortSuggestions( array $suggestions ): array {
		// Sort by newer dataset with the highest confidence
		$compare = function ( array $a, array $b ) {
			$confidenceA = $a['confidence'] ?? 0;
			$confidenceB = $b['confidence'] ?? 0;
			$timestampA = $this->globalIdGenerator->getTimestampFromUUIDv1( $a['id'] ?? '' );
			$timestampB = $this->globalIdGenerator->getTimestampFromUUIDv1( $b['id'] ?? '' );

			return $timestampB <=> $timestampA ?: $confidenceB <=> $confidenceA;
		};
		usort( $suggestions, $compare );
		return $suggestions;
	}
}
