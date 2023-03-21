<?php

namespace GrowthExperiments\EventLogging;

use EventLogging;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use WebRequest;

class SpecialMentorDashboardLogger {

	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/mentor_dashboard/visit/1.0.1';

	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.mentor_dashboard.visit';

	private UserIdentity $user;
	private WebRequest $request;
	private bool $isMobile;

	/**
	 * @param UserIdentity $user
	 * @param WebRequest $request
	 * @param bool $isMobile
	 */
	public function __construct(
		UserIdentity $user,
		WebRequest $request,
		bool $isMobile
	) {
		$this->user = $user;
		$this->request = $request;
		$this->isMobile = $isMobile;
	}

	/**
	 * @return void
	 */
	public function log() {
		$referer = $this->request->getHeader( 'REFERER' );
		$event = [
			'$schema' => self::SCHEMA_VERSIONED,
			'wiki_db' => WikiMap::getCurrentWikiId(),
			'user_id' => $this->user->getId(),
			'is_mobile' => $this->isMobile,
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
