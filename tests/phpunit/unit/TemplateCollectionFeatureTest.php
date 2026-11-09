<?php

namespace GrowthExperiments\Tests\Unit;

use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\WarningCollector;
use GrowthExperiments\TemplateCollectionFeature;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use Wikimedia\Assert\PostconditionException;

/**
 * @covers \GrowthExperiments\TemplateCollectionFeature
 */
class TemplateCollectionFeatureTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( !class_exists( SimpleKeywordFeature::class ) ) {
			$this->markTestSkipped( 'CirrusSearch extension is not installed.' );
		}
	}

	private function getTitleFactory(): TitleFactory {
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromText' )->willReturnCallback(
			fn ( string $text ) => $this->getTitle( $text )
		);
		$titleFactory->method( 'newFromLinkTarget' )->willReturnCallback(
			fn ( LinkTarget $target ) => $this->getTitle( 'Template:' . $target->getText() )
		);
		return $titleFactory;
	}

	private function getTitle( string $prefixedText ): Title {
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( $prefixedText );
		return $title;
	}

	public function testCollectionsLoadOnceOnFirstUse() {
		$calls = 0;
		$feature = new TemplateCollectionFeature(
			static function () use ( &$calls ): array {
				$calls++;
				return [
					'infobox' => [ 'Template:Infobox person' ],
					'copyedit' => [ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ],
				];
			},
			$this->getTitleFactory()
		);
		$this->assertSame( 0, $calls );

		$warningCollector = $this->createNoOpMock( WarningCollector::class );
		$this->assertSame(
			[ 'templates' => [ 'Template:Infobox person' ] ],
			$feature->parseValue( 'hastemplatecollection', 'infobox', 'infobox', '', '', $warningCollector )
		);
		$this->assertSame(
			[ 'templates' => [ 'Template:Copyedit' ] ],
			$feature->parseValue( 'hastemplatecollection', 'copyedit', 'copyedit', '', '', $warningCollector )
		);
		$this->assertSame( 1, $calls );
	}

	public function testUnknownCollection() {
		$feature = new TemplateCollectionFeature(
			static fn () => [ 'infobox' => [ 'Template:Infobox person' ] ],
			$this->getTitleFactory()
		);
		$warningCollector = $this->createMock( WarningCollector::class );
		$warningCollector->expects( $this->once() )
			->method( 'addWarning' )
			->with( 'growthexperiments-templatecollectionfeature-invalid-collection', 'foo' );

		$this->assertSame(
			[ 'templates' => [] ],
			$feature->parseValue( 'hastemplatecollection', 'foo', 'foo', '', '', $warningCollector )
		);
	}

	public function testTooManyTemplates() {
		$feature = new TemplateCollectionFeature(
			static fn () => [
				'infobox' => array_fill( 0, TemplateCollectionFeature::MAX_TEMPLATES_IN_COLLECTION + 1, 'Infobox' ),
			],
			$this->getTitleFactory()
		);

		$this->expectException( PostconditionException::class );
		$this->expectExceptionMessage( 'collection "infobox"' );
		$feature->parseValue(
			'hastemplatecollection', 'infobox', 'infobox', '', '', $this->createNoOpMock( WarningCollector::class )
		);
	}

}
