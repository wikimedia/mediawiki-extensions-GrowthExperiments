<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use Config;
use DateInterval;
use DatePeriod;
use DateTime;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Service to look up the conditions for mentee being praiseworthy
 *
 * As of now, this only uses GEPersonalizedPraiseDays and
 * GEPersonalizedPraiseEdits, but in the future, it will
 * make use of mentor's own preferences (see T322446).
 */
class PraiseworthyConditionsLookup {

	private Config $wikiConfig;
	private UserOptionsLookup $userOptionsLookup;
	private MentorManager $mentorManager;

	/** @var string */
	public const WAS_PRAISED_PREF = 'growthexperiments-mentorship-was-praised';

	/**
	 * @param Config $wikiConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		Config $wikiConfig,
		UserOptionsLookup $userOptionsLookup,
		MentorManager $mentorManager
	) {
		$this->wikiConfig = $wikiConfig;
		$this->userOptionsLookup = $userOptionsLookup;
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
	 * Return conditions mentees need to meet to be praiseworthy for a mentor
	 *
	 * @todo Allow mentor to customize the praiseworthy settings (T322446)
	 * @param UserIdentity $mentor
	 * @return int[] Array with 'maxedits', 'minedits' and 'days' as the key
	 */
	private function getPraiseworthyConditionsForMentor( UserIdentity $mentor ) {
		return [
			'maxedits' => (int)$this->wikiConfig->get( 'GEPersonalizedPraiseMaxEdits' ),
			'minedits' => (int)$this->wikiConfig->get( 'GEPersonalizedPraiseEdits' ),
			'days' => (int)$this->wikiConfig->get( 'GEPersonalizedPraiseDays' )
		];
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
	 * Is the user able to be praised by a mentor?
	 *
	 * Users can receive praise if ALL of the following conditions are true:
	 * 	* has mentorship module enabled
	 * 	* was never praised in the past
	 *
	 * Use this method if you want to know whether an user can theoretically appear in the
	 * Personalized praise module. If you want to know whether they should be included in the
	 * module, call isMenteePraiseworthyForMentor instead.
	 *
	 * @param UserIdentity $mentee
	 * @return bool
	 */
	public function canUserBePraised( UserIdentity $mentee ): bool {
		return $this->mentorManager->getMentorshipStateForUser( $mentee ) === MentorManager::MENTORSHIP_ENABLED &&
			!$this->wasMenteePraised( $mentee );
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

		$conditions = $this->getPraiseworthyConditionsForMentor( $mentor );
		$maxEdits = $conditions['maxedits'];
		$minEdits = $conditions['minedits'];
		$daysToConsider = $conditions['days'];

		if ( $menteeImpact->getTotalEditsCount() >= $maxEdits ) {
			return false;
		}

		$datePeriod = $this->buildDatePeriod( $daysToConsider );
		return $this->getEditsInDatePeriod( $menteeImpact, $datePeriod ) >= $minEdits;
	}
}
