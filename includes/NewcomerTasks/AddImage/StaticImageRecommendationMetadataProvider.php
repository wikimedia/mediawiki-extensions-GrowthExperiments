<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

/**
 * Static metadata provider, used only for testing.
 */
class StaticImageRecommendationMetadataProvider extends ImageRecommendationMetadataProvider {

	/** @inheritDoc */
	public function getMetadata( array $suggestion ) {
		return $this->getStaticData();
	}

	/** @inheritDoc */
	public function getFileMetadata( string $filename ) {
		return $this->getStaticData();
	}

	/**
	 * TODO: Parametrize this function.
	 */
	private function getStaticData(): array {
		return [
			'descriptionUrl' => 'https://commons.wikimedia.org/wiki/File:Mamoul_biscotti_libanesi.jpg',
			'thumbUrl' =>
				'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Mamoul_biscotti_l' .
				'ibanesi.jpg/120px-Mamoul_biscotti_libanesi.jpg',
			'fullUrl' => 'https://upload.wikimedia.org/wikipedia/commons/9/9e/Mamoul_biscotti_libanesi.jpg',
			'originalWidth' => 300,
			'originalHeight' => 300,
			'mustRender' => true,
			'isVectorized' => false,
			'mediaType' => 'BITMAP'
		];
	}

}
