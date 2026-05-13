<?php

namespace GrowthExperiments\Mentorship\Cleaner;

use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;

class LastActionTimestampLookup {

	public function __construct(
		private UserRegistrationLookup $userRegistrationLookup,
		private UserEditTracker $userEditTracker,
		private LoggerInterface $logger
	) {
	}

	public function getLastActionTimestampForUser( UserIdentity $user ): ?string {
		Assert::precondition( $user->isRegistered(), 'Unexpected non-registered user' );

		$timestamp = $this->userEditTracker->getLatestEditTimestamp( $user );
		if ( $timestamp ) {
			return $timestamp;
		}

		// For users who never edited, their last action is their registration
		$timestamp = $this->userRegistrationLookup->getRegistration( $user );
		if ( $timestamp ) {
			return $timestamp;
		}

		// This is a very old user who never edited
		$this->logger->warning( '{user} has neither latest edit nor a registration timestamp', [
			'user' => $user->getName(),
		] );
		return null;
	}
}
