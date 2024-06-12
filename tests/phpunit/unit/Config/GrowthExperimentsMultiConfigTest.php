<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\WikiPageConfig;
use IDBAccessObject;
use MediaWiki\Config\ConfigException;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Config\HashConfig;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Config\GrowthExperimentsMultiConfig
 */
class GrowthExperimentsMultiConfigTest extends MediaWikiUnitTestCase {
	private function getMockWikiPageConfig() {
		return $this->createMock( WikiPageConfig::class );
	}

	/**
	 * @dataProvider provideIsWikiConfigEnabled
	 * @covers ::isWikiConfigEnabled
	 * @param bool $shouldEnable
	 */
	public function testIsWikiConfigEnabled( bool $shouldEnable ) {
		$config = new GrowthExperimentsMultiConfig(
			$this->getMockWikiPageConfig(),
			new HashConfig( [ 'GEWikiConfigEnabled' => $shouldEnable ] )
		);
		$this->assertSame( $shouldEnable, $config->isWikiConfigEnabled() );
	}

	public static function provideIsWikiConfigEnabled() {
		return [
			'enabled' => [ true ],
			'disabled' => [ false ],
		];
	}

	/**
	 * @covers ::get
	 * @covers ::getWithFlags
	 */
	public function testGetConfigDisabled() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->never() )->method( 'getWithFlags' );

		$globalVarConfig = $this->createMock( GlobalVarConfig::class );
		$globalVarConfig->expects( $this->exactly( 2 ) )->method( 'get' )
			->willReturnMap( [
				[ 'GEWikiConfigEnabled', false ],
				[ 'GEFoo', 'global' ]
			] );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			$globalVarConfig
		);
		$this->assertEquals( 'global', $config->get( 'GEFoo' ) );
	}

	/**
	 * @covers ::get
	 * @covers ::getWithFlags
	 */
	public function testGetDisallowedVariable() {
		$this->expectException( ConfigException::class );
		$this->expectExceptionMessage( 'Config key cannot be retrieved via GrowthExperimentsMultiConfig' );

		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->never() )->method( 'hasWithFlags' );
		$wikiConfig->expects( $this->never() )->method( 'getWithFlags' );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [ 'GEWikiConfigEnabled' => true ] )
		);
		$config->get( 'GEFoo' );
	}

	/**
	 * @covers ::getWithFlags
	 */
	public function testGetWithFlagsFromWiki() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', IDBAccessObject::READ_LATEST )
			->willReturn( true );
		$wikiConfig->expects( $this->once() )->method( 'getWithFlags' )
			->with( 'GEMentorshipEnabled', IDBAccessObject::READ_LATEST )
			->willReturn( false );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [ 'GEWikiConfigEnabled' => true ] )
		);
		$this->assertFalse( $config->getWithFlags(
			'GEMentorshipEnabled',
			IDBAccessObject::READ_LATEST
		) );
	}

	/**
	 * @covers ::get
	 * @covers ::getWithFlags
	 */
	public function testGetFromGlobal() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', IDBAccessObject::READ_NORMAL )
			->willReturn( false );
		$wikiConfig->expects( $this->never() )->method( 'getWithFlags' );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [
				'GEWikiConfigEnabled' => true,
				'GEMentorshipEnabled' => true,
			] )
		);
		$this->assertTrue( $config->get( 'GEMentorshipEnabled' ) );
	}

	/**
	 * @covers ::get
	 * @covers ::getWithFlags
	 */
	public function testGetVariableNotFound() {
		$this->expectException( ConfigException::class );
		$this->expectExceptionMessage( 'Config key was not found in GrowthExperimentsMultiConfig' );

		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', IDBAccessObject::READ_NORMAL )
			->willReturn( false );
		$wikiConfig->expects( $this->never() )->method( 'getWithFlags' );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [
				'GEWikiConfigEnabled' => true
			] )
		);
		$config->get( 'GEMentorshipEnabled' );
	}

	/**
	 * @covers ::has
	 * @covers ::hasWithFlags
	 */
	public function testHasDisallowedVariable() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->never() )->method( 'hasWithFlags' );

		$config = new GrowthExperimentsMultiConfig(
			$this->getMockWikiPageConfig(),
			new HashConfig( [ 'GEWikiConfigEnabled' => true ] )
		);
		$this->assertFalse( $config->has( 'GEFoo' ) );
	}

	/**
	 * @covers ::hasWithFlags
	 */
	public function testHasWithFlagsWiki() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', IDBAccessObject::READ_LATEST )
			->willReturn( true );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [ 'GEWikiConfigEnabled' => true ] )
		);
		$this->assertTrue( $config->hasWithFlags(
			'GEMentorshipEnabled',
			IDBAccessObject::READ_LATEST
		) );
	}

	/**
	 * @covers ::has
	 * @covers ::hasWithFlags
	 */
	public function testHasGlobal() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', IDBAccessObject::READ_NORMAL )
			->willReturn( false );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [
				'GEWikiConfigEnabled' => true,
				'GEMentorshipEnabled' => true,
			] )
		);
		$this->assertTrue( $config->has( 'GEMentorshipEnabled' ) );
	}

	/**
	 * @covers ::has
	 * @covers ::hasWithFlags
	 */
	public function testHasNotFound() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', IDBAccessObject::READ_NORMAL )
			->willReturn( false );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [
				'GEWikiConfigEnabled' => true,
			] )
		);
		$this->assertFalse( $config->has( 'GEMentorshipEnabled' ) );
	}

	/**
	 * @dataProvider provideMergeStrategy
	 * @covers ::getWithFlags
	 */
	public function testMergeStrategy( string $name, $globalValue, $wikiValue, $expectedValue ) {
		$globalConfig = new HashConfig( [ 'GEWikiConfigEnabled' => true ] );
		if ( $globalValue !== null ) {
			$globalConfig->set( $name, $globalValue );
		}
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( $name )
			->willReturn( $wikiValue !== null );
		$wikiConfig->expects( ( $wikiValue === null ) ? $this->never() : $this->once() )
			->method( 'getWithFlags' )
			->with( $name )
			->willReturn( $wikiValue );
		$config = new GrowthExperimentsMultiConfig( $wikiConfig, $globalConfig );
		$this->assertSame( $expectedValue, $config->get( $name ) );
	}

	public static function provideMergeStrategy() {
		return [
			// variable, PHP value, on-wiki value, expected
			'replace' => [ 'GEHelpPanelViewMoreTitle', 'Foo', 'Bar', 'Bar' ],
			'wiki only' => [ 'GECampaigns', null, [ 'foo' => 1 ], [ 'foo' => 1 ] ],
			'global only' => [ 'GECampaigns', [ 'foo' => 1 ], null, [ 'foo' => 1 ] ],
			'aray_merge' => [ 'GECampaigns', [ 'foo' => 1, 'bar' => 2 ], [ 'foo' => 2, 'baz' => 3 ],
				[ 'foo' => 2, 'bar' => 2, 'baz' => 3 ] ],
		];
	}
}
