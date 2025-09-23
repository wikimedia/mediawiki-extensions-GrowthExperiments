<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationDataValidator;
use MediaWiki\Status\Status;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * These are integration tests instead of unit because of the usage of {@see File::normalizeTitle}.
 *
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationDataValidator
 */
class ImageRecommendationDataValidatorTest extends MediaWikiIntegrationTestCase {

	public function testValidate() {
		$imageRecommendationData = new ImageRecommendationData(
			'image.jpg', 'wikipedia', 'enwiki, dewiki', '1.23'
		);
		$this->assertStatusGood(
			ImageRecommendationDataValidator::validate( 'test', $imageRecommendationData )
		);
	}

	/**
	 * @dataProvider dataProvider
	 * @param array $data API response data.
	 * @param string $expectedMessage
	 */
	public function testValidateError( array $data, string $expectedMessage ) {
		$imageRecommendationData = new ImageRecommendationData(
			$data['filename'], $data['source'], $data['projects'], $data['datasetId']
		);
		$status = ImageRecommendationDataValidator::validate( 'test', $imageRecommendationData );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertSame( $expectedMessage, Status::wrap( $status )->getWikiText( false, false, 'en' ) );
	}

	public static function dataProvider() {
		return [
			'invalid filename: boolean' => [
				[
					'filename' => true,
					'source' => 'wikipedia',
					'projects' => 'enwiki, dewiki',
					'datasetId' => '1.23',
				], 'Invalid filename format for test: [type] boolean',
			],
			'invalid filename: array' => [
				[
					'filename' => [],
					'source' => 'wikipedia',
					'projects' => 'enwiki, dewiki',
					'datasetId' => '1.23',
				], 'Invalid filename format for test: [type] array',
			],
			'invalid source' => [
				[
					'filename' => 'image.png',
					'source' => 'wiki',
					'projects' => 'enwiki, dewiki',
					'datasetId' => '1.23',
				], 'Invalid source type for test: wiki',
			],
			'invalid projects' => [
				[
					'filename' => 'image.png',
					'source' => 'wikipedia',
					'projects' => null,
					'datasetId' => '1.23',
				], 'Invalid projects format for test',
			],
			'invalid datasetId' => [
				[
					'filename' => 'image.png',
					'source' => 'wikipedia',
					'projects' => 'enwiki',
					'datasetId' => 1,
				], 'Invalid datasetId format for test',
			],
			'multiple validation errors' => [
				[
					'filename' => [],
					'source' => 'wiki',
					'projects' => null,
					'datasetId' => 1,
				], 'Invalid filename format for test: [type] array',
			],
		];
	}
}
