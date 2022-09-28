<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class DatabaseUserImpactStore implements UserImpactStore {

	use ExpensiveUserImpactFallbackTrait;

	/** @internal only exposed for tests */
	public const TABLE_NAME = 'growthexperiments_user_impact';

	/** @var IDatabase */
	private $dbr;

	/** @var IDatabase */
	private $dbw;

	/**
	 * @param IDatabase $dbr
	 * @param IDatabase $dbw
	 */
	public function __construct(
		IDatabase $dbr,
		IDatabase $dbw
	) {
		$this->dbr = $dbr;
		$this->dbw = $dbw;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user ): ?UserImpact {
		$queryBuilder = new SelectQueryBuilder( $this->dbr );
		$queryBuilder->table( self::TABLE_NAME );
		$queryBuilder->field( 'geui_data' );
		$queryBuilder->where( [ 'geui_user_id' => $user->getId() ] );
		$serializedUserImpact = $queryBuilder->fetchField();
		if ( $serializedUserImpact === false ) {
			return null;
		}
		$userImpactArray = json_decode( $serializedUserImpact, true );
		if ( ( $userImpactArray['@version'] ?? 0 ) !== UserImpact::VERSION ) {
			return null;
		}
		return UserImpact::newFromJsonArray( json_decode( $serializedUserImpact, true ) );
	}

	/**
	 * Saves the user impact to the database.
	 * @param UserImpact $userImpact
	 * @return void
	 */
	public function setUserImpact( UserImpact $userImpact ): void {
		$data = [
			'geui_data' => json_encode( $userImpact, JSON_UNESCAPED_UNICODE ),
			'geui_timestamp' => $this->dbw->timestamp( $userImpact->getGeneratedAt() ),
		];
		$this->dbw->upsert(
			self::TABLE_NAME,
			[
				'geui_user_id' => $userImpact->getUser()->getId(),
			] + $data,
			[ 'geui_user_id' ],
			$data,
			__METHOD__
		);
	}

}
