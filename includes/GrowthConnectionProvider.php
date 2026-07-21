<?php

namespace GrowthExperiments;

use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class GrowthConnectionProvider {

	public const VIRTUAL_DOMAIN = 'virtual-growthexperiments';

	public function __construct( private IConnectionProvider $connectionProvider ) {
	}

	public function getPrimaryDatabase(): IDatabase {
		return $this->connectionProvider->getPrimaryDatabase( self::VIRTUAL_DOMAIN );
	}

	public function getReplicaDatabase(
		?string $group = null
	): IReadableDatabase {
		return $this->connectionProvider->getReplicaDatabase( self::VIRTUAL_DOMAIN, $group );
	}
}
