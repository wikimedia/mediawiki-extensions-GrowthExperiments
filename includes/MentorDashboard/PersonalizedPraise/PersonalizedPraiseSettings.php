<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use Config;
use FormatJson;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

/**
 * Accessor for mentor's Personalized praise settings
 *
 * The settings are modified on the frontend (via action=options); this is why there are no
 * setters available.
 */
class PersonalizedPraiseSettings {

	/** @var int */
	public const NOTIFY_NEVER = -1;
	/** @var int */
	public const NOTIFY_IMMEDIATELY = 0;

	/** @var string Note: This is hardcoded on the client side as well */
	public const PREF_NAME = 'growthexperiments-personalized-praise-settings';

	private Config $wikiConfig;
	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @param Config $wikiConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		Config $wikiConfig,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->wikiConfig = $wikiConfig;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	private function loadSettings( UserIdentity $user ): array {
		return FormatJson::decode( $this->userOptionsLookup->getOption(
			$user, self::PREF_NAME
		), true ) ?? [];
	}

	/**
	 * Get praiseworthy conditions for a mentor
	 *
	 * Defaults are provided by Community configuration as:
	 *
	 *  1) GEPersonalizedPraiseMaxEdits: the maximum number of edits a mentee must have to be
	 *     praiseworthy
	 *  2) GEPersonalizedPraiseMinEdits: the minimum number of edits a mentee must have to be
	 *     praiseworthy
	 *  3) GEPersonalizedPraiseDays: to be considered praiseworthy, a mentee needs to make a
	 *     certain number of edits (see above) in this amount of days to be praiseworthy.
	 *
	 * @param UserIdentity $user
	 * @return PraiseworthyConditions
	 */
	public function getPraiseworthyConditions( UserIdentity $user ): PraiseworthyConditions {
		$settings = $this->loadSettings( $user );

		return new PraiseworthyConditions(
			$settings['maxEdits'] ?? (int)$this->wikiConfig->get( 'GEPersonalizedPraiseMaxEdits' ),
			$settings['minEdits'] ?? (int)$this->wikiConfig->get( 'GEPersonalizedPraiseMinEdits' ),
			$settings['days'] ?? (int)$this->wikiConfig->get( 'GEPersonalizedPraiseDays' ),
		);
	}

	/**
	 * Get default subject for the praising message
	 *
	 * The default text of the message is configured via a subpage of the mentor's userpage, to
	 * make use of native MediaWiki preloading.
	 *
	 * @param UserIdentity $user
	 * @return string|null
	 */
	public function getPraisingMessageDefaultSubject( UserIdentity $user ): ?string {
		return $this->loadSettings( $user )[ 'messageSubject' ] ?? null;
	}

	/**
	 * How frequently should the user be notified about new praiseworthy mentees?
	 *
	 * @param UserIdentity $user
	 * @return int Minimum number of hours that needs to pass since the last notification
	 * (values specified by PersonalizedPraiseSettings::NOTIFY_* constants have special meaning)
	 */
	public function getNotificationsFrequency( UserIdentity $user ): int {
		return $this->loadSettings( $user )['notifications-frequency'] ?? self::NOTIFY_IMMEDIATELY;
	}
}
