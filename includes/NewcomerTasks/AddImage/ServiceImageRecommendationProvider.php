<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use File;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Language\RawMessage;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use StatusValue;
use Wikimedia\Assert\Assert;
use Wikimedia\Stats\StatsFactory;

/**
 * Provides image recommendations via the Image Suggestion API.
 * @see mvp API: https://image-suggestion-api.wmcloud.org/?doc
 * @see production API: https://wikitech.wikimedia.org/wiki/Image-suggestion
 * @see https://phabricator.wikimedia.org/project/profile/5253/
 */
class ServiceImageRecommendationProvider implements ImageRecommendationProvider {

	/** @var TitleFactory */
	private $titleFactory;

	private StatsFactory $statsFactory;

	/** @var ImageRecommendationApiHandler */
	private $apiHandler;

	/** @var ImageRecommendationMetadataProvider */
	private $metadataProvider;

	/** @var AddImageSubmissionHandler */
	private $imageSubmissionHandler;

	/** @var bool */
	private $geDeveloperSetup;

	/** @var int */
	private $maxSuggestionsToProcess;

	/**
	 * @param TitleFactory $titleFactory
	 * @param StatsFactory $statsFactory
	 * @param ImageRecommendationApiHandler $apiHandler
	 * @param ImageRecommendationMetadataProvider $metadataProvider Image metadata provider
	 * @param AddImageSubmissionHandler $imageSubmissionHandler
	 * @param bool $geDeveloperSetup
	 * @param int $maxSuggestionsToProcess Maximum number of valid suggestions to process and return with
	 * an ImageRecommendation object.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		StatsFactory $statsFactory,
		ImageRecommendationApiHandler $apiHandler,
		ImageRecommendationMetadataProvider $metadataProvider,
		AddImageSubmissionHandler $imageSubmissionHandler,
		bool $geDeveloperSetup = false,
		int $maxSuggestionsToProcess = 1
	) {
		$this->titleFactory = $titleFactory;
		$this->statsFactory = $statsFactory->withComponent( 'GrowthExperiments' );
		$this->apiHandler = $apiHandler;
		$this->metadataProvider = $metadataProvider;
		$this->imageSubmissionHandler = $imageSubmissionHandler;
		$this->geDeveloperSetup = $geDeveloperSetup;
		$this->maxSuggestionsToProcess = $maxSuggestionsToProcess;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';

		$title = $this->titleFactory->newFromLinkTarget( $title );
		$titleText = $title->getPrefixedDBkey();
		$titleTextSafe = strip_tags( $titleText );
		if ( !$title->exists() && !$this->geDeveloperSetup ) {
			// These errors might show up to the end user, but provide no useful information;
			// they are merely there to support debugging. So we keep them English-only to
			// reduce the translator burden.
			return StatusValue::newFatal( new RawMessage(
				'Recommendation could not be loaded for non-existing page: $1',
				[ $titleTextSafe ]
			) );
		}

		$request = $this->apiHandler->getApiRequest( $title, $taskType );

		if ( $request instanceof StatusValue ) {
			return $request;
		}

		$startTime = microtime( true );
		$status = $request->execute();
		$getRequestTimeInSeconds = microtime( true ) - $startTime;

		$timing = $this->statsFactory->getTiming( 'image_recommendation_provider_seconds' );
		$timing->setLabel( 'action', 'get' )
			->observeSeconds( $getRequestTimeInSeconds );

		// Stay backward compatible with the legacy Graphite-based dashboard
		// feeding on this data.
		// TODO: remove after switching to Prometheus-based dashboards
		MediaWikiServices::getInstance()->getStatsdDataFactory()->timing(
			'timing.growthExperiments.imageRecommendationProvider.get',
			$getRequestTimeInSeconds
		);

		if ( !$status->isOK() && $request->getStatus() < 400 ) {
			return $status;
		}
		$response = $request->getContent();
		$data = json_decode( $response, true );

		if ( $data === null ) {
			$errorMessage = __METHOD__ . ': Unable to decode JSON response for page {title}: {response}';
			$errorContext = [ 'title' => $titleTextSafe, 'response' => $response ];
			LoggerFactory::getInstance( 'GrowthExperiments' )->error( $errorMessage, $errorContext );
			return StatusValue::newFatal( new RawMessage(
				"Unable to decode JSON response for page $1: $2",
				[ $titleTextSafe, $response ]
			) );
		} elseif ( $request->getStatus() >= 400 ) {
			return StatusValue::newFatal( new RawMessage(
				'API returned HTTP code $1 for page $2: $3',
				[ $request->getStatus(), $titleTextSafe, strip_tags( $data['detail'] ?? '(no reason given)' ) ]
			) );
		}

		$imageRecommendationDatas = $this->apiHandler->getSuggestionDataFromApiResponse( $data, $taskType );
		if ( $imageRecommendationDatas instanceof StatusValue ) {
			return $imageRecommendationDatas;
		}

		$startTime = microtime( true );
		$responseData = self::processApiResponseData(
			$taskType,
			$title,
			$titleText,
			$imageRecommendationDatas,
			$this->metadataProvider,
			$this->imageSubmissionHandler,
			$this->maxSuggestionsToProcess
		);

		$processingTimeInSeconds = microtime( true ) - $startTime;
		$timing
			->setLabel( 'action', 'process_api_response_data' )
			->observeSeconds( $processingTimeInSeconds );

		// Stay backward compatible with the legacy Graphite-based dashboard
		// feeding on this data.
		// TODO: remove after switching to Prometheus-based dashboards
		$services = MediaWikiServices::getInstance();
		$services->getStatsdDataFactory()->timing(
			'timing.growthExperiments.imageRecommendationProvider.processApiResponseData',
			$getRequestTimeInSeconds
		);

		return $responseData;
	}

	/**
	 * Process the data returned by the Image Suggestions API and return an ImageRecommendation
	 * or an error.
	 * @param ImageRecommendationBaseTaskType $taskType
	 * @param LinkTarget|ProperPageIdentity $title Title for which to generate the image recommendation for.
	 *   The title in the API response will be ignored.
	 * @param string $titleText Title text, for logging.
	 * @param ImageRecommendationData[] $suggestionData
	 * @param ImageRecommendationMetadataProvider $metadataProvider
	 * @param AddImageSubmissionHandler|null $imageSubmissionHandler
	 * @param int $maxSuggestionsToProcess Maximum number of valid suggestions to process and return
	 *   with an ImageRecommendation object.
	 * @return ImageRecommendation|StatusValue
	 */
	public static function processApiResponseData(
		ImageRecommendationBaseTaskType $taskType,
		$title,
		string $titleText,
		array $suggestionData,
		ImageRecommendationMetadataProvider $metadataProvider,
		?AddImageSubmissionHandler $imageSubmissionHandler,
		int $maxSuggestionsToProcess = 1
	) {
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';

		$suggestionFilters = $taskType->getSuggestionFilters();
		$titleTextSafe = strip_tags( $titleText );
		if ( count( $suggestionData ) === 0 ) {
			return StatusValue::newGood()->error( new ApiRawMessage(
				// Keep in sync with Util::STATSD_INCREMENTABLE_ERROR_MESSAGES
				[ 'No recommendation found for page: $1', $titleTextSafe ],
				'growthexperiments-no-recommendation-found'
			) );
		}
		$images = [];
		$datasetId = '';
		$status = StatusValue::newGood();
		foreach ( $suggestionData as $suggestion ) {
			if ( count( $images ) >= $maxSuggestionsToProcess ) {
				break;
			}
			$validationError = ImageRecommendationDataValidator::validate( $titleTextSafe, $suggestion );
			if ( !$validationError->isGood() ) {
				$status->merge( $validationError );
				continue;
			}

			$filename = File::normalizeTitle( $suggestion->getFilename() )->getDBkey();
			$source = $suggestion->getSource();
			$projects = $suggestion->getFormattedProjects();
			$datasetId = $suggestion->getDatasetId();
			$sectionNumber = $suggestion->getSectionNumber();
			$sectionTitle = $suggestion->getSectionTitle();
			$fileMetadata = $metadataProvider->getFileMetadata( $filename );

			if ( is_array( $fileMetadata ) ) {
				$imageWidth = $fileMetadata['originalWidth'] ?: 0;
				$minWidth = $suggestionFilters['minimumSize']['width'] ?? 0;
				$validMediaTypes = $suggestionFilters['validMediaTypes'];
				if (
					self::hasMinimumWidth( $minWidth, $imageWidth, $filename, $titleTextSafe, $status ) &&
					self::isValidMediaType(
						$validMediaTypes, $fileMetadata['mediaType'], $filename, $titleTextSafe, $status
					)
				) {
					$imageMetadata = $metadataProvider->getMetadata( [
						'filename' => $suggestion->getFilename(),
						'projects' => $projects,
						'source' => $source,
					] );
					if ( is_array( $imageMetadata ) ) {
						$images[] = new ImageRecommendationImage(
							new TitleValue( NS_FILE, $filename ),
							$source,
							$projects,
							$imageMetadata,
							$sectionNumber,
							$sectionTitle
						);
					} else {
						$status->merge( $imageMetadata );
					}
				}
			} else {
				$status->merge( $fileMetadata );
			}
		}
		if ( $title instanceof ProperPageIdentity ) {
			$pageIdentity = $title;
			$linkTarget = Title::newFromPageIdentity( $title );
		} else {
			$pageIdentity = Title::newFromLinkTarget( $title )->toPageIdentity();
			$linkTarget = $title;
		}
		if ( !$images && $imageSubmissionHandler ) {
			$imageSubmissionHandler->invalidateRecommendation(
				$taskType,
				$pageIdentity,
				// We need to pass a user ID for event logging purposes. We can't easily
				// access a user ID here; passing 0 for an anonymous user seems OK.
				0,
				null,
				'',
				null,
				null
			);
			return $status;
		}
		// If $status is bad but $images is not empty (fetching some but not all images failed),
		// we can just ignore the errors, they won't be a problem for the recommendation workflow.
		return new ImageRecommendation( $linkTarget, $images, $datasetId );
	}

	/**
	 * @param int $maxSuggestionsToProcess
	 * @return void
	 */
	public function setMaxSuggestionsToProcess( int $maxSuggestionsToProcess ) {
		$this->maxSuggestionsToProcess = $maxSuggestionsToProcess;
	}

	/**
	 * @param int $minimumWidth
	 * @param int $imageWidth
	 * @param string $filename
	 * @param string $pageTitleText
	 * @param StatusValue $status
	 * @return bool
	 */
	private static function hasMinimumWidth(
		int $minimumWidth,
		int $imageWidth,
		string $filename,
		string $pageTitleText,
		StatusValue $status
	): bool {
		$res = $imageWidth >= $minimumWidth;
		if ( !$res ) {
			$status->error( new RawMessage(
				'Invalid file $1 in article $2. Filtered because not wide enough: $3 (minimum $4)',
				[ $filename, $pageTitleText, $imageWidth, $minimumWidth ]
			) );
		}
		return $res;
	}

	/**
	 * @param array $validMediaTypes
	 * @param string $mediaType
	 * @param string $filename
	 * @param string $pageTitleText
	 * @param StatusValue $status
	 * @return bool
	 */
	private static function isValidMediaType(
		array $validMediaTypes,
		string $mediaType,
		string $filename,
		string $pageTitleText,
		StatusValue $status
	): bool {
		$res = in_array( $mediaType, $validMediaTypes );
		if ( !$res ) {
			$validMediaTypesText = implode( ', ', $validMediaTypes );
			$status->error( new RawMessage(
				'Invalid file $1 in article $2. Filtered because $3 is not valid mime type ($4)',
				[ $filename, $pageTitleText, $mediaType, $validMediaTypesText ]
			) );
		}
		return $res;
	}
}
