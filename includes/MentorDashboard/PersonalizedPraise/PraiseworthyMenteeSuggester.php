<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use BagOStuff;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use GrowthExperiments\UserImpact\UserImpact;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareTrait;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ScopedCallback;

class PraiseworthyMenteeSuggester {
	use LoggerAwareTrait;

	private const EXPIRATION_TTL = ExpirationAwareness::TTL_DAY;

	private BagOStuff $globalCache;
	private PraiseworthyConditionsLookup $praiseworthyConditionsLookup;
	private PersonalizedPraiseNotificationsDispatcher $notificationsDispatcher;
	private MentorStore $mentorStore;
	private UserImpactLookup $userImpactLookup;

	/**
	 * @param BagOStuff $globalCache
	 * @param PraiseworthyConditionsLookup $praiseworthyConditionsLookup
	 * @param PersonalizedPraiseNotificationsDispatcher $notificationsDispatcher
	 * @param MentorStore $mentorStore
	 * @param UserImpactLookup $userImpactLookup
	 */
	public function __construct(
		BagOStuff $globalCache,
		PraiseworthyConditionsLookup $praiseworthyConditionsLookup,
		PersonalizedPraiseNotificationsDispatcher $notificationsDispatcher,
		MentorStore $mentorStore,
		UserImpactLookup $userImpactLookup
	) {
		$this->globalCache = $globalCache;
		$this->praiseworthyConditionsLookup = $praiseworthyConditionsLookup;
		$this->notificationsDispatcher = $notificationsDispatcher;
		$this->mentorStore = $mentorStore;
		$this->userImpactLookup = $userImpactLookup;
	}

	/**
	 * Get array of user impacts for all active mentees assigned to $mentor
	 *
	 * @param UserIdentity $mentor
	 * @return UserImpact[]
	 */
	private function getUserImpactsForActiveMentees( UserIdentity $mentor ): array {
		$mentees = $this->mentorStore->getMenteesByMentor(
			$mentor, MentorStore::ROLE_PRIMARY,
			false, false
		);

		if ( $this->userImpactLookup instanceof DatabaseUserImpactStore ) {
			$userIds = array_map( static function ( UserIdentity $mentee ) {
				return $mentee->getId();
			}, $mentees );
			return $this->userImpactLookup->batchGetUserImpact( $userIds );
		} else {
			// There is no batching in other implementations than DatabaseUserImpactStore; fetch
			// user impacts one by one.
			$this->logger->error(
				__METHOD__ . ' does not have DatabaseUserImpactStore injected, possible ' .
				'performance impact.'
			);
			return array_map( function ( UserIdentity $mentee ) {
				return $this->userImpactLookup->getUserImpact( $mentee );
			}, $mentees );
		}
	}

	/**
	 * Get list of praiseworthy mentees with no caching
	 *
	 * This will iterate through all recently active mentees assigned to the
	 * mentor in question.
	 *
	 * @param UserIdentity $mentor
	 * @return UserImpact[]
	 */
	public function getPraiseworthyMenteesForMentorUncached( UserIdentity $mentor ): array {
		$impacts = $this->getUserImpactsForActiveMentees( $mentor );
		return array_filter( $impacts, function ( ?UserImpact $impact ) use ( $mentor ) {
			if ( $impact === null ) {
				return false;
			}

			return $this->praiseworthyConditionsLookup->isMenteePraiseworthyForMentor(
				$impact, $mentor
			);
		} );
	}

	/**
	 * @param UserIdentity $mentor
	 * @return string
	 */
	private function makeCacheKeyForMentor( UserIdentity $mentor ): string {
		return $this->globalCache->makeKey(
			'GrowthExperiments', 'PraiseworthyMenteeSuggester',
			UserImpact::VERSION, 'getPraiseworthyMentees', $mentor->getId()
		);
	}

	/**
	 * Acquire a lock via BagOStuff
	 *
	 * If a lock cannot be acquired, an error is logged.
	 *
	 * @param string $key
	 * @param string $caller
	 * @return ScopedCallback|null
	 * @see BagOStuff::getScopedLock()
	 */
	private function getScopedLock( string $key, string $caller ): ?ScopedCallback {
		$lock = $this->globalCache->getScopedLock( $key );
		if ( !$lock ) {
			$this->logger->error(
				$caller . ' failed to acquire a lock for cache of praiseworthy mentees'
			);
		}
		return $lock;
	}

	/**
	 * Refresh the mentor's cache of their praiseworthy mentees
	 *
	 * @param UserIdentity $mentor
	 */
	public function refreshPraiseworthyMenteesForMentor( UserIdentity $mentor ): void {
		$key = $this->makeCacheKeyForMentor( $mentor );
		$lock = $this->getScopedLock( $key, __METHOD__ );
		if ( !$lock ) {
			return;
		}

		$this->globalCache->set(
			$key,
			$this->getPraiseworthyMenteesForMentorUncached( $mentor ),
			self::EXPIRATION_TTL
		);
		$this->notificationsDispatcher->maybeNotifyAboutPendingMentees( $mentor );
	}

	/**
	 * Get cached list of praiseworthy mentees for mentor
	 *
	 * @param UserIdentity $mentor
	 * @return UserImpact[] ID => UserImpact mapping
	 */
	public function getPraiseworthyMenteesForMentor( UserIdentity $mentor ): array {
		$key = $this->makeCacheKeyForMentor( $mentor );
		$lock = $this->getScopedLock( $key, __METHOD__ );
		if ( !$lock ) {
			return [];
		}

		$res = $this->globalCache->get( $key );
		if ( !$res ) {
			return [];
		}
		return $res;
	}

	/**
	 * @param UserIdentity $mentee
	 * @param UserIdentity $mentor
	 * @return bool
	 */
	public function isMenteeMarkedAsPraiseworthy( UserIdentity $mentee, UserIdentity $mentor ): bool {
		$praiseworthyIds = array_keys( $this->getPraiseworthyMenteesForMentor( $mentor ) );
		return in_array( $mentee->getId(), $praiseworthyIds );
	}

	/**
	 * Mark a mentee as praiseworthy
	 *
	 * Caller is responsible for checking whether mentee is praiseworthy or not;
	 * this can be done by calling PraiseworthyConditionsLookup::isMenteePraiseworthyForMentor.
	 *
	 * @param UserImpact $menteeImpact
	 * @param UserIdentity $mentorUser
	 */
	public function markMenteeAsPraiseworthy(
		UserImpact $menteeImpact,
		UserIdentity $mentorUser
	): void {
		if ( $this->isMenteeMarkedAsPraiseworthy( $menteeImpact->getUser(), $mentorUser ) ) {
			// already done
			return;
		}

		$key = $this->makeCacheKeyForMentor( $mentorUser );
		$lock = $this->getScopedLock( $key, __METHOD__ );
		if ( !$lock ) {
			return;
		}

		$data = $this->globalCache->get( $key );
		if ( !$data ) {
			$data = [];
		}
		$data[$menteeImpact->getUser()->getId()] = $menteeImpact;
		$this->globalCache->set( $key, $data, self::EXPIRATION_TTL );

		$this->notificationsDispatcher->onMenteeSuggested( $mentorUser, $menteeImpact->getUser() );
	}
}
