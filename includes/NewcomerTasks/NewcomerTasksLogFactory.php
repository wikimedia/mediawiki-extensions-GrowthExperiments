<?php

namespace GrowthExperiments\NewcomerTasks;

use DateTime;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserTimeCorrection;
use MWTimestamp;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class NewcomerTasksLogFactory {

	private IReadableDatabase $dbr;
	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @param IReadableDatabase $dbr
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( IReadableDatabase $dbr, UserOptionsLookup $userOptionsLookup ) {
		$this->dbr = $dbr;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	protected function getQueryBuilder( UserIdentity $user, string $logAction ): SelectQueryBuilder {
		// We want to know if the user made edits during the current day for that user.
		// The log_timestamp is saved in the database using UTC timezone, so we need to get the
		// current day for the user, get a timestamp for the local midnight for that date, then
		// get the UNIX timestamp and convert it to MW format for use in the query.
		$userTimeCorrection = new UserTimeCorrection(
			$this->userOptionsLookup->getOption( $user, 'timecorrection' )
		);
		$localMidnight = new DateTime( 'T00:00', $userTimeCorrection->getTimeZone() );
		$utcTimestamp = MWTimestamp::convert( TS_MW, $localMidnight->getTimestamp() );

		return $this->dbr->newSelectQueryBuilder()
			->select( [ 'log_action' ] )
			->from( 'logging' )
			->where( [
				'log_type' => 'growthexperiments',
				'log_action' => $logAction,
				'actor_name' => Title::makeTitle( NS_USER, $user->getName() )->getDbKey(),
				$this->dbr->buildComparison( '>', [ 'log_timestamp' => $utcTimestamp ] )
			] )
			->join( 'actor', null, 'log_actor=actor_id' );
	}
}
