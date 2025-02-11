<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use InvalidArgumentException;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\DBReadOnlyError;

class MentorManager implements IMentorManager, LoggerAwareInterface {
	use LoggerAwareTrait;

	public const MENTORSHIP_ENABLED_PREF = 'growthexperiments-homepage-mentorship-enabled';

	private MentorStore $mentorStore;
	private MentorStatusManager $mentorStatusManager;
	private MentorProvider $mentorProvider;
	private UserIdentityLookup $userIdentityLookup;
	private UserOptionsLookup $userOptionsLookup;
	private UserFactory $userFactory;
	private UserOptionsManager $userOptionsManager;
	private bool $wasPosted;

	public function __construct(
		MentorStore $mentorStore,
		MentorStatusManager $mentorStatusManager,
		MentorProvider $mentorProvider,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		UserOptionsLookup $userOptionsLookup,
		UserOptionsManager $userOptionsManager,
		bool $wasPosted
	) {
		$this->mentorStore = $mentorStore;
		$this->mentorStatusManager = $mentorStatusManager;
		$this->mentorProvider = $mentorProvider;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userOptionsManager = $userOptionsManager;
		$this->wasPosted = $wasPosted;

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Determine whether an user is ineligible for mentorship
	 *
	 * Users can only have mentors if they meet _all_ following criteria:
	 * 	(1) is not opted out from mentorship
	 *	(2) is not indefinitely blocked
	 *
	 * @param UserIdentity $user
	 * @return bool True if the user should not have a mentor assigned
	 */
	private function isUserIneligibleForMentorship( UserIdentity $user ): bool {
		$block = $this->userFactory->newFromUserIdentity( $user )->getBlock();
		return $this->getMentorshipStateForUser( $user ) === self::MENTORSHIP_OPTED_OUT ||
			( $block && $block->isIndefinite() );
	}

	private function getMentorUserIdentityIfExists(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?UserIdentity {
		$mentorUser = $this->mentorStore->loadMentorUser( $user, $role );

		if ( $this->isUserIneligibleForMentorship( $user ) ) {
			if ( $mentorUser ) {
				// User is ineligible for mentorship, but has a mentor anyway. Clean up the
				// invalid relationship. This is necessary to ensure falsy relationships are not
				// included in dumps etc.
				$this->mentorStore->dropMenteeRelationship( $user );
			}

			// Regardless of what is in the database, the user should not have any mentor (T351415)
			return null;
		}

		if ( $mentorUser && $role === MentorStore::ROLE_BACKUP ) {
			// Only use the saved backup mentor if they're still eligible to be a backup mentor.
			// Ignore the current backup mentor relationship if any of the following applies:
			//     a) the backup mentor is away
			//     b) the backup mentor is no longer a mentor
			if (
				$this->mentorStatusManager->getMentorStatus( $mentorUser ) === MentorStatusManager::STATUS_AWAY ||
				!$this->mentorProvider->isMentor( $mentorUser )
			) {
				// Drop the relationship. We do not need to remember the user and exclude later
				// in getRandomAutoAssignedMentorForUserAndRole – that method will ensure only an
				// eligible backup user is generated.
				$mentorUser = null;
			}
		}

		return $mentorUser;
	}

	/** @inheritDoc */
	public function getMentorForUserIfExists(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor {
		$mentorUser = $this->getMentorUserIdentityIfExists( $user, $role );
		if ( !$mentorUser ) {
			return null;
		}

		return $this->mentorProvider->newMentorFromUserIdentity( $mentorUser, $user );
	}

	/**
	 * Wrapper for getRandomAutoAssignedMentor
	 *
	 * In addition to getRandomAutoAssignedMentor, this is mentor role-aware,
	 * and automatically excludes the primary mentor if generating a mentor
	 * for a non-primary role.
	 *
	 * If $role is ROLE_BACKUP, it also makes sure to not generate a mentor that's away.
	 *
	 * @param UserIdentity $mentee
	 * @param string $role One of MentorStore::ROLE_* roles
	 * @return UserIdentity|null Mentor that can be assigned to the mentee
	 * @throws WikiConfigException if mentor list configuration is invalid
	 */
	private function getRandomAutoAssignedMentorForUserAndRole(
		UserIdentity $mentee,
		string $role
	): ?UserIdentity {
		if ( $this->isUserIneligibleForMentorship( $mentee ) ) {
			// Do not assign any mentors for ineligible users
			return null;
		}

		$excludedUsers = [];
		if ( $role !== MentorStore::ROLE_PRIMARY ) {
			$primaryMentor = $this->mentorStore->loadMentorUser(
				$mentee,
				MentorStore::ROLE_PRIMARY
			);
			if ( $primaryMentor ) {
				$excludedUsers[] = $primaryMentor;
			}
		}
		if ( $role === MentorStore::ROLE_BACKUP ) {
			$excludedUsers = array_merge(
				$excludedUsers,
				$this->mentorStatusManager->getAwayMentors()
			);
		}

		return $this->getRandomAutoAssignedMentor( $mentee, $excludedUsers );
	}

	/** @inheritDoc */
	public function getMentorForUserSafe(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor {
		try {
			$mentorUser = $this->getMentorUserIdentityIfExists( $user, $role );

			if ( !$mentorUser ) {
				$mentorUser = $this->getRandomAutoAssignedMentorForUserAndRole( $user, $role );
				if ( $mentorUser ) {
					$this->mentorStore->setMentorForUser( $user, $mentorUser, $role );
				}
			}

			if ( $mentorUser ) {
				$mentorUser = $this->mentorProvider->newMentorFromUserIdentity( $mentorUser, $user );
			}
			return $mentorUser;
		} catch ( DBReadOnlyError $e ) {
			// Just pretend the user doesn't have a mentor. It will be set later, and often
			// this call is made in the context of something not specifically mentorship-
			// related, such as the homepage, so it's better than erroring out.
		}
		return null;
	}

	/** @inheritDoc */
	public function getEffectiveMentorForUserSafe( UserIdentity $menteeUser ): ?Mentor {
		$primaryMentor = $this->getMentorForUserSafe( $menteeUser, MentorStore::ROLE_PRIMARY );
		if ( $primaryMentor === null ) {
			// If primary mentor cannot be assigned, there's zero chance to successfully assign any
			// mentor.
			return null;
		}

		if ( $this->mentorStatusManager->getMentorStatus( $primaryMentor->getUserIdentity() ) ===
			MentorStatusManager::STATUS_ACTIVE ) {
			return $primaryMentor;
		} else {
			return $this->getMentorForUserSafe( $menteeUser, MentorStore::ROLE_BACKUP );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getRandomAutoAssignedMentor(
		UserIdentity $mentee, array $excluded = []
	): ?UserIdentity {
		$autoAssignedMentors = $this->mentorProvider->getWeightedAutoAssignedMentors();
		if ( count( $autoAssignedMentors ) === 0 ) {
			$this->logger->debug(
				'Mentorship: No mentor available for user {user}',
				[
					'user' => $mentee->getName(),
				]
			);
			return null;
		}
		$autoAssignedMentors = array_values( array_diff( $autoAssignedMentors,
			array_map( static function ( UserIdentity $excludedUser ) {
				return $excludedUser->getName();
			}, $excluded )
		) );
		if ( count( $autoAssignedMentors ) === 0 ) {
			$this->logger->debug(
				'Mentorship: No mentor available for {user} but excluded users',
				[
					'user' => $mentee->getName(),
				]
			);
			return null;
		}
		$autoAssignedMentors = array_values( array_diff( $autoAssignedMentors, [ $mentee->getName() ] ) );
		if ( count( $autoAssignedMentors ) === 0 ) {
			$this->logger->debug(
				'Mentorship: No mentor available for {user} but themselves',
				[
					'user' => $mentee->getName(),
				]
			);
			return null;
		}

		$selectedMentorName = $autoAssignedMentors[ rand( 0, count( $autoAssignedMentors ) - 1 ) ];
		$result = $this->userIdentityLookup->getUserIdentityByName( $selectedMentorName );
		if ( $result === null ) {
			throw new WikiConfigException(
				'Mentorship: Mentor {user} does not have a valid username',
				[ 'user' => $selectedMentorName ]
			);
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function getMentorshipStateForUser( UserIdentity $user ): int {
		$state = $this->userOptionsLookup->getIntOption( $user, self::MENTORSHIP_ENABLED_PREF );
		if ( !in_array( $state, self::MENTORSHIP_STATES ) ) {
			// default to MENTORSHIP_DISABLED and log an error
			$this->logger->error(
				'User {user} has invalid value of {property} user property',
				[
					'user' => $user->getName(),
					'property' => self::MENTORSHIP_ENABLED_PREF,
					'impact' => 'defaulting to MENTORSHIP_DISABLED',
				]
			);
			return self::MENTORSHIP_DISABLED;
		}

		return $state;
	}

	/**
	 * @inheritDoc
	 */
	public function setMentorshipStateForUser( UserIdentity $user, int $state ): void {
		if ( !in_array( $state, self::MENTORSHIP_STATES ) ) {
			throw new InvalidArgumentException(
				'Invalid value of $state passed to ' . __METHOD__
			);
		}

		$this->userOptionsManager->setOption(
			$user,
			self::MENTORSHIP_ENABLED_PREF,
			$state
		);
		$this->userOptionsManager->saveOptions( $user );

		if ( $state === self::MENTORSHIP_OPTED_OUT ) {
			// user opted out, drop mentor/mentee relationship
			$this->mentorStore->dropMenteeRelationship( $user );
		} elseif ( $state === self::MENTORSHIP_ENABLED ) {
			// The user has opted-in to mentorship. Calling getMentorForUserSafe will
			// persist the newly assigned mentor(s) in the MentorStore.
			$this->getMentorForUserSafe( $user );
		}
	}
}
