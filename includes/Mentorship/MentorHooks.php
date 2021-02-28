<?php

namespace GrowthExperiments\Mentorship;

use Config;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Util;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use Throwable;

class MentorHooks implements GetPreferencesHook, LocalUserCreatedHook {
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ MentorPageMentorManager::MENTOR_PREF ] = [
			'type' => 'api',
		];
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $this->config->get( 'GEMentorshipEnabled' ) ) {
			try {
				// Select a mentor. FIXME Not really necessary, but avoids a change in functionality
				//   after introducing MentorManager, making debugging easier.
				GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )
					->getMentorManager()->getMentorForUser( $user );
			} catch ( Throwable $throwable ) {
				Util::logError( $throwable, [
					'user' => $user->getId(),
					'impact' => 'Failed to assign mentor for user',
					'origin' => __METHOD__,
				] );
			}
		}
	}

}
