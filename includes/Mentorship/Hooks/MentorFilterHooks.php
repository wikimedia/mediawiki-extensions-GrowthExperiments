<?php

namespace GrowthExperiments\Mentorship\Hooks;

use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\RecentChanges\ChangesListStringOptionsFilter;
use MediaWiki\RecentChanges\ChangesListStringOptionsFilterGroup;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\SpecialPage\Hook\ChangesListSpecialPageStructuredFiltersHook;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Rdbms\IDatabase;

/**
 * RecentChanges filters for mentors. Separate from MentorHooks because MentorManager
 * depends on the session so making more common hooks depend on it would break ResourceLoader.
 */
class MentorFilterHooks implements ChangesListSpecialPageStructuredFiltersHook {

	private Config $config;
	private MentorStore $mentorStore;
	private StarredMenteesStore $starredMenteesStore;
	private MentorProvider $mentorProvider;

	/** @var HashBagOStuff Mentor [starred|unstarred]:username => UserIdentity[] list of mentees */
	private HashBagOStuff $menteeCache;

	public function __construct(
		Config $config,
		MentorStore $mentorStore,
		StarredMenteesStore $starredMenteesStore,
		MentorProvider $mentorProvider
	) {
		$this->config = $config;
		$this->mentorStore = $mentorStore;
		$this->starredMenteesStore = $starredMenteesStore;
		$this->mentorProvider = $mentorProvider;
		$this->menteeCache = new HashBagOStuff();
	}

	/** @inheritDoc */
	public function onChangesListSpecialPageStructuredFilters( $special ) {
		// Make sure the user is actually a mentor.
		try {
			if ( !$this->mentorProvider->isMentor( $special->getUser() ) ) {
				return;
			}
		} catch ( WikiConfigException $wikiConfigException ) {
			return;
		}

		$group = new ChangesListStringOptionsFilterGroup( [
			'name' => 'mentorship',
			'title' => 'growthexperiments-rcfilters-mentorship-title',
			'isFullCoverage' => false,
			'filters' => [],
			'default' => '',
			'queryCallable' => function (
				string $specialPageClassName,
				IContextSource $context,
				IDatabase $dbr,
				array &$tables,
				array &$fields,
				array &$conds,
				array &$query_options,
				array &$join_conds,
				array $selectedValues
			) {
				if ( !$selectedValues ) {
					return;
				}

				$targetUserIds = [];
				if ( in_array( 'starred', $selectedValues, true ) ) {
					$targetUserIds = $this->getStarredMenteeIds( $context->getUser() );
				}
				if ( in_array( 'unstarred', $selectedValues, true ) ) {
					$targetUserIds = array_merge( $targetUserIds, $this->getUnstarredMenteeIds( $context->getUser() ) );
				}
				$targetActorIds = $this->convertUserIdsToActorIds( $dbr, $targetUserIds );

				// The query is shared with other hook handlers, so with the associative array format
				// there is a risk of key conflict. Convert into non-associate instead.
				// Only apply when $targetIds has at least one ID
				if ( $targetActorIds !== [] ) {
					$conds['rc_actor'] = $targetActorIds;
				} else {
					$conds[] = '0=1';
				}
			},
		] );
		$special->registerFilterGroup( $group );

		$starredMenteesFilter = new ChangesListStringOptionsFilter( [
			'name' => 'starred',
			'group' => $group,
			'label' => 'growthexperiments-rcfilters-mentorship-starred-label',
			'description' => 'growthexperiments-rcfilters-mentorship-starred-desc',
			'priority' => 0,
			'cssClassSuffix' => 'starred-mentee',
			'isRowApplicableCallable' => function ( IContextSource $context, RecentChange $rc ) {
				$starredMenteeIds = $this->getStarredMenteeIds( $context->getUser() );
				return in_array( $rc->getPerformerIdentity()->getId(), $starredMenteeIds, true );
			},
		] );
		$unstarredMenteesFilter = new ChangesListStringOptionsFilter( [
			'name' => 'unstarred',
			'group' => $group,
			'label' => 'growthexperiments-rcfilters-mentorship-unstarred-label',
			'description' => 'growthexperiments-rcfilters-mentorship-unstarred-desc',
			'priority' => -1,
			'cssClassSuffix' => 'unstarred-mentee',
			'isRowApplicableCallable' => function ( IContextSource $context, RecentChange $rc ) {
				$unstarredMenteeIds = $this->getUnstarredMenteeIds( $context->getUser() );
				return in_array( $rc->getPerformerIdentity()->getId(), $unstarredMenteeIds, true );
			},
		] );
	}

	/**
	 * Helper method to load the current user's starred mentees, with caching.
	 * @param UserIdentity $user
	 * @return int[]
	 */
	private function getStarredMenteeIds( UserIdentity $user ): array {
		$key = $this->menteeCache->makeKey( 'starred', $user->getId() );
		if ( $this->menteeCache->hasKey( $key ) ) {
			return $this->menteeCache->get( $key );
		}

		$starredMentees = $this->starredMenteesStore->getStarredMentees( $user );
		$starredMenteeIds = array_map( static function ( UserIdentity $user ) {
			return $user->getId();
		}, $starredMentees );

		$this->menteeCache->set( $key, $starredMenteeIds );
		return $starredMenteeIds;
	}

	/**
	 * Helper method to load the current user's unstarred mentees, with caching.
	 * @param UserIdentity $user
	 * @return int[]
	 */
	private function getUnstarredMenteeIds( UserIdentity $user ): array {
		$key = $this->menteeCache->makeKey( 'unstarred', $user->getId() );
		if ( $this->menteeCache->hasKey( $key ) ) {
			return $this->menteeCache->get( $key );
		}

		$mentees = $this->mentorStore->getMenteesByMentor(
			$user,
			MentorStore::ROLE_PRIMARY,
			false,
			false
		);
		$menteeIds = array_map( static function ( UserIdentity $user ) {
			return $user->getId();
		}, $mentees );
		$starredMenteeIds = $this->getStarredMenteeIds( $user );
		$unstarredMenteeIds = array_diff( $menteeIds, $starredMenteeIds );

		$this->menteeCache->set( $key, $unstarredMenteeIds );
		return $unstarredMenteeIds;
	}

	/**
	 * @param IDatabase $db
	 * @param int[] $userIds
	 * @return int[]
	 */
	private function convertUserIdsToActorIds( IDatabase $db, array $userIds ) {
		if ( !$userIds ) {
			return [];
		}

		// No need to worry about properly acquiring actor IDs - if it shows up in
		// recent changes, it already has an actor ID
		$res = $db->newSelectQueryBuilder()
			->select( 'actor_id' )
			->from( 'actor' )
			->where( [ 'actor_user' => $userIds ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		return array_map( 'intval', $res );
	}

}
