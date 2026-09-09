<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\ReadingRecommendations\ReadingRecommendation;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsFormatter;
use MediaWiki\Search\Entity\SearchResultThumbnail;
use MediaWiki\Search\SearchResultThumbnailProvider;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use Wikibase\Client\Store\DescriptionLookup;

/**
 * @group Database
 * @covers \GrowthExperiments\ReadingRecommendations\ReadingRecommendationsFormatter
 */
class ReadingRecommendationsFormatterTest extends MediaWikiIntegrationTestCase {

	private const FIXTURE_PATH = __DIR__ . '/../../../../modules/' .
		'ext.growthExperiments.Homepage.ReadingRecommendations/fixtures/recommendations.json';

	/**
	 * The formatter output for the fixture's articles is the fixture itself,
	 * so the committed fixture is the export contract for the Vue app.
	 */
	public function testFormatMatchesFixture() {
		if ( !class_exists( DescriptionLookup::class ) ) {
			$this->markTestSkipped( 'Wikibase Client is not installed' );
		}
		$fixture = self::getFixture();
		$pages = [];
		foreach ( $fixture as $row ) {
			$pages[] = $this->getExistingTestPage( $row['title'] );
		}
		$pageIds = array_map( static fn ( $page ) => $page->getId(), $pages );

		$thumbnails = [];
		$descriptions = [];
		foreach ( $fixture as $index => $row ) {
			if ( $row['thumbnail'] ) {
				$thumbnails[$pageIds[$index]] = new SearchResultThumbnail(
					'image/jpeg',
					null,
					$row['thumbnail']['width'],
					$row['thumbnail']['height'],
					null,
					$row['thumbnail']['url'],
					null
				);
			}
			if ( $row['description'] !== null ) {
				$descriptions[$pageIds[$index]] = $row['description'];
			}
		}

		$thumbnailProvider = $this->createMock( SearchResultThumbnailProvider::class );
		$thumbnailProvider->expects( $this->once() )
			->method( 'getThumbnails' )
			->with(
				$this->callback( static fn ( array $batch ) => array_keys( $batch ) === $pageIds ),
				ReadingRecommendationsFormatter::THUMBNAIL_SIZE
			)
			->willReturn( $thumbnails );

		$descriptionLookup = $this->createMock( DescriptionLookup::class );
		$descriptionLookup->expects( $this->once() )
			->method( 'getDescriptions' )
			->with(
				$this->callback( static fn ( array $batch ) => array_map(
					static fn ( Title $page ) => $page->getArticleID(),
					$batch
				) === $pageIds ),
				[ DescriptionLookup::SOURCE_LOCAL, DescriptionLookup::SOURCE_CENTRAL ]
			)
			->willReturn( $descriptions );

		$recommendations = [];
		foreach ( $fixture as $index => $row ) {
			$recommendations[] = new ReadingRecommendation(
				$pages[$index]->getTitle()->getTitleValue(),
				$row['relatedTo'] !== null ? new TitleValue( NS_MAIN, $row['relatedTo'] ) : null
			);
		}
		$recommendations[] = new ReadingRecommendation( new TitleValue( NS_MAIN, 'FormatterDoesNotExist' ) );

		$items = $this->getFormatter( $thumbnailProvider, $descriptionLookup )->format( $recommendations );

		$this->assertSame(
			$pageIds,
			array_column( $items, 'pageId' ),
			'existing pages are exported in order and the missing title is dropped'
		);
		// Page IDs and URLs depend on the test wiki. Check the URL against the
		// page, then replace both with the fixture values for the comparison.
		foreach ( $items as $index => &$item ) {
			$this->assertSame( $pages[$index]->getTitle()->getLinkURL(), $item['url'] );
			$item['pageId'] = $fixture[$index]['pageId'];
			$item['url'] = $fixture[$index]['url'];
		}
		unset( $item );
		$this->assertSame( $fixture, $items );
	}

	public function testFormatWithoutDescriptionLookup() {
		$page = $this->getExistingTestPage( 'FormatterWithoutDescriptionLookup' );
		$items = $this->getFormatter()->format( [
			new ReadingRecommendation( $page->getTitle()->getTitleValue() ),
		] );

		$this->assertCount( 1, $items );
		$this->assertNull( $items[0]['description'] );
	}

	public function testFormatEmptyList() {
		$this->assertSame( [], $this->getFormatter()->format( [] ) );
	}

	private function getFormatter(
		?SearchResultThumbnailProvider $thumbnailProvider = null,
		?DescriptionLookup $descriptionLookup = null
	): ReadingRecommendationsFormatter {
		$services = $this->getServiceContainer();
		return new ReadingRecommendationsFormatter(
			$services->getTitleFactory(),
			$services->getLinkBatchFactory(),
			$thumbnailProvider ?? $services->getSearchResultThumbnailProvider(),
			$services->getTitleFormatter(),
			$descriptionLookup
		);
	}

	/** @return array[] */
	private static function getFixture(): array {
		return json_decode( file_get_contents( self::FIXTURE_PATH ), true, 512, JSON_THROW_ON_ERROR );
	}
}
