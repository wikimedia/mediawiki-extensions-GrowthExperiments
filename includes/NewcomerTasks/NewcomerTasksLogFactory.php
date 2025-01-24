<?php

namespace GrowthExperiments\NewcomerTasks;

use DateTime;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserTimeCorrection;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

class NewcomerTasksLogFactory {

	private IConnectionProvider $connectionProvider;
	private UserOptionsLookup $userOptionsLookup;

	public function __construct( IConnectionProvider $connectionProvider, UserOptionsLookup $userOptionsLookup ) {
		$this->connectionProvider = $connectionProvider;
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

		$dbr = $this->connectionProvider->getReplicaDatabase();
		return $dbr->newSelectQueryBuilder()
			->select( [ 'log_action' ] )
			->from( 'logging' )
			->where( [
				'log_type' => 'growthexperiments',
				'log_action' => $logAction,
				'actor_name' => $user->getName(),
				$dbr->buildComparison( '>', [ 'log_timestamp' => $utcTimestamp ] )
			] )
			->join( 'actor', null, 'log_actor=actor_id' );
	}
}
