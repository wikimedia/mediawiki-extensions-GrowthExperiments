<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use File;
use MediaWiki\Language\RawMessage;
use MWException;
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
	 * @throws MWException
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
	 * @throws MWException
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
		if ( !in_array( $source, [
			ImageRecommendationImage::SOURCE_WIKIDATA,
			ImageRecommendationImage::SOURCE_WIKIPEDIA,
			ImageRecommendationImage::SOURCE_COMMONS,
		], true ) ) {
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

		return StatusValue::newGood();
	}
}
