<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;
use Wikimedia\AtEase\AtEase;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\ILoadBalancer;

class DatabaseUserImpactStore implements UserImpactStore {

	use ExpensiveUserImpactFallbackTrait;

	/** @internal only exposed for tests */
	public const TABLE_NAME = 'growthexperiments_user_impact';

	private ILoadBalancer $loadBalancer;

	public function __construct(
		ILoadBalancer $loadBalancer
	) {
		$this->loadBalancer = $loadBalancer;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user, int $flags = IDBAccessObject::READ_NORMAL ): ?UserImpact {
		$userId = $user->getId();
		return $this->batchGetUserImpact( [ $userId ] )[$userId];
	}

	/**
	 * @param int[] $userIds
	 * @return (UserImpact|null)[] Map of user ID => UserImpact or null.
	 * @todo make this part of the interface
	 */
	public function batchGetUserImpact( array $userIds ): array {
		if ( !$userIds ) {
			return [];
		}

		$userImpacts = array_fill_keys( $userIds, null );
		$queryBuilder = $this->loadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'geui_user_id', 'geui_data' ] )
			->from( self::TABLE_NAME )
			->where( [ 'geui_user_id' => $userIds ] )
			->caller( __METHOD__ );
		foreach ( $queryBuilder->fetchResultSet() as $row ) {
			AtEase::suppressWarnings();
			$userImpactArray = gzinflate( $row->geui_data );
			AtEase::restoreWarnings();
			if ( $userImpactArray === false ) {
				$userImpactArray = $row->geui_data;
			}
			$userImpactArray = json_decode( $userImpactArray, true );

			if ( ( $userImpactArray['@version'] ?? 0 ) !== UserImpact::VERSION ) {
				continue;
			}
			$userImpacts[$row->geui_user_id] = UserImpact::newFromJsonArray( $userImpactArray );
		}
		return $userImpacts;
	}

	/**
	 * Saves the user impact to the database.
	 * @param UserImpact $userImpact
	 * @return void
	 */
	public function setUserImpact( UserImpact $userImpact ): void {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );

		$data = [
			'geui_data' => gzdeflate( json_encode( $userImpact, JSON_UNESCAPED_UNICODE ) ),
			'geui_timestamp' => $dbw->timestamp( $userImpact->getGeneratedAt() ),
		];

		$storedUserId = $dbr->newSelectQueryBuilder()
			->select( 'geui_user_id' )
			->from( self::TABLE_NAME )
			->where( [ 'geui_user_id' => $userImpact->getUser()->getId() ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( $storedUserId !== false ) {
			$dbw->newUpdateQueryBuilder()
				->update( self::TABLE_NAME )
				->set( $data )
				->where( [ 'geui_user_id' => $userImpact->getUser()->getId() ] )
				->caller( __METHOD__ )
				->execute();
		} else {
			$dbw->newInsertQueryBuilder()
				->insertInto( self::TABLE_NAME )
				->row( [
					'geui_user_id' => $userImpact->getUser()->getId(),
				] + $data )
				->onDuplicateKeyUpdate()
				->uniqueIndexFields( 'geui_user_id' )
				->set( $data )
				->caller( __METHOD__ )
				->execute();
		}
	}

}
