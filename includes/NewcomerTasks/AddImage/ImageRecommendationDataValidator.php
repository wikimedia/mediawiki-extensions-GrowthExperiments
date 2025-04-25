<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use MediaWiki\FileRepo\File\File;
use MediaWiki\Language\RawMessage;
use StatusValue;

/**
 * Validate image recommendation data
 */
class ImageRecommendationDataValidator {

	/**
	 * Check whether the specified title is valid
	 *
	 * @param string $filename
	 * @return bool
	 */
	private static function isValidTitle( string $filename ): bool {
		return (bool)File::normalizeTitle( $filename );
	}

	/**
	 * Return StatusValue with validation errors in the recommendation data
	 *
	 * @param string $titleTextSafe
	 * @param ImageRecommendationData $imageRecommendationData
	 *
	 * @return StatusValue
	 */
	public static function validate(
		string $titleTextSafe, ImageRecommendationData $imageRecommendationData
	): StatusValue {
		$filename = $imageRecommendationData->getFilename();
		if ( !is_string( $filename ) || !self::isValidTitle( $filename ) ) {
			$invalidFilename = is_string( $filename ) ?
				strip_tags( $filename ) :
				'[type] ' . gettype( $filename );
			return StatusValue::newFatal( new RawMessage(
				'Invalid filename format for $1: $2',
				[ $titleTextSafe, $invalidFilename ]
			) );
		}

		$source = $imageRecommendationData->getSource();
		if ( !in_array( $source, ImageRecommendationImage::KNOWN_SOURCES, true ) ) {
			return StatusValue::newFatal( new RawMessage(
				'Invalid source type for $1: $2',
				[ $titleTextSafe, strip_tags( $source ) ]
			) );
		}

		if ( !is_string( $imageRecommendationData->getProjects() ) ) {
			return StatusValue::newFatal( new RawMessage(
				'Invalid projects format for $1',
				[ $titleTextSafe ]
			) );
		}

		if ( !is_string( $imageRecommendationData->getDatasetId() ) ) {
			return StatusValue::newFatal( new RawMessage(
				'Invalid datasetId format for $1',
				[ $titleTextSafe ]
			) );
		}

		if ( !is_int( $imageRecommendationData->getSectionNumber() )
			&& $imageRecommendationData->getSectionNumber() !== null
		) {
			return StatusValue::newFatal( new RawMessage(
				'Invalid section number for $1',
				[ $titleTextSafe ]
			) );
		}

		if ( !is_string( $imageRecommendationData->getSectionTitle() )
			&& $imageRecommendationData->getSectionTitle() !== null
		) {
			return StatusValue::newFatal( new RawMessage(
				'Invalid section title for $1',
				[ $titleTextSafe ]
			) );
		}

		return StatusValue::newGood();
	}
}
