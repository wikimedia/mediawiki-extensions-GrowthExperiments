<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use DerivativeContext;
use Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWikiUnitTestCase;
use Message;
use PHPUnit\Framework\MockObject\MockObject;
use Site;
use SiteStore;
use StatusValue;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider
 */
class ImageRecommendationMetadataProviderTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getMetadata
	 */
	public function testGetMetadataFileNotFound() {
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$this->getMockServiceError(),
			'en',
			[],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$this->assertTrue(
			$metadataProvider->getMetadata(
				$this->getSuggestionData( 'Image.jpg' )
			) instanceof StatusValue
		);
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testGetMetadataContentLanguage() {
		$enDescription = 'English';
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService( $mockFileMetadata, $this->getImageDescription( [
			'en' => $enDescription
		] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$metadata = $metadataProvider->getMetadata( $this->getSuggestionData( 'Image.jpg' ) );
		$this->assertArrayEquals(
			[
				'description' => $enDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
				'mustRender' => true,
				'isVectorized' => false,
				'reason' => 'growthexperiments-addimage-reason-wikipedia-languages'
			],
			$metadata
		);
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testGetMetadataFallbackLanguage() {
		$skDescription = 'Slovensko';
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService( $mockFileMetadata, $this->getImageDescription( [
			'en' => 'English',
			'sk' => $skDescription
		] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'cs',
			[ 'sk', 'en' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$this->assertArrayEquals(
			[
				'description' => $skDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
				'mustRender' => true,
				'isVectorized' => false,
				'reason' => 'growthexperiments-addimage-reason-wikipedia-languages'
			],
			$metadataProvider->getMetadata( $this->getSuggestionData( 'Image.jpg' ) )
		);
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testGetMetadataFallbackLanguageOrder() {
		$enDescription = 'English';
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService( $mockFileMetadata, $this->getImageDescription( [
			'en' => $enDescription,
			'sk' => 'Slovensko'
		] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'cs',
			[ 'en', 'sk' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$this->assertArrayEquals(
			[
				'description' => $enDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
				'mustRender' => true,
				'isVectorized' => false,
				'reason' => 'growthexperiments-addimage-reason-wikipedia-languages'
			],
			$metadataProvider->getMetadata( $this->getSuggestionData( 'Image.jpg' ) )
		);
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testGetMetadata() {
		$enDescription = 'English';
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
			'en' => $enDescription
		] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$this->assertArrayEquals(
			[
				'description' => $enDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
				'mustRender' => true,
				'isVectorized' => false,
				'reason' => 'growthexperiments-addimage-reason-wikipedia-languages'
			],
			$metadataProvider->getMetadata( $this->getSuggestionData( 'HMS_Pandora.jpg' ) )
		);
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testWikidataSuggestionReason() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$actualReason = $metadataProvider->getMetadata(
			$this->getSuggestionData( 'Image.jpg', [ 'source' => 'wikidata' ] )
		)['reason'];
		$this->assertEquals( 'growthexperiments-addimage-reason-wikidata', $actualReason );
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testCommonsSuggestionReason() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer(),
			$this->getMockSiteLookup()
		);
		$actualReason = $metadataProvider->getMetadata(
			$this->getSuggestionData( 'Image.jpg', [ 'source' => 'commons' ] )
		)['reason'];
		$this->assertEquals( 'growthexperiments-addimage-reason-commons', $actualReason );
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testWikipediaSingleProjectSuggestionReason() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer( false ),
			$this->getMockSiteLookup()
		);
		$actualReason = $metadataProvider->getMetadata(
			$this->getSuggestionData( 'Image.jpg', [ 'projects' => [ 'cswiki' ] ] )
		)['reason'];
		$this->assertEquals( 'Used in the same article in Czech Wikipedia', $actualReason );
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testWikipediaMultipleProjectsSuggestionReason() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer( false ),
			$this->getMockSiteLookup()
		);
		$metadata = $metadataProvider->getMetadata( $this->getSuggestionData(
			'Image.jpg',
				[ 'projects' => [ 'cswiki', 'frwiki' ] ]
		) );
		$this->assertEquals(
			'Used in the same article in 2 other languages',
			$metadata['reason']
		);
	}

	/**
	 * @covers ::getMetadata
	 */
	public function testWikipediaMultipleProjectsFallbackSuggestionReason() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
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
	 * @covers ::getShownLanguageCodes
	 */
	public function testSuggestionReasonShownLanguages() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'en', 'cs', 'sk' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer( false ),
			$this->getMockSiteLookup()
		);
		$this->assertArrayEquals(
			[ 'cs', 'sk' ],
			$metadataProvider->getShownLanguageCodes( [ 'cs', 'fr', 'sk', 'ar' ], 2 )
		);
	}

	/**
	 * @covers ::getShownLanguageCodes
	 */
	public function testSuggestionReasonShownLanguagesNoFallback() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer( false ),
			$this->getMockSiteLookup()
		);
		$this->assertArrayEquals(
			[ 'cs', 'fr' ],
			$metadataProvider->getShownLanguageCodes( [ 'cs', 'fr', 'sk', 'ar' ], 2 )
		);
	}

	/**
	 * @covers ::getShownLanguageCodes
	 */
	public function testSuggestionReasonShownLanguagesFallback() {
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService(
			$mockFileMetadata,
			$this->getImageDescription( [
				'en' => 'Description'
			] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[ 'cs', 'sk' ],
			$this->getMockLanguageNameUtils(),
			$this->getMockMessageLocalizer( false ),
			$this->getMockSiteLookup()
		);
		$this->assertArrayEquals(
			[ 'cs', 'sk', 'fr' ],
			$metadataProvider->getShownLanguageCodes( [ 'cs', 'fr', 'sk', 'ar' ], 3 )
		);
	}

	private function getImageDescription( array $description = [] ): array {
		return [
			'ImageDescription' => [
				'value' => $description
			]
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
		array $extendedMetadata = []
	): ImageRecommendationMetadataService {
		$metadataService = $this->createMock(
			ImageRecommendationMetadataService::class
		);
		$metadataService->method( 'getFileMetadata' )->willReturn( $fileMetadata );
		$metadataService->method( 'getExtendedMetadata' )->willReturn( $extendedMetadata );
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
			'fr' => 'French'
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
			'source' => 'wikipedia'
		], $overrides );
	}

	private static function getMessageValue( string $key, array $params ): string {
		switch ( $key ) {
			case 'project-localized-name-cswiki':
				return 'Czech Wikipedia';
			case 'project-localized-name-frwiki':
				return 'French Wikipedia';
			case 'growthexperiments-addimage-reason-wikipedia-project':
				return 'Used in the same article in ' . $params[0];
			case 'growthexperiments-addimage-reason-wikipedia-languages':
				return 'Used in the same article in ' . $params[0]['num'] . ' other languages';
			default:
				return $key;
		}
	}

	private static function updateMessageMock(
		MockObject $message,
		string $key,
		array $params,
		bool $shouldReturnKey
	): MockObject {
		$message->method( 'exists' )->willReturn( true );
		$message->method( 'text' )->willReturnCallback(
			static function () use ( $key, $params, $shouldReturnKey ) {
				if ( $shouldReturnKey ) {
					return $key;
				}
				return ImageRecommendationMetadataProviderTest::getMessageValue( $key, $params );
			}
		);
		return $message;
	}

	private function getMockMessageLocalizer( bool $shouldReturnKey = true ): DerivativeContext {
		$localizer = $this->createMock( DerivativeContext::class );
		$context = $this;
		$localizer->method( 'msg' )->willReturnCallback(
			static function ( $key, ...$params ) use ( $context, $shouldReturnKey ) {
				$message = $context->createMock( Message::class );
				$message->method( 'numParams' )->willReturn( $message );
				return ImageRecommendationMetadataProviderTest::updateMessageMock(
					$message, $key, $params, $shouldReturnKey
				);
			}
		);
		$mockLanguage = $this->createMock( Language::class );
		$mockLanguage->method( 'getCode' )->willReturn( 'en' );
		$localizer->method( 'getLanguage' )->willReturn( $mockLanguage );
		return $localizer;
	}

	private function getMockSiteLookup(): SiteStore {
		$siteLookup = $this->createMock( SiteStore::class );
		$context = $this;
		$siteLookup->method( 'getSite' )->willReturnCallback(
			static function ( $siteId ) use ( $context ) {
				$site = $context->createMock( Site::class );
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
