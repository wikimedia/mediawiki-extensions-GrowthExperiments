<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use File;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use IBufferingStatsdDataFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Logger\LoggerFactory;
use StatusValue;
use Title;
use TitleFactory;
use TitleValue;
use Wikimedia\Assert\Assert;

/**
 * Provides image recommendations via the Image Suggestion API.
 * @see mvp API: https://image-suggestion-api.wmcloud.org/?doc
 * @see production API: https://wikitech.wikimedia.org/wiki/Image-suggestion
 * @see https://phabricator.wikimedia.org/project/profile/5253/
 */
class ServiceImageRecommendationProvider implements ImageRecommendationProvider {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

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
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param ImageRecommendationApiHandler $apiHandler
	 * @param ImageRecommendationMetadataProvider $metadataProvider Image metadata provider
	 * @param AddImageSubmissionHandler $imageSubmissionHandler
	 * @param bool $geDeveloperSetup
	 * @param int $maxSuggestionsToProcess Maximum number of valid suggestions to process and return with
	 * an ImageRecommendation object.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		IBufferingStatsdDataFactory $statsdDataFactory,
		ImageRecommendationApiHandler $apiHandler,
		ImageRecommendationMetadataProvider $metadataProvider,
		AddImageSubmissionHandler $imageSubmissionHandler,
		bool $geDeveloperSetup = false,
		int $maxSuggestionsToProcess = 1
	) {
		$this->titleFactory = $titleFactory;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->apiHandler = $apiHandler;
		$this->metadataProvider = $metadataProvider;
		$this->imageSubmissionHandler = $imageSubmissionHandler;
		$this->geDeveloperSetup = $geDeveloperSetup;
		$this->maxSuggestionsToProcess = $maxSuggestionsToProcess;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		'@phan-var ImageRecommendationTaskType $taskType';
		Assert::parameterType( ImageRecommendationTaskType::class, $taskType, '$taskType' );
		$title = $this->titleFactory->newFromLinkTarget( $title );
		$titleText = $title->getPrefixedDBkey();
		$titleTextSafe = strip_tags( $titleText );
		if ( !$title->exists() && !$this->geDeveloperSetup ) {
			// These errors might show up to the end user, but provide no useful information;
			// they are merely there to support debugging. So we keep them English-only to
			// to reduce the translator burden.
			return StatusValue::newFatal( 'rawmessage',
				'Recommendation could not be loaded for non-existing page: ' . $titleTextSafe );
		}

		$request = $this->apiHandler->getApiRequest( $title, $taskType );

		if ( $request instanceof StatusValue ) {
			return $request;
		}

		$startTime = microtime( true );
		$status = $request->execute();

		$this->statsdDataFactory->timing(
			'timing.growthExperiments.imageRecommendationProvider.get',
			microtime( true ) - $startTime
		);

		if ( !$status->isOK() && $request->getStatus() < 400 ) {
			return $status;
		}
		$response = $request->getContent();
		$data = json_decode( $response, true );

		if ( $data === null ) {
			$errorMessage = __METHOD__ . ': Unable to decode JSON response for page {title}: {response}';
			LoggerFactory::getInstance( 'GrowthExperiments' )->error( $errorMessage, [
				'title' => $titleTextSafe,
				'response' => $response
			] );
			return StatusValue::newFatal( 'rawmessage', $errorMessage );
		} elseif ( $request->getStatus() >= 400 ) {
			return StatusValue::newFatal( 'rawmessage',
				'API returned HTTP code ' . $request->getStatus() . ' for page '
				. $titleTextSafe . ': ' . ( strip_tags( $data['detail'] ?? '(no reason given)' ) ) );
		}

		$startTime = microtime( true );
		$responseData = self::processApiResponseData(
			$title,
			$titleText,
			$this->apiHandler->getSuggestionDataFromApiResponse( $data ),
			$this->metadataProvider,
			$this->imageSubmissionHandler,
			$taskType->getSuggestionFilters(),
			$this->maxSuggestionsToProcess
		);

		$this->statsdDataFactory->timing(
			'timing.growthExperiments.imageRecommendationProvider.processApiResponseData',
			microtime( true ) - $startTime
		);

		return $responseData;
	}

	/**
	 * Process the data returned by the Image Suggestions API and return an ImageRecommendation
	 * or an error.
	 * @param LinkTarget $title Title for which to generate the image recommendation for.
	 *   The title in the API response will be ignored.
	 * @param string $titleText Title text, for logging.
	 * @param ImageRecommendationData[] $suggestionData
	 * @param ImageRecommendationMetadataProvider $metadataProvider
	 * @param AddImageSubmissionHandler $imageSubmissionHandler
	 * @param array $suggestionFilters
	 * @param int $maxSuggestionsToProcess Maximum number of valid suggestions to process and return
	 * with an ImageRecommendation object.
	 * @return ImageRecommendation|StatusValue
	 * @throws \MWException
	 */
	public static function processApiResponseData(
		LinkTarget $title,
		string $titleText,
		array $suggestionData,
		ImageRecommendationMetadataProvider $metadataProvider,
		AddImageSubmissionHandler $imageSubmissionHandler,
		array $suggestionFilters = [],
		int $maxSuggestionsToProcess = 1
	) {
		$titleTextSafe = strip_tags( $titleText );
		if ( count( $suggestionData ) === 0 ) {
			return StatusValue::newFatal( 'rawmessage',
				'No recommendation found for page: ' . $titleTextSafe );
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
			$fileMetadata = $metadataProvider->getFileMetadata( $filename );

			if ( is_array( $fileMetadata ) ) {
				$imageWidth = $fileMetadata['originalWidth'] ?: 0;
				$minWidth = $suggestionFilters['minimumSize']['width'];
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
							$imageMetadata
						);
					} else {
						$status->merge( $imageMetadata );
					}
				}
			} else {
				$status->merge( $fileMetadata );
			}
		}
		if ( !$images ) {
			$imageSubmissionHandler->invalidateRecommendation(
				Title::newFromLinkTarget( $title )->toPageIdentity()
			);
			return $status;
		}
		// If $status is bad but $images is not empty (fetching some but not all images failed),
		// we can just ignore the errors, they won't be a problem for the recommendation workflow.
		return new ImageRecommendation( $title, $images, $datasetId );
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
			$status->error(
				'rawmessage',
				"Invalid file $filename in article $pageTitleText. " .
				"Filtered because not wide enough: $imageWidth (minimum $minimumWidth)"
			);
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
			$status->error(
				'rawmessage',
				"Invalid file $filename in article $pageTitleText. " .
				"Filtered because $mediaType is not valid mime type ( $validMediaTypesText )"
			);
		}
		return $res;
	}
}
