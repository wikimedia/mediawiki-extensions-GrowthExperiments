<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use GrowthExperiments\EventLogging\PersonalizedPraiseLogger;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use GrowthExperiments\UserImpact\UserImpact;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ScopedCallback;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class PraiseworthyMenteeSuggester {
	private const EXPIRATION_TTL = ExpirationAwareness::TTL_DAY;

	private LoggerInterface $logger;
	private BagOStuff $globalCache;
	private UserOptionsManager $userOptionsManager;
	private PraiseworthyConditionsLookup $praiseworthyConditionsLookup;
	private PersonalizedPraiseNotificationsDispatcher $notificationsDispatcher;
	private PersonalizedPraiseLogger $eventLogger;
	private MentorStore $mentorStore;
	private UserImpactLookup $userImpactLookup;

	public function __construct(
		LoggerInterface $logger,
		BagOStuff $globalCache,
		UserOptionsManager $userOptionsManager,
		PraiseworthyConditionsLookup $praiseworthyConditionsLookup,
		PersonalizedPraiseNotificationsDispatcher $notificationsDispatcher,
		PersonalizedPraiseLogger $personalizedPraiseLogger,
		MentorStore $mentorStore,
		UserImpactLookup $userImpactLookup
	) {
		$this->logger = $logger;
		$this->globalCache = $globalCache;
		$this->userOptionsManager = $userOptionsManager;
		$this->praiseworthyConditionsLookup = $praiseworthyConditionsLookup;
		$this->notificationsDispatcher = $notificationsDispatcher;
		$this->eventLogger = $personalizedPraiseLogger;
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
	 */
	public function refreshPraiseworthyMenteesForMentor( UserIdentity $mentor ): void {
		$key = $this->makeCacheKeyForMentor( $mentor );
		$lock = $this->getScopedLock( $key, __METHOD__ );
		if ( !$lock ) {
			return;
		}

		$praiseworthyMentees = $this->getPraiseworthyMenteesForMentorUncached( $mentor );
		$this->globalCache->set(
			$key,
			$praiseworthyMentees,
			self::EXPIRATION_TTL
		);

		$wasNotified = $this->notificationsDispatcher->maybeNotifyAboutPendingMentees( $mentor );
		foreach ( $praiseworthyMentees as $impact ) {
			$this->eventLogger->logSuggested( $mentor, $impact->getUser(), $wasNotified );
		}
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

		return $this->globalCache->get( $key ) ?: [];
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

	/**
	 * Remove a mentee from the list of praiseworthy mentees
	 */
	private function removeMenteeFromSuggestions( UserIdentity $mentee ): void {
		$mentor = $this->mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY );
		if ( !$mentor ) {
			return;
		}

		if ( $this->isMenteeMarkedAsPraiseworthy( $mentee, $mentor ) ) {
			$this->globalCache->merge(
				$this->makeCacheKeyForMentor( $mentor ),
				static function ( $cache, $key, $value ) use ( $mentee ) {
					if ( array_key_exists( $mentee->getId(), $value ) ) {
						unset( $value[$mentee->getId()] );
					}
					return $value;
				}
			);
		}
	}

	/**
	 * Mark a mentee as already praised
	 */
	public function markMenteeAsPraised( UserIdentity $mentee ): void {
		$this->userOptionsManager->setOption(
			$mentee,
			PraiseworthyConditionsLookup::WAS_PRAISED_PREF,
			true
		);
		$this->userOptionsManager->saveOptions( $mentee );

		$this->removeMenteeFromSuggestions( $mentee );
	}

	/**
	 * Mark a mentee as skipped
	 *
	 * The mentee will not be re-suggested for PraiseworthyConditionsLookup::SKIP_MENTEES_FOR_DAYS
	 * days.
	 */
	public function markMenteeAsSkipped( UserIdentity $mentee ): void {
		$this->userOptionsManager->setOption(
			$mentee,
			PraiseworthyConditionsLookup::SKIPPED_UNTIL_PREF,
			( new ConvertibleTimestamp() )
				->add( 'P' . PraiseworthyConditionsLookup::SKIP_MENTEES_FOR_DAYS . 'D' )
				->getTimestamp( TS_MW )
		);
		$this->userOptionsManager->saveOptions( $mentee );

		$this->removeMenteeFromSuggestions( $mentee );
	}
}
