<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider
 */
class ImageRecommendationMetadataProviderTest extends MediaWikiUnitTestCase {

	public function testGetMetadataFileNotFound() {
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$this->getMockServiceError(),
			'en',
			[]
		);
		$this->assertTrue(
			$metadataProvider->getMetadata( 'Image.jpg' ) instanceof StatusValue
		);
	}

	public function testGetMetadataContentLanguage() {
		$enDescription = 'English';
		$mockFileMetadata = $this->getMockFileMetadata();
		$metadataService = $this->getMockService( $mockFileMetadata, $this->getImageDescription( [
			'en' => $enDescription
		] ) );
		$metadataProvider = new ImageRecommendationMetadataProvider(
			$metadataService,
			'en',
			[]
		);
		$this->assertArrayEquals(
			[
				'description' => $enDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
			],
			$metadataProvider->getMetadata( 'Image.jpg' )
		);
	}

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
			[ 'sk', 'en' ]
		);
		$this->assertArrayEquals(
			[
				'description' => $skDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
			],
			$metadataProvider->getMetadata( 'Image.jpg' )
		);
	}

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
			[ 'en', 'sk' ]
		);
		$this->assertArrayEquals(
			[
				'description' => $enDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
			],
			$metadataProvider->getMetadata( 'Image.jpg' )
		);
	}

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
			[ 'en' ]
		);
		$this->assertArrayEquals(
			[
				'description' => $enDescription,
				'thumbUrl' => $mockFileMetadata['thumbUrl'],
				'fullUrl' => $mockFileMetadata['fullUrl'],
				'descriptionUrl' => $mockFileMetadata['descriptionUrl'],
				'originalWidth' => $mockFileMetadata['originalWidth'],
				'originalHeight' => $mockFileMetadata['originalHeight'],
			],
			$metadataProvider->getMetadata( 'HMS_Pandora.jpg' )
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
}
