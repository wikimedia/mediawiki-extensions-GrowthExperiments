<?php

namespace GrowthExperiments\Mentorship\Store;

use DBAccessObjectUtils;
use JobQueueGroup;
use LogicException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use WANObjectCache;
use Wikimedia\Rdbms\IDatabase;

class DatabaseMentorStore extends MentorStore {
	/** @var UserFactory */
	private $userFactory;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/** @var IDatabase */
	private $dbr;

	/** @var IDatabase */
	private $dbw;

	/** @var bool */
	private $useIsActive;

	/**
	 * @param WANObjectCache $wanCache
	 * @param UserFactory $userFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param JobQueueGroup $jobQueueGroup
	 * @param IDatabase $dbr
	 * @param IDatabase $dbw
	 * @param bool $wasPosted
	 * @param bool $useIsActive Temporary flag, controls whether gemm_mentee_is_active is accessed
	 */
	public function __construct(
		WANObjectCache $wanCache,
		UserFactory $userFactory,
		UserIdentityLookup $userIdentityLookup,
		JobQueueGroup $jobQueueGroup,
		IDatabase $dbr,
		IDatabase $dbw,
		bool $wasPosted,
		bool $useIsActive
	) {
		parent::__construct( $wanCache, $wasPosted );

		$this->userFactory = $userFactory;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->dbr = $dbr;
		$this->dbw = $dbw;
		$this->useIsActive = $useIsActive;
	}

	/**
	 * @inheritDoc
	 */
	public function loadMentorUserUncached(
		UserIdentity $mentee,
		string $mentorRole,
		$flags
	): ?UserIdentity {
		list( $index, $options ) = DBAccessObjectUtils::getDBOptions( $flags );
		$db = ( $index === DB_PRIMARY ) ? $this->dbw : $this->dbr;
		$id = $db->newSelectQueryBuilder()
			->select( 'gemm_mentor_id' )
			->from( 'growthexperiments_mentor_mentee' )
			->where( [
				'gemm_mentee_id' => $mentee->getId(),
				'gemm_mentor_role' => $mentorRole,
			] )
			->options( $options )
			->caller( __METHOD__ )
			->fetchField();

		if ( $id === false ) {
			// No mentor in the database, return null
			return null;
		}

		// Construct & return the user
		$user = $this->userFactory->newFromId( $id );
		// Return null if user does not exist
		$user->load();
		if ( !$user->isRegistered() ) {
			return null;
		}
		return new UserIdentityValue( $user->getId(), $user->getName() );
	}

	/**
	 * @inheritDoc
	 */
	public function getMenteesByMentor(
		UserIdentity $mentor,
		?string $mentorRole = null,
		bool $includeHiddenUsers = false,
		bool $includeInactiveUsers = true,
		int $flags = 0
	): array {
		list( $index, $options ) = DBAccessObjectUtils::getDBOptions( $flags );
		$db = ( $index === DB_PRIMARY ) ? $this->dbw : $this->dbr;

		$conds = [
			'gemm_mentor_id' => $mentor->getId()
		];

		if ( $mentorRole === null ) {
			wfDeprecated( __METHOD__ . ' with no role parameter', '1.39' );
		} else {
			$conds['gemm_mentor_role'] = $mentorRole;
		}

		if ( !$includeInactiveUsers ) {
			if ( !$this->useIsActive ) {
				$this->logger->error(
					__METHOD__ .
					': $includeInactiveUsers=false, but GEMentorshipUseIsActiveFlag=false',
					[
						'impact' => '$includeInactiveUsers=false is ignored'
					]
				);
			} else {
				$conds['gemm_mentee_is_active'] = true;
			}
		}

		$ids = $db->newSelectQueryBuilder()
			->select( 'gemm_mentee_id' )
			->from( 'growthexperiments_mentor_mentee' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchFieldValues();

		if ( $ids === [] ) {
			return [];
		}

		$builder = $this->userIdentityLookup
			->newSelectQueryBuilder()
			->registered()
			->whereUserIds( $ids );

		if ( !$includeHiddenUsers ) {
			$builder->hidden( false );
		}

		return iterator_to_array( $builder
			->fetchUserIdentities() );
	}

	/**
	 * Really set a mentor for a given user
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity|null $mentor Set to null to drop the relationship
	 * @param string $mentorRole
	 */
	private function setMentorForUserReal(
		UserIdentity $mentee,
		?UserIdentity $mentor,
		string $mentorRole
	): void {
		if ( $mentor === null ) {
			$this->dbw->delete(
				'growthexperiments_mentor_mentee',
				[
					'gemm_mentee_id' => $mentee->getId(),
					'gemm_mentor_role' => $mentorRole
				],
				__METHOD__
			);
			return;
		}
		$this->dbw->upsert(
			'growthexperiments_mentor_mentee',
			[
				'gemm_mentee_id' => $mentee->getId(),
				'gemm_mentor_id' => $mentor->getId(),
				'gemm_mentor_role' => $mentorRole,
			],
			[ [ 'gemm_mentee_id', 'gemm_mentor_role' ] ],
			[
				'gemm_mentor_id' => $mentor->getId()
			],
			__METHOD__
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function setMentorForUserInternal(
		UserIdentity $mentee,
		?UserIdentity $mentor,
		string $mentorRole
	): void {
		if ( $this->wasPosted ) {
			$this->setMentorForUserReal(
				$mentee,
				$mentor,
				$mentorRole
			);
		} else {
			$this->jobQueueGroup->lazyPush( new SetUserMentorDatabaseJob( [
				'menteeId' => $mentee->getId(),
				'mentorId' => $mentor ? $mentor->getId() : null,
				'roleId' => $mentorRole,
			] ) );
		}
	}

	/**
	 * Set gemm_mentee_is_active to true/false
	 *
	 * @param UserIdentity $mentee
	 * @param bool $isActive
	 */
	private function setMenteeActiveFlag(
		UserIdentity $mentee,
		bool $isActive
	): void {
		if ( !$this->useIsActive ) {
			$this->logger->error(
				__METHOD__ . ' was called with GEMentorshipUseIsActiveFlag=false',
				[
					'menteeName' => $mentee->getName(),
					'newIsActive' => $isActive,
					'impact' => 'Attempt to change the gemm_mentee_is_active flag was ignored'
				]
			);
			return;
		}

		$this->dbw->update(
			'growthexperiments_mentor_mentee',
			[ 'gemm_mentee_is_active' => $isActive ],
			[
				'gemm_mentee_id' => $mentee->getId(),
				'gemm_mentor_role' => self::ROLE_PRIMARY,
			],
			__METHOD__
		);
		$this->invalidateIsMenteeActive( $mentee );
	}

	/**
	 * @inheritDoc
	 */
	protected function isMenteeActiveUncached( UserIdentity $mentee ): ?bool {
		if ( !$this->useIsActive ) {
			throw new LogicException(
				'MentorStore::isMenteeActive was called, but GEMentorshipUseIsActiveFlag is false'
			);
		}
		if ( !$this->isMentee( $mentee ) ) {
			return null;
		}

		return (bool)$this->dbr->newSelectQueryBuilder()
			->select( 'gemm_mentee_is_active' )
			->from( 'growthexperiments_mentor_mentee' )
			->where( [
				'gemm_mentee_id' => $mentee->getId(),
				'gemm_mentor_role' => self::ROLE_PRIMARY,
			] )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * @inheritDoc
	 */
	public function markMenteeAsActive( UserIdentity $mentee ): void {
		if ( $this->isMentee( $mentee ) && !$this->isMenteeActive( $mentee ) ) {
			$this->setMenteeActiveFlag( $mentee, true );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function markMenteeAsInactive( UserIdentity $mentee ): void {
		if ( $this->isMentee( $mentee ) && $this->isMenteeActive( $mentee ) ) {
			$this->setMenteeActiveFlag( $mentee, false );
		}
	}
}
