<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use RequestContext;
use StatusValue;
use TitleFactory;
use TitleValue;
use Wikimedia\Assert\Assert;

/**
 * Provides image recommendations via the Image Suggestion API.
 * @see https://image-suggestion-api.wmcloud.org/?doc
 * @see https://phabricator.wikimedia.org/project/profile/5253/
 */
class ServiceImageRecommendationProvider implements ImageRecommendationProvider {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $url;

	/** @var string */
	private $wikiProject;

	/** @var string */
	private $wikiLanguage;

	/** @var int|null */
	private $requestTimeout;

	/** @var ImageRecommendationMetadataProvider */
	private $metadataProvider;

	/**
	 * @param TitleFactory $titleFactory
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $url Image recommendation service root URL
	 * @param string $wikiProject Wiki project (e.g. 'wikipedia')
	 * @param string $wikiLanguage Wiki language code
	 * @param ImageRecommendationMetadataProvider $metadataProvider Image metadata provider
	 * @param int|null $requestTimeout Service request timeout in seconds.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		HttpRequestFactory $httpRequestFactory,
		string $url,
		string $wikiProject,
		string $wikiLanguage,
		ImageRecommendationMetadataProvider $metadataProvider,
		?int $requestTimeout
	) {
		$this->titleFactory = $titleFactory;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->url = $url;
		$this->wikiProject = $wikiProject;
		$this->wikiLanguage = $wikiLanguage;
		$this->requestTimeout = $requestTimeout;
		$this->metadataProvider = $metadataProvider;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( ImageRecommendationTaskType::class, $taskType, '$taskType' );
		$title = $this->titleFactory->newFromLinkTarget( $title );
		$pageId = $title->getArticleID();
		$titleText = $title->getPrefixedDBkey();
		if ( !$pageId ) {
			// These errors might show up to the end user, but provide no useful information;
			// they are merely there to support debugging. So we keep them English-only to
			// to reduce the translator burden.
			return StatusValue::newFatal( 'rawmessage',
				'No recommendation found for page: ' . $titleText );
		}
		if ( !$this->url ) {
			return StatusValue::newFatal( 'rawmessage',
				'Image Suggestions API is not configured' );
		}

		$pathArgs = [ 'image-suggestions', 'v0', $this->wikiProject, $this->wikiLanguage, 'pages' ];
		$queryArgs = [ 'id' => $pageId, 'source' => 'ima' ];
		$request = $this->httpRequestFactory->create(
			wfAppendQuery( $this->url . '/' . implode( '/', $pathArgs ), $queryArgs ),
			[
				'method' => 'GET',
				'originalRequest' => RequestContext::getMain()->getRequest(),
				'timeout' => $this->requestTimeout,
			],
			__METHOD__
		);
		$request->setHeader( 'Accept', 'application/json' );

		$status = $request->execute();
		if ( !$status->isOK() && $request->getStatus() < 400 ) {
			return $status;
		}
		$response = $request->getContent();
		$data = json_decode( $response, true );
		if ( $data === null ) {
			return StatusValue::newFatal( 'rawmessage',
				'Invalid JSON response for page: ' . $titleText );
		} elseif ( $request->getStatus() >= 400 ) {
			return StatusValue::newFatal( 'rawmessage',
				'API returned HTTP code ' . $request->getStatus() . ' for page '
				. $titleText . ': ' . ( $data['detail'] ?? '<no reason given>' ) );
		}

		return self::processApiResponseData( $title, $titleText, $data, $this->metadataProvider );
	}

	/**
	 * Process the data returned by the Image Suggestions API and return an ImageRecommendation
	 * or an error.
	 * @param LinkTarget $title Title for which to generate the image recommendation for.
	 *   The title in the API response will be ignored.
	 * @param string $titleText Title text, for logging.
	 * @param array $data API response body
	 * @param ImageRecommendationMetadataProvider $metadataProvider
	 * @return ImageRecommendation|StatusValue
	 */
	public static function processApiResponseData(
		LinkTarget $title,
		string $titleText,
		array $data,
		ImageRecommendationMetadataProvider $metadataProvider
	) {
		if ( !$data['pages'] ) {
			return StatusValue::newFatal( 'rawmessage',
				'No recommendation found for page: ' . $titleText );
		}
		$images = [];
		$datasetId = '';
		foreach ( $data['pages'][0]['suggestions'] as $suggestion ) {
			$source = $suggestion['source']['details']['from'];
			$projects = $suggestion['source']['details']['found_on'];
			$datasetId = $suggestion['source']['details']['dataset_id'];
			if ( !in_array( $source, [
				ImageRecommendationImage::SOURCE_WIKIDATA,
				ImageRecommendationImage::SOURCE_WIKIPEDIA,
				ImageRecommendationImage::SOURCE_COMMONS,
			], true ) ) {
				return StatusValue::newFatal( 'rawmessage',
					'Invalid source type for ' . $titleText . ': ' . $source );
			}

			$imageMetadata = $metadataProvider->getMetadata( $suggestion['filename'] );
			if ( is_array( $imageMetadata ) ) {
				$images[] = new ImageRecommendationImage(
					new TitleValue( NS_FILE, $suggestion['filename'] ),
					$source,
					$projects ? explode( ',', $projects ) : [],
					$imageMetadata
				);
			}
		}

		if ( !$images ) {
			return StatusValue::newFatal( 'rawmessage',
				'No recommendation found for page: ' . $titleText );
		}
		return new ImageRecommendation( $title, $images, $datasetId );
	}

}
