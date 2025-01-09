<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Config\Validation\IConfigValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\GrowthExperimentsServices;
use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Throwable;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\Config\WikiPageConfigWriter
 * @group Database
 */
class WikiPageConfigWriterIntegrationTest extends MediaWikiIntegrationTestCase {
	/** @var Title */
	private $defaultConfigTitle;

	protected function setUp(): void {
		parent::setUp();

		// Prevent GrowthExperimentsConfig.json caching
		$this->setMainCache( CACHE_NONE );
		$this->overrideConfigValue( 'GEUseCommunityConfigurationExtension', false );

		$this->defaultConfigTitle = $this->getServiceContainer()
			->getTitleFactory()
			->newFromText( 'MediaWiki:GrowthExperimentsConfig.json' );
	}

	/**
	 * @param LinkTarget|null $configPage
	 * @param User|null $performer
	 * @return WikiPageConfigWriter
	 */
	private function getWriter(
		?LinkTarget $configPage = null,
		?User $performer = null
	): WikiPageConfigWriter {
		return GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getWikiPageConfigWriterFactory()
			->newWikiPageConfigWriter(
				$configPage ?? $this->defaultConfigTitle,
				$performer
			);
	}

	private function getLoader() {
		return GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getWikiPageConfigLoader();
	}

	/**
	 * @covers ::save
	 * @covers ::setVariable
	 */
	public function testInitialSave() {
		$this->assertFalse( $this->defaultConfigTitle->exists() );
		$writer = $this->getWriter();
		$writer->setVariable( 'GEMentorshipEnabled', false );
		$writer->save();
		$this->assertTrue( $this->defaultConfigTitle->exists() );
		$this->assertArrayEquals( [
			'GEMentorshipEnabled' => false
		], $this->getLoader()->load( $this->defaultConfigTitle ) );
	}

	/**
	 * @covers ::setVariable
	 * @covers ::setVariables
	 * @covers ::save
	 */
	public function testSaveChange() {
		$writer = $this->getWriter();
		$writer->setVariables( [
			'GEMentorshipEnabled' => false,
			'GEHelpPanelAskMentor' => false,
		] );
		$writer->save();
		$this->assertTrue( $this->defaultConfigTitle->exists() );
		$this->assertArrayEquals( [
			'GEMentorshipEnabled' => false,
			'GEHelpPanelAskMentor' => false
		], $this->getLoader()->load( $this->defaultConfigTitle ) );

		$writer = $this->getWriter();
		$writer->setVariable( 'GEMentorshipEnabled', true );
		$writer->save();
		$this->assertTrue( $this->defaultConfigTitle->exists() );
		$this->assertArrayEquals( [
			'GEMentorshipEnabled' => true,
			'GEHelpPanelAskMentor' => false
		], $this->getLoader()->load( $this->defaultConfigTitle ) );
	}

	/**
	 * @dataProvider provideSetVariable
	 * @covers ::setVariable
	 * @param string $variable Variable name
	 * @param mixed $wikiConfig Initial config state
	 * @param string $setVariable $variable parameter passed to setVariable()
	 * @param mixed $setValue $value parameter passed to setVariable()
	 * @param mixed $expectedValue Expected final value of variable, or an exception that's expected
	 *   to be thrown.
	 * @return void
	 */
	public function testSetVariable( string $variable, $wikiConfig, $setVariable, $setValue, $expectedValue ) {
		/** @var IConfigValidator|MockObject $validator */
		$validator = $this->createNoOpMock( IConfigValidator::class, [ 'validateVariable' ] );
		/** @var WikiPageConfigLoader|MockObject $loader */
		$loader = $this->createNoOpMock( WikiPageConfigLoader::class, [ 'load' ] );
		$loader->method( 'load' )->willReturn( $wikiConfig );
		$writer = new WikiPageConfigWriter(
			$validator,
			$loader,
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getHookContainer(),
			new NullLogger(),
			$this->getExistingTestPage()->getTitle(),
			$this->getTestUser()->getUserIdentity()
		);
		if ( $expectedValue instanceof Throwable ) {
			$this->expectException( get_class( $expectedValue ) );
		}
		$writer->setVariable( $setVariable, $setValue );
		if ( !( $expectedValue instanceof Throwable ) ) {
			$this->assertSame( [ $variable => $expectedValue ],
				TestingAccessWrapper::newFromObject( $writer )->wikiConfig );
		}
	}

	public static function provideSetVariable() {
		return [
			// variable name, initial config, $variable, $value, expected value
			'basic' => [ 'var', [ 'var' => 'foo' ], 'var', 'bar', 'bar' ],
			'unset' => [ 'var', [], 'var', 'bar', 'bar' ],
			'subfield of string' => [ 'var', [ 'var' => 'foo' ], [ 'var', 'sub' ], 'bar',
				new InvalidArgumentException() ],
			'subfield of unset' => [ 'var', [], [ 'var', 'sub' ], 'bar', [ 'sub' => 'bar' ] ],
			'subfield of null' => [ 'var', [ 'var' => null ], [ 'var', 'sub' ], 'bar', [ 'sub' => 'bar' ] ],
			'subfield of empty' => [ 'var', [ 'var' => [] ], [ 'var', 'sub' ], 'bar', [ 'sub' => 'bar' ] ],
			'add subfield' => [ 'var', [ 'var' => [ 'other' => 'boom' ] ], [ 'var', 'sub' ], 'bar',
				[ 'other' => 'boom', 'sub' => 'bar' ] ],
			'override subfield' => [ 'var', [ 'var' => [ 'sub' => 'baz' ] ], [ 'var', 'sub' ], 'bar',
				[ 'sub' => 'bar' ] ],
			'second-level subfield of string' => [ 'var', [ 'var' => [ 'sub' => 'baz' ] ], [ 'var', 'sub', 'sub2' ],
				'bar', new InvalidArgumentException() ],
			'add second-level subfield' => [ 'var', [ 'var' => [ 'sub' => [] ] ], [ 'var', 'sub', 'sub2' ], 'bar',
				[ 'sub' => [ 'sub2' => 'bar' ] ] ],
			'override second-level subfield' => [ 'var', [ 'var' => [ 'sub' => [ 'sub2' => 'baz' ] ] ],
				[ 'var', 'sub', 'sub2' ], 'bar', [ 'sub' => [ 'sub2' => 'bar' ] ] ],
		];
	}

	/**
	 * @dataProvider provideVariableExists
	 * @covers ::variableExists
	 * @param mixed $wikiConfig Initial config state
	 * @param string $variable $variable parameter passed to setVariable()
	 * @param mixed $expectedValue Expected return value, or an exception that's expected
	 *   to be thrown.
	 * @return void
	 */
	public function testVariableExists( array $wikiConfig, $variable, $expectedValue ) {
		/** @var IConfigValidator|MockObject $validator */
		$validator = $this->createNoOpMock( IConfigValidator::class, [ 'validateVariable' ] );
		/** @var WikiPageConfigLoader|MockObject $loader */
		$loader = $this->createNoOpMock( WikiPageConfigLoader::class, [ 'load' ] );
		$loader->method( 'load' )->willReturn( $wikiConfig );
		$writer = new WikiPageConfigWriter(
			$validator,
			$loader,
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getHookContainer(),
			new NullLogger(),
			$this->getExistingTestPage()->getTitle(),
			$this->getTestUser()->getUserIdentity()
		);
		if ( $expectedValue instanceof Throwable ) {
			$this->expectException( get_class( $expectedValue ) );
		}
		$value = $writer->variableExists( $variable );
		if ( !( $expectedValue instanceof Throwable ) ) {
			$this->assertSame( $expectedValue, $value );
		}
	}

	public static function provideVariableExists() {
		return [
			// initial config, $variable, expected value
			'set' => [ [ 'var' => 'foo' ], 'var', true ],
			'unset' => [ [ 'var' => 'foo' ], 'var2', false ],
			'null is set' => [ [ 'var' => null ], 'var', true ],
			'unset subfield' => [ [ 'var' => 'foo' ], [ 'var', 'sub' ], new InvalidArgumentException() ],
			'subfield of null is not error' => [ [ 'var' => null ], [ 'var', 'sub' ], false ],
			'unset parent' => [ [], [ 'var', 'sub' ], false ],
			'unset child' => [ [ 'var' => [] ], [ 'var', 'sub' ], false ],
			'set child' => [ [ 'var' => [ 'sub' => [] ] ], [ 'var', 'sub' ], true ],
		];
	}
}
