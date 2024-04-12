<?php

namespace GrowthExperiments\Mentorship\Store;

use IDBAccessObject;
use JobQueueGroup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use WANObjectCache;
use Wikimedia\Rdbms\ILoadBalancer;

class DatabaseMentorStore extends MentorStore {

	private UserFactory $userFactory;
	private UserIdentityLookup $userIdentityLookup;
	private JobQueueGroup $jobQueueGroup;
	private ILoadBalancer $loadBalancer;

	/**
	 * @param WANObjectCache $wanCache
	 * @param UserFactory $userFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param JobQueueGroup $jobQueueGroup
	 * @param ILoadBalancer $loadBalancer
	 * @param bool $wasPosted
	 */
	public function __construct(
		WANObjectCache $wanCache,
		UserFactory $userFactory,
		UserIdentityLookup $userIdentityLookup,
		JobQueueGroup $jobQueueGroup,
		ILoadBalancer $loadBalancer,
		bool $wasPosted
	) {
		parent::__construct( $wanCache, $wasPosted );

		$this->userFactory = $userFactory;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @inheritDoc
	 */
	public function loadMentorUserUncached(
		UserIdentity $mentee,
		string $mentorRole,
		$flags
	): ?UserIdentity {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) == IDBAccessObject::READ_LATEST ) {
			$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		} else {
			$db = $this->loadBalancer->getConnection( DB_REPLICA );
		}
		$id = $db->newSelectQueryBuilder()
			->select( 'gemm_mentor_id' )
			->from( 'growthexperiments_mentor_mentee' )
			->where( [
				'gemm_mentee_id' => $mentee->getId(),
				'gemm_mentor_role' => $mentorRole,
			] )
			->recency( $flags )
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
		if ( !$user->getId() ) {
			return null;
		}
		return new UserIdentityValue( $user->getId(), $user->getName() );
	}

	/**
	 * @inheritDoc
	 */
	public function getMenteesByMentor(
		UserIdentity $mentor,
		string $mentorRole,
		bool $includeHiddenUsers = false,
		bool $includeInactiveUsers = true,
		int $flags = 0
	): array {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) == IDBAccessObject::READ_LATEST ) {
			$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		} else {
			$db = $this->loadBalancer->getConnection( DB_REPLICA );
		}

		$queryBuilder = $db->newSelectQueryBuilder()
			->select( 'gemm_mentee_id' )
			->from( 'growthexperiments_mentor_mentee' )
			->where( [ 'gemm_mentor_id' => $mentor->getId(), 'gemm_mentor_role' => $mentorRole ] );

		if ( !$includeInactiveUsers ) {
			$queryBuilder->andWhere( [ 'gemm_mentee_is_active' => true ] );
		}

		$ids = $queryBuilder->caller( __METHOD__ )->fetchFieldValues();

		if ( $ids === [] ) {
			return [];
		}

		$builder = $this->userIdentityLookup
			->newSelectQueryBuilder()
			->registered()
			->named()
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
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		if ( $mentor === null ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'growthexperiments_mentor_mentee' )
				->where( [
					'gemm_mentee_id' => $mentee->getId(),
					'gemm_mentor_role' => $mentorRole
				] )
				->caller( __METHOD__ )
				->execute();
			return;
		}
		$dbw->newInsertQueryBuilder()
			->insertInto( 'growthexperiments_mentor_mentee' )
			->row( [
				'gemm_mentee_id' => $mentee->getId(),
				'gemm_mentor_id' => $mentor->getId(),
				'gemm_mentor_role' => $mentorRole,
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'gemm_mentee_id', 'gemm_mentor_role' ] )
			->set( [
				'gemm_mentor_id' => $mentor->getId()
			] )
			->caller( __METHOD__ )
			->execute();
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
		$this->loadBalancer->getConnection( DB_PRIMARY )->update(
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
		if ( !$this->isMentee( $mentee ) ) {
			return null;
		}

		return (bool)$this->loadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder()
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
