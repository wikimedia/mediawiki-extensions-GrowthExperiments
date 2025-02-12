<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use DateInterval;
use DatePeriod;
use DateTime;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Service to look up the conditions for mentee being praiseworthy
 */
class PraiseworthyConditionsLookup {

	private PersonalizedPraiseSettings $settings;
	private UserOptionsLookup $userOptionsLookup;
	private UserFactory $userFactory;
	private IMentorManager $mentorManager;

	public const WAS_PRAISED_PREF = 'growthexperiments-mentorship-was-praised';
	public const SKIPPED_UNTIL_PREF = 'growthexperiments-personalized-praise-skipped-until';

	/** Number of days skipped mentees cannot be suggested for */
	public const SKIP_MENTEES_FOR_DAYS = 10;

	/**
	 * @param PersonalizedPraiseSettings $settings
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param IMentorManager $mentorManager
	 */
	public function __construct(
		PersonalizedPraiseSettings $settings,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		IMentorManager $mentorManager
	) {
		$this->settings = $settings;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->mentorManager = $mentorManager;
	}

	/**
	 * Build a DatePeriod going from today to today minus $days days
	 *
	 * @param int $days
	 * @return DatePeriod
	 */
	private function buildDatePeriod( int $days ): DatePeriod {
		$daysAgoUnix = ( new ConvertibleTimestamp() )->sub( 'P' . $days . 'D' )->getTimestamp( TS_UNIX );
		$tomorrowUnix = ( new ConvertibleTimestamp() )->add( 'P1D' )->getTimestamp( TS_UNIX );
		return new DatePeriod(
			new DateTime( '@' . $daysAgoUnix ),
			new DateInterval( 'P1D' ),
			new DateTime( '@' . $tomorrowUnix )
		);
	}

	/**
	 * Find how many edits the mentee made in a DatePeriod
	 *
	 * @param UserImpact $menteeImpact
	 * @param DatePeriod $datePeriod
	 * @return int
	 */
	private function getEditsInDatePeriod(
		UserImpact $menteeImpact,
		DatePeriod $datePeriod
	): int {
		$editCountByDay = $menteeImpact->getEditCountByDay();

		$res = 0;
		foreach ( $datePeriod as $day ) {
			$res += $editCountByDay[$day->format( 'Y-m-d' )] ?? 0;
		}

		return $res;
	}

	/**
	 * Was the mentee ever praised by their mentor?
	 *
	 * @param UserIdentity $mentee
	 * @return bool
	 */
	private function wasMenteePraised( UserIdentity $mentee ): bool {
		return $this->userOptionsLookup->getBoolOption(
			$mentee,
			self::WAS_PRAISED_PREF
		);
	}

	/**
	 * Is the mentee currently skipped?
	 *
	 * @param UserIdentity $mentee
	 * @return bool
	 */
	private function isMenteeSkipped( UserIdentity $mentee ): bool {
		$skippedUntilRaw = $this->userOptionsLookup->getOption(
			$mentee,
			self::SKIPPED_UNTIL_PREF
		);
		if ( $skippedUntilRaw === null ) {
			return false;
		}

		$noPraiseUntilUnix = ( new ConvertibleTimestamp( $skippedUntilRaw ) )->getTimestamp( TS_UNIX );
		$nowUnix = ( new ConvertibleTimestamp() )->getTimestamp( TS_UNIX );
		return $nowUnix < $noPraiseUntilUnix;
	}

	/**
	 * Is the user able to be praised by a mentor?
	 *
	 * Users can receive praise if ALL of the following conditions are true:
	 * 	* has mentorship module enabled
	 * 	* was never praised in the past
	 * 	* either was not skipped at all or was skipped at least SKIP_MENTEES_FOR_DAYS days ago
	 *
	 * Use this method if you want to know whether an user can theoretically appear in the
	 * Personalized praise module. If you want to know whether they should be included in the
	 * module, call isMenteePraiseworthyForMentor instead.
	 *
	 * @param UserIdentity $mentee
	 * @return bool
	 */
	public function canUserBePraised( UserIdentity $mentee ): bool {
		// NOTE: This does not use HomepageHooks::isHomepageEnabled, because it accesses the global
		// state. Personalized praise is not directly tied to Homepage, so relying on the
		// preference value alone is sufficient.

		$menteeUser = $this->userFactory->newFromUserIdentity( $mentee );
		return $this->userOptionsLookup->getBoolOption( $mentee, HomepageHooks::HOMEPAGE_PREF_ENABLE ) &&
			$this->mentorManager->getMentorshipStateForUser( $mentee ) === IMentorManager::MENTORSHIP_ENABLED &&
			$menteeUser->isNamed() &&
			$menteeUser->getBlock() === null &&
			!$this->wasMenteePraised( $mentee ) &&
			!$this->isMenteeSkipped( $mentee );
	}

	/**
	 * Is the mentee praiseworthy for a given mentor?
	 *
	 * @param UserImpact $menteeImpact
	 * @param UserIdentity $mentor
	 * @return bool
	 */
	public function isMenteePraiseworthyForMentor(
		UserImpact $menteeImpact,
		UserIdentity $mentor
	): bool {
		if ( !$this->canUserBePraised( $menteeImpact->getUser() ) ) {
			return false;
		}

		$conditions = $this->settings->getPraiseworthyConditions( $mentor );

		if ( $menteeImpact->getTotalEditsCount() >= $conditions->getMaxEdits() ) {
			return false;
		}
		if (
			$conditions->getMaxReverts() !== null &&
			$menteeImpact->getRevertedEditCount() > $conditions->getMaxReverts() ) {
			return false;
		}

		$datePeriod = $this->buildDatePeriod( $conditions->getDays() );
		return $this->getEditsInDatePeriod( $menteeImpact, $datePeriod ) >= $conditions->getMinEdits();
	}
}
