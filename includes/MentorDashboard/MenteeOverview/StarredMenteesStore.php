<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use IDBAccessObject;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;

class StarredMenteesStore {
	public const STARRED_MENTEES_PREFERENCE = 'growthexperiments-starred-mentees';

	private const SEPARATOR = '|';

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		UserOptionsManager $userOptionsManager
	) {
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
	 * @param int $flags Bitarray, one of IDBAccessObject::READ_*
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
			->caller( __METHOD__ )
			->fetchUserIdentities() );
	}

	/**
	 * @param UserIdentity $user
	 * @param int $flags Bitarray, one of IDBAccessObject::READ_* constants
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
		$starredMentees = $this->getStarredMenteeIds( $mentor, IDBAccessObject::READ_LOCKING );
		$menteeId = $mentee->getId();

		if ( in_array( $menteeId, $starredMentees ) ) {
			// $mentee is already starred
			return;
		}

		// Update the user option
		$starredMentees[] = $mentee->getId();
		$this->userOptionsManager->setOption(
			$mentor,
			self::STARRED_MENTEES_PREFERENCE,
			$this->encodeMenteeIds( $starredMentees )
		);
		$this->userOptionsManager->saveOptions( $mentor );
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 */
	public function unstarMentee( UserIdentity $mentor, UserIdentity $mentee ): void {
		$starredMentees = $this->getStarredMenteeIds( $mentor, IDBAccessObject::READ_LOCKING );
		$menteeId = $mentee->getId();

		// Delete $menteeId from $starredMentees, if it is there
		$key = array_search( $menteeId, $starredMentees );
		if ( $key !== false ) {
			unset( $starredMentees[$key] );
			$starredMentees = array_values( $starredMentees );

			// $starredMentees was changed, update option
			$this->userOptionsManager->setOption(
				$mentor,
				self::STARRED_MENTEES_PREFERENCE,
				$this->encodeMenteeIds( $starredMentees )
			);
			$this->userOptionsManager->saveOptions( $mentor );
		}
	}
}
