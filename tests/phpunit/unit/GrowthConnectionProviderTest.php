<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\GrowthConnectionProvider;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \GrowthExperiments\GrowthConnectionProvider
 */
class GrowthConnectionProviderTest extends MediaWikiUnitTestCase {

	public function testGetPrimaryDatabaseUsesGrowthVirtualDomain(): void {
		$database = $this->createMock( IDatabase::class );
		$innerProvider = $this->createMock( IConnectionProvider::class );
		$innerProvider->expects( $this->once() )
			->method( 'getPrimaryDatabase' )
			->with( GrowthConnectionProvider::VIRTUAL_DOMAIN )
			->willReturn( $database );

		$provider = new GrowthConnectionProvider( $innerProvider );
		$this->assertSame( $database, $provider->getPrimaryDatabase() );
	}

	public function testGetReplicaDatabaseUsesGrowthVirtualDomain(): void {
		$database = $this->createMock( IReadableDatabase::class );
		$innerProvider = $this->createMock( IConnectionProvider::class );
		$innerProvider->expects( $this->once() )
			->method( 'getReplicaDatabase' )
			->with( GrowthConnectionProvider::VIRTUAL_DOMAIN, null )
			->willReturn( $database );

		$provider = new GrowthConnectionProvider( $innerProvider );
		$this->assertSame( $database, $provider->getReplicaDatabase() );
	}

	public function testGetReplicaDatabaseForwardsGroup(): void {
		$database = $this->createMock( IReadableDatabase::class );
		$innerProvider = $this->createMock( IConnectionProvider::class );
		$innerProvider->expects( $this->once() )
			->method( 'getReplicaDatabase' )
			->with( GrowthConnectionProvider::VIRTUAL_DOMAIN, 'vslow' )
			->willReturn( $database );

		$provider = new GrowthConnectionProvider( $innerProvider );
		$this->assertSame( $database, $provider->getReplicaDatabase( 'vslow' ) );
	}
}
