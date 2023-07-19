<?php

namespace GrowthExperiments\UserImpact;

use IDBAccessObject;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\SelectQueryBuilder;

class DatabaseUserImpactStore implements UserImpactStore {

	use ExpensiveUserImpactFallbackTrait;

	/** @internal only exposed for tests */
	public const TABLE_NAME = 'growthexperiments_user_impact';

	private ILoadBalancer $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
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
		$queryBuilder = new SelectQueryBuilder( $this->loadBalancer->getConnection( DB_REPLICA ) );
		$queryBuilder->table( self::TABLE_NAME );
		$queryBuilder->fields( [ 'geui_user_id', 'geui_data' ] );
		$queryBuilder->where( [ 'geui_user_id' => $userIds ] );
		foreach ( $queryBuilder->fetchResultSet() as $row ) {
			$userImpactArray = json_decode( $row->geui_data, true );
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
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$data = [
			'geui_data' => json_encode( $userImpact, JSON_UNESCAPED_UNICODE ),
			'geui_timestamp' => $dbw->timestamp( $userImpact->getGeneratedAt() ),
		];
		$dbw->upsert(
			self::TABLE_NAME,
			[
				'geui_user_id' => $userImpact->getUser()->getId(),
			] + $data,
			'geui_user_id',
			$data,
			__METHOD__
		);
	}

}
