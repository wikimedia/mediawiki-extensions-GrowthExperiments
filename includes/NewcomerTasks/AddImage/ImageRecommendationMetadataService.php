<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use FormatMetadata;
use GrowthExperiments\Util;
use MediaTransformError;
use MediaWiki\Http\HttpRequestFactory;
use RepoGroup;
use StatusValue;

/**
 * Fetch and process metadata for image recommendation
 */
class ImageRecommendationMetadataService {

	/** @var int */
	private const THUMB_WIDTH = 120;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var string[] */
	private $mediaInfoRepos;

	/** @var string */
	private $contentLanguage;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param RepoGroup $repoGroup
	 * @param string[] $mediaInfoRepos List of repo names which provide WikibaseMediaInfo data.
	 * @param string $contentLanguage Language code of wiki content language
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		RepoGroup $repoGroup,
		array $mediaInfoRepos,
		$contentLanguage
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->repoGroup = $repoGroup;
		$this->mediaInfoRepos = $mediaInfoRepos;
		$this->contentLanguage = $contentLanguage;
	}

	/**
	 * Fetch extended metadata for the current file
	 *
	 * @param string $fileName Image file name for which to fetch extended metadata.
	 * @return array|StatusValue On success, the extended metadata, as returned by
	 *    FormatMetadata::fetchExtendedMetadata()
	 */
	public function getExtendedMetadata( string $fileName ) {
		$file = $this->repoGroup->findFile( $fileName );
		if ( $file ) {
			return ( new FormatMetadata )->fetchExtendedMetadata( $file );
		}
		return StatusValue::newFatal( 'rawmessage', 'Image file not found: '
			. $fileName );
	}

	/**
	 * Get metadata for the specified image file name
	 *
	 * @param string $fileName
	 * @return array|StatusValue On success, an array of file metadata. See
	 *   {@see ImageRecommendationMetadataProvider::getMetadata()} for details.
	 */
	public function getFileMetadata( string $fileName ) {
		$file = $this->repoGroup->findFile( $fileName );
		if ( !$file ) {
			return StatusValue::newFatal( 'rawmessage', 'Image file not found: '
				. $fileName );
		} else {
			$thumb = $file->transform( [ 'width' => self::THUMB_WIDTH ] );
			if ( !$thumb ) {
				return StatusValue::newFatal( 'rawmessage', 'Thumbnailing error' );
			} elseif ( $thumb instanceof MediaTransformError ) {
				return StatusValue::newFatal( 'rawmessage', 'Thumbnailing error: '
					. $thumb->toText() );
			}
		}
		return [
			'descriptionUrl' => $file->getDescriptionUrl(),
			'thumbUrl' => $thumb->getUrl(),
			'fullUrl' => $file->getUrl(),
			'originalWidth' => $file->getWidth(),
			'originalHeight' => $file->getHeight(),
			'mustRender' => $file->mustRender(),
			'isVectorized' => $file->isVectorized(),
		];
	}

	/**
	 * Get action=query API metadata for the specified image file name:
	 * - category data
	 * - pageterms (WikibaseMediaInfo) data (e.g. structured data on Commons)
	 * @param string $fileName
	 * @return array|StatusValue On success, an array of file metadata. See
	 *   {@see ImageRecommendationMetadataProvider::getMetadata()} for details.
	 */
	public function getApiMetadata( string $fileName ) {
		$file = $this->repoGroup->findFile( $fileName );
		if ( !$file ) {
			return StatusValue::newFatal( 'rawmessage', 'Image file not found: '
				. $fileName );
		}
		$repoName = $file->getRepoName();
		if ( !in_array( $repoName, $this->mediaInfoRepos, true ) ) {
			return [];
		}
		$apiUrl = $file->getRepo()->makeUrl( '', 'api' );
		$status = Util::getApiUrl( $this->httpRequestFactory, $apiUrl, [
			'action' => 'query',
			'titles' => $file->getTitle()->getPrefixedDBkey(),
			'prop' => 'categories|pageterms',
			'clshow' => '!hidden',
			'cllimit' => 'max',
			'wbptterms' => 'label',
			'wbptlanguage' => $this->contentLanguage,
		], true );
		if ( !$status->isOK() ) {
			return $status;
		}
		$data = $status->getValue();

		return [
			'caption' => $data['query']['pages'][0]['terms']['label'][0] ?? null,
			'categories' => array_map( static function ( $categoryData ) {
				$title = $categoryData['title'];
				// strip namespace prefix
				return explode( ':', $title, 2 )[1];
			}, $data['query']['pages'][0]['categories'] ?? [] ),
		];
	}

}
