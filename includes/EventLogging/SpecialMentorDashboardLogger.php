<?php

namespace GrowthExperiments\EventLogging;

use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;

class SpecialMentorDashboardLogger {

	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/mentor_dashboard/visit/1.1.0';

	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.mentor_dashboard.visit';

	private string $pageviewToken;
	private UserIdentity $user;
	private WebRequest $request;
	private bool $isMobile;

	public function __construct(
		string $pageviewToken,
		UserIdentity $user,
		WebRequest $request,
		bool $isMobile
	) {
		$this->pageviewToken = $pageviewToken;
		$this->user = $user;
		$this->request = $request;
		$this->isMobile = $isMobile;
	}

	public function log(): void {
		$referer = $this->request->getHeader( 'REFERER' );
		$event = [
			'$schema' => self::SCHEMA_VERSIONED,
			'wiki_db' => WikiMap::getCurrentWikiId(),
			'user_id' => $this->user->getId(),
			'is_mobile' => $this->isMobile,
			'pageview_token' => $this->pageviewToken,
		];
		$event['referer_route'] = $this->request->getVal(
			'source',
			// If there is no referer header and no source param, then assume the user went to the
			// page directly from their browser history/bookmark/etc.
			$referer ? 'other' : 'direct'
		);
		EventLogging::submit(
			self::STREAM,
			$event
		);
	}
}
