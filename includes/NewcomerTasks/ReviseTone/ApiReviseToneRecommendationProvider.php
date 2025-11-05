<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\ReviseTone;

use GrowthExperiments\NewcomerTasks\Recommendation;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\ReviseToneRecommendation;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleFactory;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Stats\StatsFactory;

class ApiReviseToneRecommendationProvider implements RecommendationProvider {
	private readonly StatsFactory $statsFactory;

	public function __construct(
		private readonly string $apiUrl,
		private readonly string $wikiId,
		private readonly TitleFactory $titleFactory,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly LoggerInterface $logger,
		StatsFactory $statsFactory,
	) {
		$this->statsFactory = $statsFactory->withComponent( 'GrowthExperiments' );
	}

	public function get( LinkTarget $title, TaskType $taskType ): Recommendation|StatusValue {
		$articleTitle = $this->titleFactory->newFromLinkTarget( $title );
		$pageId = $articleTitle->getArticleID();
		$revisionId = $articleTitle->getLatestRevID( IDBAccessObject::READ_LATEST );

		if ( !$this->apiUrl ) {
			return StatusValue::newFatal( 'rawmessage', 'ReviseTone API URL is not configured' );
		}

		$request = $this->getRequest( [
			'public',
			'ml_cache',
			'page_paragraph_tone_scores',
			$this->wikiId,
			$pageId,
			$revisionId,
		] );

		$timer = $this->statsFactory->getTiming( 'revise_tone_provider_request_seconds' )->start();
		$status = $request->execute();
		$timer->stop();

		$requestCounter = $this->statsFactory->getCounter( 'revise_tone_provider_request_total' );
		if ( !$status->isOK() ) {
			$this->logger->error( 'Error response from ReviseTone API for title {title} and revision {revision}', [
				'title' => $title->getDBkey(),
				'revision' => $revisionId,
				'status' => $status,
			] );
			$requestCounter
				->setLabel( 'status', 'error' )
				->increment();
			return $status;
		}
		$response = $request->getContent();
		$data = json_decode( $response, true, flags: JSON_THROW_ON_ERROR );
		if ( !$data || !isset( $data['rows'] ) || !is_array( $data['rows'] ) ) {
			$this->logger->error( 'Invalid response from ReviseTone API for title {title} and revision {revision}', [
				'title' => $title->getDBkey(),
				'revision' => $revisionId,
				'response' => $response,
			] );
			$requestCounter
				->setLabel( 'status', 'invalid' )
				->increment();
			return StatusValue::newFatal( 'rawmessage', 'Invalid response from ReviseTone DataGateway API' );
		}
		$resultRows = $data['rows'];
		if ( count( $resultRows ) === 0 ) {
			$this->logger->warning( 'No tone suggestions found from API for title {title} and revision {revision}', [
				'title' => $title->getDBkey(),
				'revision' => $revisionId,
			] );
			$requestCounter
				->setLabel( 'status', 'no_suggestions' )
				->increment();
			// TODO: trigger clearing weighted tag for page here (T407538)
			return StatusValue::newFatal( 'rawmessage', 'No tone suggestions found from API' );
		}
		$requestCounter
			->setLabel( 'status', 'OK' )
			->increment();
		return $this->extractRecommendationFromApiResults( $title, $resultRows );
	}

	private function getRequest( array $pathArgs = [] ): MWHttpRequest {
		$request = $this->httpRequestFactory->create(
			$this->apiUrl . '/' . implode( '/', array_map( 'rawurlencode', $pathArgs ) ),
			[
				'method' => 'GET',
				'originalRequest' => RequestContext::getMain()->getRequest(),
			],
			__METHOD__
		);
		$request->setHeader( 'Accept', 'application/json' );
		return $request;
	}

	private function extractRecommendationFromApiResults(
		LinkTarget $title,
		array $rows
	): ReviseToneRecommendation|StatusValue {
		$modelVersions = array_unique( array_map( static fn ( $row ) => $row['model_version'], $rows ) );
		$latestModelVersion = max( $modelVersions );
		$latestRows = array_filter( $rows, static fn ( $row ) => $row['model_version'] === $latestModelVersion );

		$highestScoringRow = array_reduce( $latestRows, static fn ( $carry, $row ) =>
			$carry === null || $row['score'] > $carry['score'] ? $row : $carry, null );

		return new ReviseToneRecommendation( $title, $highestScoringRow['content'] );
	}
}
