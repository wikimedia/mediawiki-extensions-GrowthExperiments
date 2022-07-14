<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use IDBAccessObject;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserOptionsManager;

class StarredMenteesStore implements IDBAccessObject {
	public const STARRED_MENTEES_PREFERENCE = 'growthexperiments-starred-mentees';

	private const SEPARATOR = '|';

	/** @var UserFactory */
	private $userFactory;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param UserFactory $userFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		UserFactory $userFactory,
		UserIdentityLookup $userIdentityLookup,
		UserOptionsManager $userOptionsManager
	) {
		$this->userFactory = $userFactory;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userOptionsManager = $userOptionsManager;
	}

	private function encodeMenteeIds( array $ids ): string {
		return implode( self::SEPARATOR, $ids );
	}

	private function decodeMenteeIds( string $encodedIds ): array {
		$res = explode( self::SEPARATOR, $encodedIds );
		return array_map( 'intval', array_filter( $res, 'is_numeric' ) );
	}

	/**
	 * @param UserIdentity $user
	 * @param int $flags Bitarray, one of StarredMenteesStore::READ_*
	 * @return UserIdentity[]
	 */
	public function getStarredMentees( UserIdentity $user, int $flags = 0 ): array {
		$ids = $this->getStarredMenteeIds( $user, $flags );
		if ( $ids === [] ) {
			// UserIdentityLookup will throw if $ids is empty
			return [];
		}

		return iterator_to_array( $this->userIdentityLookup
			->newSelectQueryBuilder()
			->whereUserIds( $ids )
			->fetchUserIdentities() );
	}

	/**
	 * @param UserIdentity $user
	 * @param int $flags Bitarray, one of StarredMenteesStore::READ_* constants
	 * @return int[]
	 */
	private function getStarredMenteeIds( UserIdentity $user, int $flags = 0 ): array {
		return $this->decodeMenteeIds(
			$this->userOptionsManager
				->getOption(
					$user,
					self::STARRED_MENTEES_PREFERENCE,
					null,
					false,
					$flags
				)
		);
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 */
	public function starMentee( UserIdentity $mentor, UserIdentity $mentee ): void {
		$starredMentees = $this->getStarredMenteeIds( $mentor, self::READ_LOCKING );
		$menteeId = $mentee->getId();

		if ( in_array( $menteeId, $starredMentees ) ) {
			// $mentee is already starred
			return;
		}

		// Update the user option
		$starredMentees[] = $mentee->getId();
		$mentorUser = $this->userFactory->newFromUserIdentity( $mentor );
		$this->userOptionsManager->setOption(
			$mentorUser,
			self::STARRED_MENTEES_PREFERENCE,
			$this->encodeMenteeIds( $starredMentees )
		);
		$mentorUser->saveSettings();
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 */
	public function unstarMentee( UserIdentity $mentor, UserIdentity $mentee ): void {
		$starredMentees = $this->getStarredMenteeIds( $mentor, self::READ_LOCKING );
		$menteeId = $mentee->getId();

		// Delete $menteeId from $starredMentees, if it is there
		$key = array_search( $menteeId, $starredMentees );
		if ( $key !== false ) {
			unset( $starredMentees[$key] );
			$starredMentees = array_values( $starredMentees );

			// $starredMentees was changed, update option
			$mentorUser = $this->userFactory->newFromUserIdentity( $mentor );
			$this->userOptionsManager->setOption(
				$mentorUser,
				self::STARRED_MENTEES_PREFERENCE,
				$this->encodeMenteeIds( $starredMentees )
			);
			$mentorUser->saveSettings();
		}
	}
}
