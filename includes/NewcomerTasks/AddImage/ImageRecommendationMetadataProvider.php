<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use StatusValue;

class ImageRecommendationMetadataProvider {

	/** @var ImageRecommendationMetadataService */
	private $service;

	/** @var string[] */
	private $languages;

	/**
	 * ImageRecommendationMetadataProvider constructor.
	 *
	 * @param ImageRecommendationMetadataService $service
	 * @param string $wikiLanguage
	 * @param string[] $fallbackLanguages
	 */
	public function __construct(
		ImageRecommendationMetadataService $service,
		string $wikiLanguage,
		array $fallbackLanguages
	) {
		$this->service = $service;
		$this->languages = array_merge( [ $wikiLanguage ], $fallbackLanguages );
	}

	/**
	 * Get the description string from the specified description metadata in the content language,
	 * or in one of the fallback languages. Return null if the description is not available
	 *
	 * @param array $extendedMetadata
	 * @return string|null
	 */
	private function getDescriptionValue( array $extendedMetadata ): ?string {
		if ( isset( $extendedMetadata['ImageDescription']['value'] ) ) {
			$descriptionData = $extendedMetadata['ImageDescription']['value'];
			foreach ( $this->languages as $language ) {
				if ( isset( $descriptionData[$language] ) ) {
					return $descriptionData[$language];
				}
			}
		}
		return null;
	}

	/**
	 * Get metadata for the specified image file name
	 *
	 * @param string $fileName
	 * @return array|StatusValue
	 */
	public function getMetadata( string $fileName ) {
		$extendedMetadata = $this->service->getExtendedMetadata( $fileName );
		$fileMetadata = $this->service->getFileMetadata( $fileName );
		if ( $fileMetadata instanceof StatusValue ) {
			return $fileMetadata;
		}
		return [
			'description' => $this->getDescriptionValue( $extendedMetadata ),
		] + $fileMetadata;
	}
}
