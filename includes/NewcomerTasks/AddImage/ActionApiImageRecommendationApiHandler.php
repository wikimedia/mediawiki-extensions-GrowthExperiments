<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use ApiRawMessage;
use Exception;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Language\RawMessage;
use MediaWiki\Logger\LoggerFactory;
use Status;
use StatusValue;
use Title;

/**
 * Handler for the action=query&prop=growthimagesuggestiondata API.
 * Uses a remote installation of this extension to proxy from ProductionApiImageRecommendationApiHandler.
 * Documentation: https://en.wikipedia.org/wiki/Special:ApiHelp/query+growthimagesuggestiondata
 * Configuration of constructor parameters:
 * - $apiUrl: GEImageRecommendationServiceUrl (should point to api.php)
 * - $accessToken: GEImageRecommendationServiceAccessToken
 */
class ActionApiImageRecommendationApiHandler implements ImageRecommendationApiHandler {

	private const SOURCE_TO_TASK_TYPE_ID = [
		ImageRecommendationImage::SOURCE_WIKIPEDIA => ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		ImageRecommendationImage::SOURCE_WIKIDATA => ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		ImageRecommendationImage::SOURCE_COMMONS => ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		ImageRecommendationImage::SOURCE_WIKIDATA_SECTION => SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
	];

	private HttpRequestFactory $httpRequestFactory;
	/** @var string api.php URL */
	private string $apiUrl;
	/** @var string MediaWiki OAuth 2 access token for upstream wiki */
	private string $accessToken;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $apiUrl api.php URL
	 * @param string $accessToken
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		string $apiUrl,
		string $accessToken
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->apiUrl = $apiUrl;
		$this->accessToken = $accessToken;
	}

	/** @inheritDoc */
	public function getApiRequest( Title $title, TaskType $taskType ) {
		// Ideally we'd use Util::getApiUrl() + maybe handle continuation, but the
		// RecommendationProvider/ApiHandler split prevents that.
		$url = wfAppendQuery( $this->apiUrl, [
			'action' => 'query',
			'prop' => 'growthimagesuggestiondata',
			'titles' => $title->getPrefixedText(),
			'gisdtasktype' => $taskType->getId(),
			'format' => 'json',
			'formatversion' => '2',
			'errorformat' => 'wikitext',
			'errorlang' => 'en',
		] );
		$request = $this->httpRequestFactory->create( $url, [], __METHOD__ );
		$request->setHeader( 'Authorization', 'Bearer ' . $this->accessToken );
		return $request;
	}

	/** @inheritDoc */
	public function getSuggestionDataFromApiResponse( array $apiResponse, TaskType $taskType ) {
		// based on Util::getApiUrl()
		if ( isset( $apiResponse['errors'] ) ) {
			$errorStatus = StatusValue::newGood();
			foreach ( $apiResponse['errors'] as $error ) {
				$errorStatus->fatal( new ApiRawMessage( $error['text'], $error['code'] ) );
			}
			return $errorStatus;
		}
		if ( isset( $apiResponse['warnings'] ) ) {
			$warningStatus = StatusValue::newGood();
			foreach ( $apiResponse['warnings'] as $warning ) {
				$warningStatus->warning( new RawMessage( $warning['module'] . ': ' . $warning['text'] ) );
			}
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				Status::wrap( $warningStatus )->getWikiText( false, false, 'en' ),
				[ 'exception' => new Exception( __METHOD__ ) ]
			);
		}

		if ( isset( $apiResponse['query']['pages'][0]['growthimagesuggestiondataerrors'] ) ) {
			$errorStatus = StatusValue::newGood();
			foreach ( $apiResponse['query']['pages'][0]['growthimagesuggestiondataerrors'] as $error ) {
				$errorStatus->fatal( new ApiRawMessage( $error['text'], $error['code'] ) );
			}
			return $errorStatus;
		}
		if ( !isset( $apiResponse['query']['pages'][0]['growthimagesuggestiondata'] ) ) {
			// page not found or has no suggestions
			return [];
		}
		$imageRecommendationArray = $apiResponse['query']['pages'][0]['growthimagesuggestiondata'][0];
		// $imageRecommendationArray is a serialized ImageRecommendation; the nice thing to do here
		// would be to just unserialize it, but ServiceImageRecommendationProvider / ApiHandler are
		// not structured right for that.

		$imageData = [];
		foreach ( $imageRecommendationArray['images'] as $imageDataArray ) {
			$imageDataArrayTaskTypeId = self::SOURCE_TO_TASK_TYPE_ID[$imageDataArray['source']] ?? null;
			if ( $imageDataArrayTaskTypeId !== $taskType->getId() ) {
				continue;
			}

			$imageData[] = new ImageRecommendationData(
				$imageDataArray['image'],
				$imageDataArray['source'],
				implode( ',', $imageDataArray['projects'] ?? [] ),
				$imageRecommendationArray['datasetId'],
				// the fallbacks are only needed until d78543cb reaches production
				$imageDataArray['sectionNumber'] ?? null,
				$imageDataArray['sectionTitle'] ?? null
			);
		}
		return $imageData;
	}

}
