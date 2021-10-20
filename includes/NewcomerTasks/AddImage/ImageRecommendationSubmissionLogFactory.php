<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use DateTime;
use IContextSource;
use LogEventsList;
use LogPager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserTimeCorrection;

class ImageRecommendationSubmissionLogFactory {

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( UserOptionsLookup $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @param UserIdentity $user
	 * @param IContextSource $context
	 * @return ImageRecommendationSubmissionLog
	 */
	public function newImageRecommendationSubmissionLog(
		UserIdentity $user, IContextSource $context
	): ImageRecommendationSubmissionLog {
		// We want to know if the user made image suggestion edits during the current day for that user.
		// The log_timestamp is saved in the database using UTC timezone, so we need to get the
		// current day for the user, get a timestamp for the local midnight for that date, then
		// get the UNIX timestamp and convert it to MW format for use in the query.
		$userTimeCorrection = new UserTimeCorrection(
			$this->userOptionsLookup->getOption( $user, 'timecorrection' )
		);
		$localMidnight = new DateTime( 'T00:00', $userTimeCorrection->getTimeZone() );
		$utcTimestamp = \MWTimestamp::convert( TS_MW, $localMidnight->getTimestamp() );

		return new ImageRecommendationSubmissionLog( new LogPager(
			new LogEventsList( $context ),
			[ 'growthexperiments' ],
			$user->getName(),
			'',
			false,
			[ "log_timestamp>$utcTimestamp" ],
			false,
			false,
			false,
			'',
			'addimage'
		) );
	}
}
