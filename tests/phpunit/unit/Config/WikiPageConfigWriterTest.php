<?php

namespace GrowthExperiments\Tests;

use FormatJson;
use GrowthExperiments\Config\Validation\IConfigValidator;
use GrowthExperiments\Config\Validation\NoValidationValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriter;
use InvalidArgumentException;
use JsonContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use RecentChange;
use StatusValue;
use Title;
use TitleFactory;
use User;
use Wikimedia\TestingAccessWrapper;
use WikiPage;

/**
 * @coversDefaultClass \GrowthExperiments\Config\WikiPageConfigWriter
 */
class WikiPageConfigWriterTest extends MediaWikiUnitTestCase {

	/**
	 * @param LinkTarget $configPage
	 * @param array|null $currentConfig Null for "should return an error"
	 * @param bool $expectLoad
	 * @return WikiPageConfigLoader|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getWikiPageConfigLoaderMock(
		LinkTarget $configPage,
		?array $currentConfig,
		bool $expectLoad
	) {
		$wikiPageConfigLoader = $this->createMock( WikiPageConfigLoader::class );
		$wikiPageConfigLoader->expects( $expectLoad ? $this->atLeastOnce() : $this->never() )
			->method( 'load' )
			->with( $configPage, WikiPageConfigLoader::READ_LATEST )
			->willReturn( $currentConfig ?? false );
		return $wikiPageConfigLoader;
	}

	/**
	 * @param LinkTarget $configPage
	 * @param bool $configPageExists
	 * @return \PHPUnit\Framework\MockObject\MockObject|TitleFactory
	 */
	private function getTitleFactoryMock( LinkTarget $configPage, bool $configPageExists ) {
		$configTitle = $this->createMock( Title::class );
		$configTitle->expects( $this->atLeastOnce() )
			->method( 'exists' )
			->willReturn( $configPageExists );
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->expects( $this->atLeastOnce() )
			->method( 'newFromLinkTarget' )
			->with( $configPage )
			->willReturn( $configTitle );
		return $titleFactory;
	}

	/**
	 * @param LinkTarget $configPage
	 * @param PageUpdater $updater
	 * @return WikiPageFactory|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getWikiPageFactoryMock( LinkTarget $configPage, PageUpdater $updater ) {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->expects( $this->once() )
			->method( 'newPageUpdater' )
			->willReturn( $updater );
		$wikiPageFactory = $this->createMock( WikiPageFactory::class );
		$wikiPageFactory->expects( $this->once() )
			->method( 'newFromLinkTarget' )
			->with( $configPage )
			->willReturn( $wikiPage );
		return $wikiPageFactory;
	}

	/**
	 * @param bool $isAutopatrol
	 * @return UserFactory|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getUserFactoryMock( bool $isAutopatrol ) {
		$user = $this->createMock( User::class );
		$user->expects( $this->once() )
			->method( 'authorizeWrite' )
			->willReturn( $isAutopatrol );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->expects( $this->once() )
			->method( 'newFromUserIdentity' )
			->willReturn( $user );
		return $userFactory;
	}

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			WikiPageConfigWriter::class,
			new WikiPageConfigWriter(
				new NoValidationValidator(),
				$this->createNoOpMock( WikiPageConfigLoader::class ),
				$this->createNoOpMock( WikiPageFactory::class ),
				$this->createNoOpMock( TitleFactory::class ),
				$this->createNoOpMock( UserFactory::class ),
				new NullLogger(),
				[],
				$this->createNoOpMock( LinkTarget::class ),
				new UserIdentityValue( 1, 'Performer' )
			)
		);
	}

	/**
	 * @covers ::getCurrentWikiConfig
	 * @dataProvider provideGetCurrentWikiConfig
	 * @param array|null $expected Null for "error expected"
	 * @param bool $configPageExists
	 * @param array|null $currentConfig Null for "error expected"
	 */
	public function testGetCurrentWikiConfig(
		?array $expected,
		bool $configPageExists,
		?array $currentConfig
	) {
		$configPage = $this->createNoOpMock( LinkTarget::class );
		$writer = new WikiPageConfigWriter(
			new NoValidationValidator(),
			$this->getWikiPageConfigLoaderMock( $configPage, $currentConfig, $configPageExists ),
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->getTitleFactoryMock( $configPage, $configPageExists ),
			$this->createNoOpMock( UserFactory::class ),
			new NullLogger(),
			[],
			$configPage,
			new UserIdentityValue( 1, 'Performer' )
		);

		if ( $expected === null ) {
			$this->expectException( InvalidArgumentException::class );
			$this->expectExceptionMessage( 'failed to load config' );
		}

		$result = TestingAccessWrapper::newFromObject( $writer )->getCurrentWikiConfig();

		if ( $expected !== null ) {
			$this->assertArrayEquals( $expected, $result );
		}
	}

	public function provideGetCurrentWikiConfig() {
		return [
			'empty existing page' => [ [], true, [] ],
			'existing page with content' => [ [ 'foo' => 321 ], true, [ 'foo' => 321 ] ],
			'non-existing page with content' => [ [], false, [ 'foo' => 123 ] ],
			'erroring out' => [ null, true, null ],
		];
	}

	/**
	 * @covers ::loadConfig
	 * @covers ::pruneConfig
	 */
	public function testLoadPruneConfig() {
		$configPage = $this->createNoOpMock( LinkTarget::class );
		$writer = new WikiPageConfigWriter(
			new NoValidationValidator(),
			$this->getWikiPageConfigLoaderMock( $configPage, [ 'foo' => 123 ], true ),
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->getTitleFactoryMock( $configPage, true ),
			$this->createNoOpMock( UserFactory::class ),
			new NullLogger(),
			[],
			$configPage,
			new UserIdentityValue( 1, 'Performer' )
		);
		$writerWrapper = TestingAccessWrapper::newFromObject( $writer );

		$this->assertNull( $writerWrapper->wikiConfig );
		$writerWrapper->loadConfig();
		$this->assertArrayEquals(
			[ 'foo' => 123 ],
			$writerWrapper->wikiConfig
		);

		$writer->pruneConfig();
		$this->assertArrayEquals(
			[],
			$writerWrapper->wikiConfig
		);
	}

	/**
	 * @covers ::setVariable
	 * @dataProvider provideSetVariable
	 * @param mixed $expectedBaseVariable
	 * @param mixed $expectedFullValue
	 * @param mixed $variable
	 * @param mixed $value
	 * @param bool $expectException
	 * @param array $currentConfig
	 */
	public function testSetVariable(
		$expectedBaseVariable,
		$expectedFullValue,
		$variable, $value,
		bool $expectException = false,
		array $currentConfig = []
	) {
		$validator = $this->createMock( IConfigValidator::class );
		$validator->expects( $expectException ? $this->never() : $this->once() )
			->method( 'validateVariable' )
			->with( $expectedBaseVariable, $expectedFullValue );

		$configPage = $this->createNoOpMock( LinkTarget::class );
		$writer = new WikiPageConfigWriter(
			$validator,
			$this->getWikiPageConfigLoaderMock( $configPage, $currentConfig, true ),
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->getTitleFactoryMock( $configPage, true ),
			$this->createNoOpMock( UserFactory::class ),
			new NullLogger(),
			[],
			$configPage,
			new UserIdentityValue( 1, 'Performer' )
		);
		$writerWrapper = TestingAccessWrapper::newFromObject( $writer );

		if ( $expectException ) {
			$this->expectException( InvalidArgumentException::class );
			$this->expectExceptionMessage( 'Trying to set a sub-field of a non-array' );
		}

		$this->assertNull( $writerWrapper->wikiConfig );
		$writer->setVariable( $variable, $value );

		if ( !$expectException ) {
			$this->assertArrayEquals(
				[ $expectedBaseVariable => $expectedFullValue ],
				$writerWrapper->wikiConfig
			);
		}
	}

	public function provideSetVariable() {
		return [
			'normal' => [
				'foo', 123,
				'foo', 123
			],
			'setting non array' => [
				'foo', 123,
				[ 'foo', 'bar' ], 123,
				true,
				[ 'foo' => 123 ]
			]
		];
	}

	/**
	 * @covers ::setVariable
	 */
	public function testSetVariableMultiple() {
		$configPage = $this->createNoOpMock( LinkTarget::class );
		$writer = new WikiPageConfigWriter(
			new NoValidationValidator(),
			$this->getWikiPageConfigLoaderMock( $configPage, [ 'preexisting' => 123 ], true ),
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->getTitleFactoryMock( $configPage, true ),
			$this->createNoOpMock( UserFactory::class ),
			new NullLogger(),
			[],
			$configPage,
			new UserIdentityValue( 1, 'Performer' )
		);
		$writerWrapper = TestingAccessWrapper::newFromObject( $writer );

		$this->assertNull( $writerWrapper->wikiConfig );

		$writer->setVariable( 'foo', 'bar' );
		$this->assertArrayEquals(
			[ 'foo' => 'bar', 'preexisting' => 123 ],
			$writerWrapper->wikiConfig
		);

		$writer->setVariable( 'bar', 'foo' );
		$this->assertArrayEquals(
			[ 'foo' => 'bar', 'bar' => 'foo', 'preexisting' => 123 ],
			$writerWrapper->wikiConfig
		);
	}

	/**
	 * @covers ::save
	 * @dataProvider provideSave
	 * @param bool $isAutopatrol
	 */
	public function testSave( bool $isAutopatrol ) {
		$newConfig = [
			'Test' => 123,
			'TestBaz' => 321
		];

		$configPage = $this->createNoOpMock( LinkTarget::class );

		$wikiPageConfigLoader = $this->getWikiPageConfigLoaderMock( $configPage, [], true );
		$wikiPageConfigLoader->expects( $this->once() )
			->method( 'invalidate' )
			->with( $configPage );

		$updater = $this->createMock( PageUpdater::class );
		$updater->expects( $this->once() )
			->method( 'setContent' )
			->with( SlotRecord::MAIN, new JsonContent( FormatJson::encode( $newConfig ) ) );
		$updater->expects( $isAutopatrol ? $this->once() : $this->never() )
			->method( 'setRcPatrolStatus' )
			->with( RecentChange::PRC_AUTOPATROLLED );
		$updater->expects( $this->once() )
			->method( 'saveRevision' );

		$validator = $this->createMock( IConfigValidator::class );
		$validator->expects( $this->once() )
			->method( 'validate' )
			->with( $newConfig )
			->willReturn( StatusValue::newGood() );

		$writer = new WikiPageConfigWriter(
			$validator,
			$wikiPageConfigLoader,
			$this->getWikiPageFactoryMock( $configPage, $updater ),
			$this->getTitleFactoryMock( $configPage, true ),
			$this->getUserFactoryMock( $isAutopatrol ),
			new NullLogger(),
			[],
			$configPage,
			new UserIdentityValue( 1, 'Performer' )
		);
		$this->assertArrayEquals( [], TestingAccessWrapper::newFromObject( $writer )->getCurrentWikiConfig() );
		TestingAccessWrapper::newFromObject( $writer )->wikiConfig = $newConfig;

		$this->assertTrue( $writer->save()->isOK() );
	}

	public function provideSave() {
		return [
			'autopatrolled' => [ true ],
			'non-autopatrolled' => [ false ],
		];
	}
}
