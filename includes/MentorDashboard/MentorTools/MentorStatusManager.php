<?php

namespace GrowthExperiments\MentorDashboard\MentorTools;

use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class MentorStatusManager {

	/** @var string Mentor status */
	public const STATUS_ACTIVE = 'active';
	/** @var string Mentor status */
	public const STATUS_AWAY = 'away';

	/** @var string[] List of MentorStatusManager::STATUS_* constants */
	public const STATUSES = [
		self::STATUS_ACTIVE,
		self::STATUS_AWAY
	];

	/** @var string Preference key to store mentor's away timestamp */
	public const MENTOR_AWAY_TIMESTAMP_PREF = 'growthexperiments-mentor-away-timestamp';

	/** @var int Number of seconds in a day */
	private const SECONDS_DAY = 86400;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		UserOptionsManager $userOptionsManager,
		UserFactory $userFactory
	) {
		$this->userOptionsManager = $userOptionsManager;
		$this->userFactory = $userFactory;
	}

	/**
	 * Get mentor's current status
	 *
	 * @param UserIdentity $mentor
	 * @return string one of MentorStatusManager::STATUS_* constants
	 */
	public function getMentorStatus( UserIdentity $mentor ): string {
		if ( $this->getMentorBackTimestamp( $mentor ) === null ) {
			return self::STATUS_ACTIVE;
		} else {
			return self::STATUS_AWAY;
		}
	}

	/**
	 * @param UserIdentity $mentor
	 * @return string|null Null if mentor is currently active
	 */
	public function getMentorBackTimestamp( UserIdentity $mentor ): ?string {
		$rawTs = $this->userOptionsManager->getOption(
			$mentor,
			self::MENTOR_AWAY_TIMESTAMP_PREF
		);
		if (
			$rawTs === null ||
			ConvertibleTimestamp::convert( TS_UNIX, $rawTs ) < wfTimestamp( TS_UNIX )
		) {
			return null;
		}

		return $rawTs;
	}

	/**
	 * Mark a mentor as away
	 *
	 * @param UserIdentity $mentor
	 * @param int $backInDays Length of mentor's wiki-vacation in days
	 */
	public function markMentorAsAway( UserIdentity $mentor, int $backInDays ): void {
		$this->userOptionsManager->setOption(
			$mentor,
			self::MENTOR_AWAY_TIMESTAMP_PREF,
			ConvertibleTimestamp::convert(
				TS_MW,
				wfTimestamp( TS_UNIX ) + self::SECONDS_DAY * $backInDays
			)
		);
		$this->userFactory->newFromUserIdentity( $mentor )->saveSettings();
	}

	/**
	 * Mark a mentor as active
	 *
	 * @param UserIdentity $mentor
	 */
	public function markMentorAsActive( UserIdentity $mentor ): void {
		$this->userOptionsManager->setOption(
			$mentor,
			self::MENTOR_AWAY_TIMESTAMP_PREF,
			null
		);
		$this->userFactory->newFromUserIdentity( $mentor )->saveSettings();
	}
}
