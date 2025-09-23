<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataService;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Site\Site;
use MediaWiki\Site\SiteStore;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider
 */
class ImageRecommendationMetadataProviderTest extends MediaWikiUnitTestCase {

	public function testGetMetadata() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [] ),
			[ 'categories' => [ 'A', 'B' ] ]
		);
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$metadata = $metadataProvider->getMetadata( $this->getSuggestionData( 'HMS_Pandora.jpg' ) );
		$this->assertArrayEquals(
			[
				'description' => null,
				'author' => null,
				'license' => null,
				'date' => null,
				'categories' => [ 'A', 'B' ],
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
				'mustRender' => true,
				'isVectorized' => false,
				'reason' => 'growthexperiments-addimage-reason-wikipedia-languages',
				'contentLanguageName' => 'English',
			],
			$metadata
		);
	}

	public function testGetMetadata_FileNotFound() {
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$this->getMockServiceError(),
			'en',
			[],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$metadata = $metadataProvider->getMetadata( $this->getSuggestionData( 'Image.jpg' ) );
		$this->assertInstanceOf( StatusValue::class, $metadata );
	}

	/**
	 * @dataProvider provideGetMetadata_Language
	 */
	public function testGetMetadata_Language(
		string $wikiLanguage,
		array $fallbackChain,
		array $imageDescription,
		string $expectedDescription
	) {
		$metadataService = $this->getMockService( [], $this->getImageDescription( $imageDescription ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			$wikiLanguage,
			$fallbackChain,
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$metadata = $metadataProvider->getMetadata( $this->getSuggestionData( 'Image.jpg' ) );
		$this->assertArrayHasKey( 'description', $metadata );
		$this->assertSame( $expectedDescription, $metadata['description'] );
	}

	public static function provideGetMetadata_Language() {
		return [
			'primary language available' => [
				'wikiLanguage' => 'cs',
				'fallbackChain' => [ 'sk', 'en' ],
				'imageDescription' => [
					'en' => 'English',
					'sk' => 'Slovensko',
					'cs' => 'Čeština',
				],
				'expectedDescription' => 'Čeština',
			],
			'fallback chain language available' => [
				'wikiLanguage' => 'cs',
				'fallbackChain' => [ 'sk', 'en' ],
				'imageDescription' => [
					'en' => 'English',
					'sk' => 'Slovensko',
				],
				'expectedDescription' => 'Slovensko',
			],
			'fallback chain language available #2' => [
				'wikiLanguage' => 'cs',
				'fallbackChain' => [ 'sk', 'en' ],
				'imageDescription' => [
					'en' => 'English',
				],
				'expectedDescription' => 'English',
			],
			'no relevant language available' => [
				'wikiLanguage' => 'cs',
				'fallbackChain' => [ 'sk', 'en' ],
				'imageDescription' => [
					'pt' => 'Português',
					'sr' => 'Српски',
				],
				'expectedDescription' => 'Português',
			],
		];
	}

	/**
	 * @dataProvider provideGetMetadata_Reason
	 */
	public function testGetMetadata_Reason(
		array $suggestionOverrides,
		string $expectedReason,
		bool $expectedReasonIsMessageKey
	) {
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
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer( $expectedReasonIsMessageKey ),
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
			[ [ 'source' => 'wikidata' ], 'growthexperiments-addimage-reason-wikidata', true ],
			[ [ 'source' => 'commons' ], 'growthexperiments-addimage-reason-commons', true ],
			[ [ 'source' => 'wikipedia', 'projects' => [ 'cswiki' ] ],
			  'Used in the same article in Czech Wikipedia', false ],
			[ [ 'source' => 'wikipedia', 'projects' => [ 'cswiki' ] ],
			  'Used in the same article in Czech Wikipedia', false ],
			[ [ 'source' => 'wikipedia', 'projects' => [ 'cswiki', 'frwiki' ] ],
			  'Used in the same article in 2 other languages', false ],
		];
	}

	public function testWikipediaMultipleProjectsFallbackSuggestionReason() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description',
			] ) );
		$siteLookup = $this->createMock( SiteStore::class );
		$siteLookup->method( 'getSite' )->willReturn( null );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$siteLookup
		);
		$metadata = $metadataProvider->getMetadata( $this->getSuggestionData(
			'Image.jpg',
			[ 'projects' => [ 'cswiki', 'frwiki' ] ]
		) );
		$this->assertEquals(
			'growthexperiments-addimage-reason-wikipedia',
			$metadata['reason']
		);
	}

	/**
	 * @dataProvider provideGetShownLanguageCodes
	 */
	public function testGetShownLanguageCodes(
		$languageFallbackChain,
		$suggestionLanguageCodes,
		$targetCount,
		$expectedShownLanguageCodes
	) {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description',
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			$languageFallbackChain[0],
			array_slice( $languageFallbackChain, 1 ),
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer( false ),
			$this->getMockSiteLookup()
		);
		$this->assertArrayEquals(
			$expectedShownLanguageCodes,
			$metadataProvider->getShownLanguageCodes( $suggestionLanguageCodes, $targetCount )
		);
	}

	public static function provideGetShownLanguageCodes() {
		return [
			// language chain, suggestion languages, shown language target count, expected shown languages
			[ [ 'en' ], [ 'cs', 'fr', 'sk', 'ar' ], 2, [ 'cs', 'fr' ] ],
			[ [ 'en', 'cs', 'sk' ], [ 'cs', 'fr', 'sk', 'ar' ], 2, [ 'cs', 'sk' ] ],
			[ [ 'en', 'cs', 'sk' ], [ 'cs', 'fr', 'sk', 'ar' ], 3, [ 'cs', 'sk', 'fr' ] ],
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

	private function getMockServiceError(): ImageRecommendationMetadataService {
		$metadataService = $this->createMock(
			ImageRecommendationMetadataService::class
		);
		$metadataService->method( 'getFileMetadata' )->willReturn(
			StatusValue::newFatal( 'rawmessage', 'Image file not found' )
		);
		$metadataService->method( 'getExtendedMetadata' )->willReturn(
			StatusValue::newFatal( 'rawmessage', 'Image file not found' )
		);
		return $metadataService;
	}

	private function getMockLanguageNameUtils(): LanguageNameUtils {
		$languageNameUtils = $this->createMock(
			LanguageNameUtils::class
		);
		$languageMap = [
			'en' => 'English',
			'cs' => 'Czech',
			'fr' => 'French',
		];
		$languageNameUtils->method( 'getLanguageName' )->willReturnCallback(
			static function ( $languageCode ) use ( $languageMap ) {
				return $languageMap[ $languageCode ];
			}
		);
		return $languageNameUtils;
	}

	private function getSuggestionData( string $filename, array $overrides = [] ): array {
		return array_replace( [
			'filename' => $filename,
			'projects' => [ 'enwiki', 'cswiki' ],
			'source' => 'wikipedia',
		], $overrides );
	}

	private function getMessageValue( string $key ): string {
		switch ( $key ) {
			case 'project-localized-name-cswiki':
				return 'Czech Wikipedia';
			case 'project-localized-name-frwiki':
				return 'French Wikipedia';
			case 'growthexperiments-addimage-reason-wikipedia-project':
				return 'Used in the same article in Czech Wikipedia';
			case 'growthexperiments-addimage-reason-wikipedia-languages':
				return 'Used in the same article in 2 other languages';
			default:
				return $key;
		}
	}

	private function getMockMessageLocalizer( bool $shouldReturnKey = true ): DerivativeContext {
		$localizer = $this->createMock( DerivativeContext::class );
		$localizer->method( 'msg' )->willReturnCallback(
			fn ( $key ) => $this->getMockMessage(
				$shouldReturnKey ? $key : $this->getMessageValue( $key )
			)
		);
		$mockLanguage = $this->createMock( Language::class );
		$mockLanguage->method( 'getCode' )->willReturn( 'en' );
		$localizer->method( 'getLanguage' )->willReturn( $mockLanguage );
		return $localizer;
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
