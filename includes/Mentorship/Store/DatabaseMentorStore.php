<?php

namespace GrowthExperiments\Mentorship\Store;

use DBAccessObjectUtils;
use JobQueueGroup;
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

	/**
	 * @param WANObjectCache $wanCache
	 * @param UserFactory $userFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param JobQueueGroup $jobQueueGroup
	 * @param IDatabase $dbr
	 * @param IDatabase $dbw
	 * @param bool $wasPosted
	 */
	public function __construct(
		WANObjectCache $wanCache,
		UserFactory $userFactory,
		UserIdentityLookup $userIdentityLookup,
		JobQueueGroup $jobQueueGroup,
		IDatabase $dbr,
		IDatabase $dbw,
		bool $wasPosted
	) {
		parent::__construct( $wanCache, $wasPosted );

		$this->userFactory = $userFactory;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->dbr = $dbr;
		$this->dbw = $dbw;
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
}
