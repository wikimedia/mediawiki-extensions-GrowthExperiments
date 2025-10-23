<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use GrowthExperiments\EventLogging\PersonalizedPraiseLogger;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\ObjectCache\BagOStuff;

class PersonalizedPraiseNotificationsDispatcher {

	private Config $config;
	private BagOStuff $cache;
	private SpecialPageFactory $specialPageFactory;
	private PersonalizedPraiseSettings $personalizedPraiseSettings;
	private PersonalizedPraiseLogger $eventLogger;

	/**
	 * @param Config $config
	 * @param BagOStuff $cache
	 * @param SpecialPageFactory $specialPageFactory
	 * @param PersonalizedPraiseSettings $personalizedPraiseSettings
	 * @param PersonalizedPraiseLogger $personalizedPraiseLogger
	 */
	public function __construct(
		Config $config,
		BagOStuff $cache,
		SpecialPageFactory $specialPageFactory,
		PersonalizedPraiseSettings $personalizedPraiseSettings,
		PersonalizedPraiseLogger $personalizedPraiseLogger
	) {
		$this->config = $config;
		$this->cache = $cache;
		$this->specialPageFactory = $specialPageFactory;
		$this->personalizedPraiseSettings = $personalizedPraiseSettings;
		$this->eventLogger = $personalizedPraiseLogger;
	}

	private function makeLastNotifiedKey( UserIdentity $mentor ): string {
		return $this->cache->makeKey(
			'growthexperiments-mentor-last-notified',
			$mentor->getId()
		);
	}

	/**
	 * If available, get last notified timestamp
	 *
	 * @param UserIdentity $userIdentity
	 * @return string|null
	 */
	private function getLastNotified( UserIdentity $userIdentity ): ?string {
		$res = $this->cache->get( $this->makeLastNotifiedKey( $userIdentity ) );
		if ( !is_string( $res ) ) {
			return null;
		}

		return $res;
	}

	/**
	 * Set last notified timestamp
	 *
	 * @param UserIdentity $userIdentity
	 * @param string $lastNotified
	 */
	private function setLastNotified( UserIdentity $userIdentity, string $lastNotified ): void {
		$this->cache->set(
			$this->makeLastNotifiedKey( $userIdentity ),
			$lastNotified,
			BagOStuff::TTL_INDEFINITE
		);
	}

	private function makePendingMenteesKey( UserIdentity $mentor ): string {
		return $this->cache->makeKey(
			'growthexperiments-mentor-pending-mentees',
			$mentor->getId()
		);
	}

	/**
	 * Are there any mentees the mentor was not yet notified about?
	 *
	 * @param UserIdentity $mentor
	 * @return bool
	 */
	private function doesMentorHavePendingMentees( UserIdentity $mentor ): bool {
		$res = $this->cache->get( $this->makePendingMenteesKey( $mentor ) );
		return is_array( $res ) && $res;
	}

	/**
	 * Purge the list of mentees the mentor was not yet notified about
	 *
	 * This should be called upon mentor getting a notification.
	 */
	private function purgePendingMenteesForMentor( UserIdentity $mentor ): void {
		$this->cache->delete( $this->makePendingMenteesKey( $mentor ) );
	}

	private function markMenteeAsPendingForMentor(
		UserIdentity $mentor, UserIdentity $mentee
	): void {
		$this->cache->merge(
			$this->makePendingMenteesKey( $mentor ),
			static function ( $cache, $key, $value ) use ( $mentee ) {
				if ( !is_array( $value ) ) {
					$value = [];
				}
				$value[] = $mentee->getId();
				return array_unique( $value );
			}
		);
	}

	/**
	 * Notify a mentor about new praiseworthy mentees
	 */
	private function notifyMentor( UserIdentity $mentor ) {
		if ( !$this->config->get( 'GEPersonalizedPraiseNotificationsEnabled' ) ) {
			return;
		}

		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			// Event::create accesses global state, avoid calling it when running the test
			// suite.
			Event::create( [
				'type' => 'new-praiseworthy-mentees',
				'title' => $this->specialPageFactory->getTitleForAlias( 'MentorDashboard' ),
				'agent' => $mentor,
			] );
		}

		$this->eventLogger->logNotified( $mentor );
		$this->setLastNotified( $mentor, MWTimestamp::getInstance()->getTimestamp( TS_MW ) );
		$this->purgePendingMenteesForMentor( $mentor );
	}

	/**
	 * Called whenever GrowthExperiments suggests a new mentee to praise
	 *
	 * @param UserIdentity $mentor Mentor to whom the suggestion was made
	 * @param UserIdentity $mentee Mentee which was suggested
	 */
	public function onMenteeSuggested( UserIdentity $mentor, UserIdentity $mentee ): void {
		$freq = $this->personalizedPraiseSettings->getNotificationsFrequency( $mentor );
		if ( $freq === PersonalizedPraiseSettings::NOTIFY_IMMEDIATELY ) {
			$this->notifyMentor( $mentor );
		} elseif ( $freq !== PersonalizedPraiseSettings::NOTIFY_NEVER ) {
			$this->markMenteeAsPendingForMentor( $mentor, $mentee );
		}
	}

	/**
	 * If mentor has any mentees they were not yet notified about, notify them
	 *
	 * @param UserIdentity $mentor
	 * @return bool Was the mentor notified?
	 */
	public function maybeNotifyAboutPendingMentees( UserIdentity $mentor ): bool {
		if ( !$this->doesMentorHavePendingMentees( $mentor ) ) {
			return false;
		}

		$hoursToWait = $this->personalizedPraiseSettings->getNotificationsFrequency( $mentor );
		if ( $hoursToWait === PersonalizedPraiseSettings::NOTIFY_IMMEDIATELY ) {
			// NOTE: immediate notification is normally handled in onMenteeSuggested; only
			// process pending mentees that were not yet processed.
			$this->notifyMentor( $mentor );
			return true;
		} elseif ( $hoursToWait === PersonalizedPraiseSettings::NOTIFY_NEVER ) {
			return false;
		}

		$rawLastNotifiedTS = $this->getLastNotified( $mentor );
		$notifiedSecondsAgo = (int)MWTimestamp::getInstance()->getTimestamp( TS_UNIX ) -
			(int)MWTimestamp::getInstance( $rawLastNotifiedTS ?? false )->getTimestamp( TS_UNIX );

		if (
			$rawLastNotifiedTS === null ||
			$notifiedSecondsAgo >= $hoursToWait * BagOStuff::TTL_HOUR
		) {
			$this->notifyMentor( $mentor );
			return true;
		}
		return false;
	}
}
