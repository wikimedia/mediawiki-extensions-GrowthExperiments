<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use RequestContext;
use StatusValue;
use TitleFactory;
use WikitextContent;

/**
 * A link recommendation provider that uses the link recommendation service.
 * @see https://wikitech.wikimedia.org/wiki/Add_Link
 */
class ServiceLinkRecommendationProvider implements LinkRecommendationProvider {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $url;

	/** @var string */
	private $wikiId;

	/** @var string|null */
	private $accessToken;

	/** @var int|null Service request timeout in seconds. */
	private $requestTimeout;

	/**
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $url Link recommendation service root URL
	 * @param string $wikiId Wiki language
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
		?string $accessToken,
		?int $requestTimeout
	) {
		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->url = $url;
		$this->wikiId = $wikiId;
		$this->accessToken = $accessToken;
		$this->requestTimeout = $requestTimeout;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, LinkRecommendationTaskType $taskType ) {
		$title = $this->titleFactory->newFromLinkTarget( $title );
		$pageId = $title->getArticleID();
		$titleText = $title->getPrefixedDBkey();
		$revId = $title->getLatestRevID();

		if ( !$revId ) {
			return StatusValue::newFatal( 'growthexperiments-addlink-pagenotfound', $titleText );
		}
		$content = $this->revisionLookup->getRevisionById( $revId )->getContent( SlotRecord::MAIN );
		if ( !$content ) {
			return StatusValue::newFatal( 'growthexperiments-addlink-revdeleted', $revId, $titleText );
		} elseif ( !( $content instanceof WikitextContent ) ) {
			return StatusValue::newFatal( 'growthexperiments-addlink-wrongmodel', $revId, $titleText );
		}
		$wikitext = $content->getText();

		$pathArgs = [ $this->wikiId, $titleText ];
		$queryArgs = [
			'threshold' => $taskType->getMinimumLinkScore(),
			'max_recommendations' => $taskType->getMaximumLinksPerTask()
		];
		$postBodyArgs = [
			'pageid' => $pageId,
			'revid' => $revId,
			'wikitext' => $wikitext,
		];
		$request = $this->httpRequestFactory->create(
			wfAppendQuery(
				$this->url . '/v0/linkrecommendations/' . implode( '/', array_map( function ( $arg ) {
					return rawurlencode( $arg );
				}, $pathArgs ) ),
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
			return $status;
		}
		$response = $request->getContent();

		$data = json_decode( $response, true );
		if ( $data === null ) {
			return StatusValue::newFatal( 'growthexperiments-addlink-invalidjson', $titleText );
		}
		if ( array_key_exists( 'error', $data ) ) {
			return StatusValue::newFatal( 'growthexperiments-addlink-serviceerror',
				$titleText, $data['error'] );
		}
		// TODO validate/process data; compare $data['page_id'] and $data['revid']
		$links = LinkRecommendation::getLinksFromArray( $data['links'] );
		return new LinkRecommendation( $title, $pageId, $revId, $links );
	}

}
