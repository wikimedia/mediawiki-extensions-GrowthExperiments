<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\Config\Config;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class MenteeGraduation {

	private readonly bool $isEnabled;
	private readonly int $minEdits;
	private readonly int $minTenureInDays;

	public function __construct(
		Config $wikiConfig,
		private UserEditTracker $userEditTracker,
		private UserRegistrationLookup $userRegistrationLookup,
		private IMentorManager $mentorManager
	) {
		$thresholds = $wikiConfig->get( 'GEMentorshipStartOptedOutThresholds' );

		$this->isEnabled = $thresholds->enabled;
		$this->minEdits = $thresholds->minEditcount;
		$this->minTenureInDays = $thresholds->minTenureInDays;
	}

	public function getIsEnabled(): bool {
		return $this->isEnabled;
	}

	private function isEditcountConditionMetForUser( UserIdentity $user ): bool {
		return $this->userEditTracker->getUserEditCount( $user ) >= $this->minEdits;
	}

	private function isTenureConditionMetForUser( UserIdentity $user ): bool {
		$registrationTimestamp = $this->userRegistrationLookup->getRegistration( $user );
		if ( $registrationTimestamp === null || $registrationTimestamp === false ) {
			return true;
		}

		$registeredAgo = (int)ConvertibleTimestamp::now( TS_UNIX )
			- (int)ConvertibleTimestamp::convert( TS_UNIX, $registrationTimestamp );
		return $registeredAgo > $this->minTenureInDays * ExpirationAwareness::TTL_DAY;
	}

	public function doesUserMeetOptOutConditions( UserIdentity $user ): bool {
		return $this->getIsEnabled()
			&& $this->isEditcountConditionMetForUser( $user )
			&& $this->isTenureConditionMetForUser( $user );
	}

	public function shouldUserBeGraduated( UserIdentity $user ): bool {
		return $this->mentorManager->getMentorshipStateForUser( $user ) === IMentorManager::MENTORSHIP_ENABLED
			&& !$this->mentorManager->didUserExplicitlyOptIntoMentorship( $user )
			&& $this->doesUserMeetOptOutConditions( $user );
	}

	public function graduateUserFromMentorship( UserIdentity $user ): void {
		$this->mentorManager->setMentorshipStateForUser( $user, IMentorManager::MENTORSHIP_OPTED_OUT );
	}
}
