<?php

namespace GrowthExperiments\Mentorship\Hooks;

use ChangesListStringOptionsFilter;
use ChangesListStringOptionsFilterGroup;
use Config;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use HashBagOStuff;
use IContextSource;
use MediaWiki\SpecialPage\Hook\ChangesListSpecialPageStructuredFiltersHook;
use MediaWiki\User\UserIdentity;
use RecentChange;
use Wikimedia\Rdbms\IDatabase;

/**
 * RecentChanges filters for mentors. Separate from MentorHooks because MentorManager
 * depends on the session so making more common hooks depend on it would break ResourceLoader.
 */
class MentorFilterHooks implements ChangesListSpecialPageStructuredFiltersHook {

	/** @var Config */
	private $config;

	/** @var MentorStore */
	private $mentorStore;

	/** @var StarredMenteesStore */
	private $starredMenteesStore;

	/** @var MentorManager */
	private $mentorManager;

	/** @var HashBagOStuff Mentor [starred|unstarred]:username => UserIdentity[] list of mentees */
	private $menteeCache;

	/**
	 * @param Config $config
	 * @param MentorStore $mentorStore
	 * @param StarredMenteesStore $starredMenteesStore
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		Config $config,
		MentorStore $mentorStore,
		StarredMenteesStore $starredMenteesStore,
		MentorManager $mentorManager
	) {
		$this->config = $config;
		$this->mentorStore = $mentorStore;
		$this->starredMenteesStore = $starredMenteesStore;
		$this->mentorManager = $mentorManager;
		$this->menteeCache = new HashBagOStuff();
	}

	/** @inheritDoc */
	public function onChangesListSpecialPageStructuredFilters( $special ) {
		// Somewhat arbitrarily, use the dashboard feature flag to expose the mentor filters.
		// Also make sure the user is actually a mentor.
		try {
			if ( !$this->config->get( 'GEMentorDashboardEnabled' )
				 || !$this->mentorManager->isMentor( $special->getUser() )
			) {
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
				$targetIds = [];
				if ( in_array( 'starred', $selectedValues, true ) ) {
					$targetIds = $this->getStarredMenteeIds( $context->getUser() );
				}
				if (
					$this->config->get( 'GERecentChangesUnstarredMenteesFilterEnabled' ) &&
					in_array( 'unstarred', $selectedValues, true )
				) {
					$targetIds = array_merge( $targetIds, $this->getUnstarredMenteeIds( $context->getUser() ) );
				}
				// Un-alias the rc_user field, aliases do not work in WHERE.
				$rcUserField = RecentChange::getQueryInfo()['fields']['rc_user'];
				// The query is shared with other hook handlers, so with the associative array format
				// there is a risk of key conflict. Convert into non-associate instead.
				// Only apply when $targetIds has at least one ID
				if ( $targetIds !== [] ) {
					$conds[] = $dbr->makeList( [ $rcUserField => $targetIds ], IDatabase::LIST_AND );
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

		if ( $this->config->get( 'GERecentChangesUnstarredMenteesFilterEnabled' ) ) {
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
			MentorStore::ROLE_PRIMARY
		);
		$menteeIds = array_map( static function ( UserIdentity $user ) {
			return $user->getId();
		}, $mentees );
		$starredMenteeIds = $this->getStarredMenteeIds( $user );
		$unstarredMenteeIds = array_diff( $menteeIds, $starredMenteeIds );

		$this->menteeCache->set( $key, $unstarredMenteeIds );
		return $unstarredMenteeIds;
	}

}
