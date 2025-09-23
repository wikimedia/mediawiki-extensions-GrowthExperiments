<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataService;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Site\Site;
use MediaWiki\Site\SiteStore;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider
 */
class ImageRecommendationMetadataProviderTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGetMetadata_Reason
	 */
	public function testGetMetadata_Reason(
		array $suggestionOverrides,
		string $expectedReason
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikimediaMessages' );
		$this->markTestSkippedIfExtensionNotLoaded( 'cldr' );

		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description',
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en' ],
			$this->getServiceContainer()->getLanguageNameUtils(),
			new DerivativeContext( RequestContext::getMain() ),
			$this->getMockSiteLookup()
		);
		$actualReason = $metadataProvider->getMetadata(
			$this->getSuggestionData( 'Image.jpg', $suggestionOverrides )
		)['reason'];
		$this->assertEquals( $expectedReason, $actualReason );
	}

	public static function provideGetMetadata_Reason() {
		return [
			// suggestion data, expected message, assert as message key (vs. actual message)?
			[ [ 'source' => 'wikidata' ], 'Found in the Wikidata item for this article (strong match).' ],
			[ [ 'source' => 'commons' ], 'One of several images linked to the Wikidata item for this article.' ],
			[ [ 'source' => 'wikipedia', 'projects' => [ 'cswiki' ] ],
				'Used in the same article in Czech Wikipedia.' ],
			[ [ 'source' => 'wikipedia', 'projects' => [ 'cswiki' ] ],
				'Used in the same article in Czech Wikipedia.' ],
			[ [ 'source' => 'wikipedia', 'projects' => [ 'cswiki', 'frwiki' ] ],
				'Used in the same article in 2 other languages: Czech and French.' ],
			[ [ 'source' => 'wikidata-section-topics' ],
				'Linked to a Wikidata item that may be relevant to this article section.' ],
			[ [ 'source' => 'wikidata-section-alignment', 'projects' => [ 'cswiki' ] ],
				'Used in an equivalent article section in Czech Wikipedia.' ],
			[ [ 'source' => 'wikidata-section-alignment', 'projects' => [ 'nosuchwiki' ] ],
				'Used in an equivalent article section in one other language: 1 other.' ],
			[ [ 'source' => 'wikidata-section-alignment', 'projects' => [ 'cswiki', 'frwiki' ] ],
				'Used in an equivalent article section in 2 other languages: Czech and French.' ],
			[ [ 'source' => 'wikidata-section-intersection', 'projects' => [ 'cswiki' ] ],
				'Used in an equivalent article section in Czech Wikipedia, and relates to '
				. 'a Wikidata item that may be relevant to this article section.' ],
			[ [ 'source' => 'wikidata-section-intersection', 'projects' => [ 'nosuchwiki' ] ],
				'Used in an equivalent article section in one other language, and relates to '
				. 'a Wikidata item that may be relevant to this article section.' ],
			[ [ 'source' => 'wikidata-section-intersection', 'projects' => [ 'cswiki', 'frwiki' ] ],
				'Used in an equivalent article section in Czech Wikipedia and one more language, '
				. 'and relates to a Wikidata item that may be relevant to this article section.' ],
			[ [ 'source' => 'wikidata-section-intersection', 'projects' => [ 'cswiki', 'frwiki', 'eswiki' ] ],
				'Used in an equivalent article section in Czech Wikipedia and 2 more languages, '
				. 'and relates to a Wikidata item that may be relevant to this article section.' ],
		];
	}

	private function getImageDescription( array $description = [] ): array {
		return [
			'ImageDescription' => [
				'value' => $description,
			],
		];
	}

	private function getMockFileMetadata(): array {
		return [
			'descriptionUrl' => 'https://commons.wikimedia.org/wiki/File:HMS_Pandora.jpg',
			'thumbUrl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3d/' .
				'HMS_Pandora.jpg/300px-HMS_Pandora.jpg',
			'fullUrl' => 'https://upload.wikimedia.org/wikipedia/commons/3/3d/HMS_Pandora.jpg',
			'originalWidth' => 1024,
			'originalHeight' => 768,
			'mustRender' => true,
			'isVectorized' => false,
		];
	}

	private function getMockService(
		array $fileMetadata = [],
		array $extendedMetadata = [],
		array $apiMetadata = []
	): ImageRecommendationMetadataService {
		$metadataService = $this->createNoOpMock(
			ImageRecommendationMetadataService::class,
			[ 'getFileMetadata', 'getExtendedMetadata', 'getApiMetadata' ]
		);
		$metadataService->method( 'getFileMetadata' )->willReturn( $fileMetadata );
		$metadataService->method( 'getExtendedMetadata' )->willReturn( $extendedMetadata );
		$metadataService->method( 'getApiMetadata' )->willReturn( $apiMetadata );
		return $metadataService;
	}

	private function getSuggestionData( string $filename, array $overrides = [] ): array {
		return array_replace( [
			'filename' => $filename,
			'projects' => [ 'enwiki', 'cswiki' ],
			'source' => 'wikipedia',
		], $overrides );
	}

	private function getMockSiteLookup(): SiteStore {
		$siteLookup = $this->createMock( SiteStore::class );
		$siteLookup->method( 'getSite' )->willReturnCallback(
			function ( $siteId ) {
				$site = $this->createMock( Site::class );
				$site->method( 'getLanguageCode' )->willReturnCallback(
					static function () use ( $siteId ) {
						return str_replace( 'wiki', '', $siteId );
					}
				);
				return $site;
			} );
		return $siteLookup;
	}
}
