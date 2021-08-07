<?php

namespace GrowthExperiments\Mentorship;

use Config;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Util;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use Psr\Log\LogLevel;
use Throwable;

class MentorHooks implements GetPreferencesHook, UserGetDefaultOptionsHook, LocalUserCreatedHook {
	/** @var Config */
	private $wikiConfig;

	/**
	 * @param Config $wikiConfig
	 */
	public function __construct( Config $wikiConfig ) {
		$this->wikiConfig = $wikiConfig;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ MentorPageMentorManager::MENTOR_PREF ] = [
			'type' => 'api',
		];
		$preferences[ MentorPageMentorManager::MENTORSHIP_ENABLED_PREF ] = [
			'type' => 'api'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			MentorPageMentorManager::MENTORSHIP_ENABLED_PREF => 1
		];
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $autocreated ) {
			// Excluding autocreated users is necessary, see T276720
			return;
		}
		if ( $this->wikiConfig->get( 'GEMentorshipEnabled' ) ) {
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
				], LogLevel::INFO );
			}
		}
	}

}
