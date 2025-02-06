<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\Assert\Assert;

/**
 * A link recommendation provider that uses the link recommendation service.
 * @see https://wikitech.wikimedia.org/wiki/Add_Link
 */
class ServiceLinkRecommendationProvider implements LinkRecommendationProvider {

	private TitleFactory $titleFactory;

	private RevisionLookup $revisionLookup;

	private HttpRequestFactory $httpRequestFactory;

	private string $url;

	private string $wikiId;

	private string $languageCode;

	private ?string $accessToken;

	/** @var int|null Service request timeout in seconds. */
	private ?int $requestTimeout;

	/**
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $url Link recommendation service root URL
	 * @param string $wikiId Wiki ID (e.g. "simple", "en")
	 * @param string $languageCode the ISO-639 language code (e.g. "az" for Azeri, "en" for English) to use in
	 *   processing the wikitext
	 * @param string|null $accessToken Jwt for authorization with external traffic release of link
	 *   recommendation service
	 * @param int|null $requestTimeout Service request timeout in seconds.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup,
		HttpRequestFactory $httpRequestFactory,
		string $url,
		string $wikiId,
		string $languageCode,
		?string $accessToken,
		?int $requestTimeout
	) {
		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->url = $url;
		$this->wikiId = $wikiId;
		$this->languageCode = $languageCode;
		$this->accessToken = $accessToken;
		$this->requestTimeout = $requestTimeout;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		$status = $this->getDetailed( $title, $taskType );
		if ( $status->isGood() ) {
			return $status->getValue();
		}
		return $status;
	}

	public function getDetailed( LinkTarget $title, TaskType $taskType ): LinkRecommendationEvalStatus {
		Assert::parameterType( LinkRecommendationTaskType::class, $taskType, '$taskType' );
		/** @var LinkRecommendationTaskType $taskType */
		'@phan-var LinkRecommendationTaskType $taskType';
		$title = $this->titleFactory->newFromLinkTarget( $title );
		$pageId = $title->getArticleID();
		$titleText = $title->getPrefixedDBkey();
		$revId = $title->getLatestRevID();

		if ( !$revId ) {
			return LinkRecommendationEvalStatus::newFatal( 'growthexperiments-addlink-pagenotfound', $titleText );
		}
		$content = $this->revisionLookup->getRevisionById( $revId )->getContent( SlotRecord::MAIN );
		if ( !$content ) {
			return LinkRecommendationEvalStatus::newFatal( 'growthexperiments-addlink-revdeleted', $revId, $titleText );
		} elseif ( !( $content instanceof WikitextContent ) ) {
			return LinkRecommendationEvalStatus::newFatal( 'growthexperiments-addlink-wrongmodel', $revId, $titleText );
		}
		$wikitext = $content->getText();

		// FIXME: Don't hardcode 'wikipedia' project
		// FIXME: Use a less hacky way to get the project/subdomain pair that the API gateway wants.
		$pathArgs = [ 'wikipedia', str_replace( 'wiki', '', $this->wikiId ), $titleText ];
		$queryArgs = [
			'threshold' => $taskType->getMinimumLinkScore(),
			'max_recommendations' => $taskType->getMaximumLinksPerTask(),
			'language_code' => $this->languageCode,
		];
		$postBodyArgs = [
			'pageid' => $pageId,
			'revid' => $revId,
			'wikitext' => $wikitext,
		];
		if ( $taskType->getExcludedSections() ) {
			$postBodyArgs['sections_to_exclude'] = $taskType->getExcludedSections();
		}
		$request = $this->httpRequestFactory->create(
			wfAppendQuery(
				$this->url . '/v1/linkrecommendations/' . implode( '/', array_map( 'rawurlencode', $pathArgs ) ),
				$queryArgs
			),
			[
				'method' => 'POST',
				'postData' => json_encode( $postBodyArgs ),
				'originalRequest' => RequestContext::getMain()->getRequest(),
				'timeout' => $this->requestTimeout,
			],
			__METHOD__
		);
		if ( $this->accessToken ) {
			// TODO: Support app authentication with client ID / secret
			// https://api.wikimedia.org/wiki/Documentation/Getting_started/Authentication#App_authentication
			$request->setHeader( 'Authorization', "Bearer $this->accessToken" );
		}
		$request->setHeader( 'Content-Type', 'application/json' );

		$status = $request->execute();
		if ( !$status->isOK() ) {
			return LinkRecommendationEvalStatus::newGood()->merge( $status );
		}
		$response = $request->getContent();

		$data = json_decode( $response, true );
		if ( $data === null ) {
			return LinkRecommendationEvalStatus::newFatal( 'growthexperiments-addlink-invalidjson', $titleText );
		}
		if ( array_key_exists( 'error', $data ) ) {
			return LinkRecommendationEvalStatus::newFatal( 'growthexperiments-addlink-serviceerror',
				$titleText, $data['error'] );
		}
		// TODO validate/process data; compare $data['page_id'] and $data['revid']
		return LinkRecommendationEvalStatus::newGood( new LinkRecommendation(
			$title,
			$pageId,
			$revId,
			LinkRecommendation::getLinksFromArray( $data['links'] ),
			LinkRecommendation::getMetadataFromArray( $data['meta'] + [
				'task_timestamp' => MWTimestamp::time(),
			] )
		) );
	}

}
