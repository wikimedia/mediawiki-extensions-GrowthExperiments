<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use FormatMetadata;
use RepoGroup;
use StatusValue;

/**
 * Fetch and process metadata for image recommendation
 */
class ImageRecommendationMetadataService {

	/** @var int */
	private const THUMB_WIDTH = 120;

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * ImageRecommendationMetadataService constructor.
	 *
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( RepoGroup $repoGroup ) {
		$this->repoGroup = $repoGroup;
	}

	/**
	 * Fetch extended metadata for the current file
	 *
	 * @param string $fileName Image file name for which to fetch extended metadata.
	 * @return array|StatusValue
	 */
	public function getExtendedMetadata( string $fileName ) {
		$file = $this->repoGroup->findFile( $fileName );
		if ( $file ) {
			return ( new FormatMetadata )->fetchExtendedMetadata( $file );
		}
		return StatusValue::newFatal( 'rawmessage', 'Image file not found' );
	}

	/**
	 * Get metadata for the specified image file name
	 *
	 * @param string $fileName
	 * @return array|StatusValue
	 */
	public function getFileMetadata( string $fileName ) {
		$file = $this->repoGroup->findFile( $fileName );
		if ( $file ) {
			return [
				'descriptionUrl' => $file->getDescriptionUrl(),
				'thumbUrl' => $file->transform( [ 'width' => self::THUMB_WIDTH ] )->getUrl(),
				'fullUrl' => $file->getUrl(),
				'originalWidth' => $file->getWidth(),
				'originalHeight' => $file->getHeight(),
				'mustRender' => $file->mustRender(),
				'isVectorized' => $file->isVectorized(),
			];
		}
		return StatusValue::newFatal( 'rawmessage', 'Image file not found' );
	}
}
