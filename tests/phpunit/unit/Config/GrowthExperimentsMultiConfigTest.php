<?php

namespace GrowthExperiments\Tests;

use ConfigException;
use GlobalVarConfig;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\WikiPageConfig;
use HashConfig;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Config\GrowthExperimentsMultiConfig
 */
class GrowthExperimentsMultiConfigTest extends MediaWikiUnitTestCase {
	private function getMockWikiPageConfig() {
		return $this->getMockBuilder( WikiPageConfig::class )
			->disableOriginalConstructor()
			->getMock();
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

	public function provideIsWikiConfigEnabled() {
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

		$globalVarConfig = $this->getMockBuilder( GlobalVarConfig::class )->getMock();
		$globalVarConfig->expects( $this->exactly( 2 ) )->method( 'get' )
			->withConsecutive(
				[ 'GEWikiConfigEnabled' ],
				[ 'GEFoo' ]
			)
			->willReturnOnConsecutiveCalls( false, 'global' );

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
			->with( 'GEMentorshipEnabled', GrowthExperimentsMultiConfig::READ_LATEST )
			->willReturn( true );
		$wikiConfig->expects( $this->once() )->method( 'getWithFlags' )
			->with( 'GEMentorshipEnabled', GrowthExperimentsMultiConfig::READ_LATEST )
			->willReturn( false );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [ 'GEWikiConfigEnabled' => true ] )
		);
		$this->assertFalse( $config->getWithFlags(
			'GEMentorshipEnabled',
			GrowthExperimentsMultiConfig::READ_LATEST
		) );
	}

	/**
	 * @covers ::get
	 * @covers ::getWithFlags
	 */
	public function testGetFromGlobal() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', GrowthExperimentsMultiConfig::READ_NORMAL )
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
			->with( 'GEMentorshipEnabled', GrowthExperimentsMultiConfig::READ_NORMAL )
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
			->with( 'GEMentorshipEnabled', GrowthExperimentsMultiConfig::READ_LATEST )
			->willReturn( true );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [ 'GEWikiConfigEnabled' => true ] )
		);
		$this->assertTrue( $config->hasWithFlags(
			'GEMentorshipEnabled',
			GrowthExperimentsMultiConfig::READ_LATEST
		) );
	}

	/**
	 * @covers ::has
	 * @covers ::hasWithFlags
	 */
	public function testHasGlobal() {
		$wikiConfig = $this->getMockWikiPageConfig();
		$wikiConfig->expects( $this->once() )->method( 'hasWithFlags' )
			->with( 'GEMentorshipEnabled', GrowthExperimentsMultiConfig::READ_NORMAL )
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
			->with( 'GEMentorshipEnabled', GrowthExperimentsMultiConfig::READ_NORMAL )
			->willReturn( false );

		$config = new GrowthExperimentsMultiConfig(
			$wikiConfig,
			new HashConfig( [
				'GEWikiConfigEnabled' => true,
			] )
		);
		$this->assertFalse( $config->has( 'GEMentorshipEnabled' ) );
	}
}
