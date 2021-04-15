<?php

namespace GrowthExperiments\Mentorship\Store;

use InvalidArgumentException;
use JobQueueGroup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsManager;
use UserOptionsUpdateJob;

class PreferenceMentorStore extends MentorStore {
	/** @var string User preference for storing the mentor. */
	public const MENTOR_PREF = 'growthexperiments-mentor-id';

	/** @var UserFactory */
	private $userFactory;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param UserFactory $userFactory
	 * @param UserOptionsManager $userOptionsManager
	 * @param bool $wasPosted
	 */
	public function __construct(
		UserFactory $userFactory,
		UserOptionsManager $userOptionsManager,
		bool $wasPosted
	) {
		parent::__construct( $wasPosted );

		$this->userFactory = $userFactory;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @inheritDoc
	 */
	public function loadMentorUserUncached(
		UserIdentity $mentee,
		string $mentorRole,
		$flags
	): ?UserIdentity {
		// As of now, we don't have non-primary mentors, and by the time T227876 is worked on,
		// we will use DatabaseMentorStore. Make sure we throw if mentor is not primary for safety.
		// This should never actually happen.
		if ( $mentorRole !== self::ROLE_PRIMARY ) {
			throw new InvalidArgumentException(
				'PreferenceMentorStore cannot handle non-primary mentors'
			);
		}

		$mentorId = $this->userOptionsManager->getIntOption(
			$mentee,
			static::MENTOR_PREF,
			0,
			$flags
		);
		$user = $this->userFactory->newFromId( $mentorId );
		$user->load();
		if ( !$user->isRegistered() ) {
			return null;
		}
		return new UserIdentityValue( $user->getId(), $user->getName() );
	}

	/**
	 * @inheritDoc
	 */
	protected function setMentorForUserInternal(
		UserIdentity $mentee,
		UserIdentity $mentor,
		string $mentorRole = self::ROLE_PRIMARY
	): void {
		// As of now, we don't have non-primary mentors, and by the time T227876 is worked on,
		// we will use DatabaseMentorStore. Make sure we throw if mentor is not primary for safety.
		// This should never actually happen.
		if ( $mentorRole !== self::ROLE_PRIMARY ) {
			throw new InvalidArgumentException(
				'PreferenceMentorStore cannot handle non-primary mentors'
			);
		}

		$this->userOptionsManager->setOption( $mentee, static::MENTOR_PREF, $mentor->getId() );

		// setMentorForUser is safe to call in GET requests. Call saveOptions only
		// when we're in a POST request, change it with a job if we're in a GET request.
		// setOption is outside of this if to set the option immediately in
		// UserOptionsManager's in-process cache to avoid race conditions.
		if ( $this->wasPosted ) {
			// Do not defer to job queue when in a POST request, assures quicker
			// propagation of mentor changes.
			$this->userOptionsManager->saveOptions( $mentee );
		} else {
			JobQueueGroup::singleton()->lazyPush( new UserOptionsUpdateJob( [
				'userId' => $mentee->getId(),
				'options' => [ static::MENTOR_PREF => $mentor->getId() ]
			] ) );
		}
	}
}
