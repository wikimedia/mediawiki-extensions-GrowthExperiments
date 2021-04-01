<?php

namespace GrowthExperiments\Mentorship\Store;

use JobQueueGroup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;
use UserOptionsUpdateJob;

class PreferenceMentorStore extends MentorStore {
	/** @var string User preference for storing the mentor. */
	public const MENTOR_PREF = 'growthexperiments-mentor-id';

	/** @var UserFactory */
	private $userFactory;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/** @var bool */
	private $wasPosted;

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
		parent::__construct();

		$this->userFactory = $userFactory;
		$this->userOptionsManager = $userOptionsManager;
		$this->wasPosted = $wasPosted;
	}

	/**
	 * @inheritDoc
	 */
	protected function loadMentorUserUncached(
		UserIdentity $mentee,
		$flags
	): ?UserIdentity {
		$mentorId = $this->userOptionsManager->getIntOption(
			$mentee,
			static::MENTOR_PREF,
			0,
			$flags
		);
		$user = $this->userFactory->newFromId( $mentorId );
		$user->load();
		return $user->isRegistered() ? $user : null;
	}

	/**
	 * @inheritDoc
	 */
	public function setMentorForUser( UserIdentity $mentee, UserIdentity $mentor ): void {
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

		$this->invalidateMentorCache( $mentee );
	}
}
