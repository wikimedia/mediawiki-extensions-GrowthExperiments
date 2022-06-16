<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use RequestContext;
use StatusValue;
use Title;

class OldImageRecommendationApiHandler implements ImageRecommendationApiHandler {

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
				'Image Suggestions API is not configured' );
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
	public function getSuggestionDataFromApiResponse( array $apiResponse ): array {
		if ( !$apiResponse['pages'] || !$apiResponse['pages'][0]['suggestions'] ) {
			return [];
		}
		$imageData = [];
		foreach ( $apiResponse['pages'][0]['suggestions'] as $suggestion ) {
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
}
