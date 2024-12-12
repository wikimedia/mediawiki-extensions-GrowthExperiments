<?php

namespace GrowthExperiments\PeriodicMetrics;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;

class InactiveMentorsMetric implements IMetric {

	/** @var int Number of seconds a mentor has to be without any edit to be inactive */
	private const INACTIVITY_THRESHOLD = 30 * ExpirationAwareness::TTL_DAY;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var MentorProvider */
	private $mentorProvider;

	/**
	 * @param UserEditTracker $userEditTracker
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param MentorProvider $mentorProvider
	 */
	public function __construct(
		UserEditTracker $userEditTracker,
		UserIdentityLookup $userIdentityLookup,
		MentorProvider $mentorProvider
	) {
		$this->userEditTracker = $userEditTracker;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->mentorProvider = $mentorProvider;
	}

	/**
	 * @inheritDoc
	 */
	public function calculate(): int {
		$inactiveMentors = 0;
		foreach ( $this->mentorProvider->getAutoAssignedMentors() as $mentorName ) {
			$mentor = $this->userIdentityLookup->getUserIdentityByName( $mentorName );
			if ( $mentor === null ) {
				continue;
			}

			$lastEditTimestamp = $this->userEditTracker->getLatestEditTimestamp( $mentor );
			$secondsSinceLastEdit = (int)MWTimestamp::now( TS_UNIX ) -
				(int)MWTimestamp::getInstance( $lastEditTimestamp )->getTimestamp( TS_UNIX );

			if ( $secondsSinceLastEdit > self::INACTIVITY_THRESHOLD ) {
				$inactiveMentors++;
			}
		}
		return $inactiveMentors;
	}

	/**
	 * @inheritDoc
	 */
	public function getStatsdKey(): string {
		return 'GrowthExperiments.Mentorship.InactiveMentors';
	}

	/**
	 * @inheritDoc
	 */
	public function getStatsLibKey(): string {
		return 'mentorship_inactive_mentors';
	}
}
