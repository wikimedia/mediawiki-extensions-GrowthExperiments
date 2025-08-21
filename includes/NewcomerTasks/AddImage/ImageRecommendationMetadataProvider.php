<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use InvalidArgumentException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Message\Message;
use MediaWiki\Site\SiteLookup;
use StatusValue;
use Wikimedia\Message\ListType;

class ImageRecommendationMetadataProvider {

	/** @var ImageRecommendationMetadataService */
	private $service;

	/** @var string[] */
	private $languages;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var DerivativeContext */
	private $localizer;

	/** @var SiteLookup */
	private $siteLookup;

	/** @var string */
	private $contentLanguage;

	/** @var int Number of languages to show in the suggestion reason */
	private const SUGGESTION_REASON_PROJECTS_SHOWN = 2;

	/**
	 * @param ImageRecommendationMetadataService $service
	 * @param string $wikiLanguage
	 * @param string[] $fallbackLanguages
	 * @param LanguageNameUtils $languageNameUtils
	 * @param DerivativeContext $localizer
	 * @param SiteLookup $siteLookup
	 */
	public function __construct(
		ImageRecommendationMetadataService $service,
		string $wikiLanguage,
		array $fallbackLanguages,
		LanguageNameUtils $languageNameUtils,
		DerivativeContext $localizer,
		SiteLookup $siteLookup
	) {
		$this->service = $service;
		$this->languages = array_merge( [ $wikiLanguage ], $fallbackLanguages );
		$this->languageNameUtils = $languageNameUtils;
		$this->localizer = $localizer;
		$this->siteLookup = $siteLookup;
		$this->contentLanguage = $wikiLanguage;
	}

	/**
	 * Returns an array with fields extracted from extended metadata fields. See
	 * {@see ImageRecommendationMetadataProvider::getMetadata()} for details.
	 *
	 * @param array $extendedMetadata
	 * @return array
	 */
	private function filterExtendedMetadata( array $extendedMetadata ): array {
		return [
			// description field of {{information}} template - see
			// https://commons.wikimedia.org/wiki/Template:Information
			'description' => $this->getExtendedMetadataField( $extendedMetadata, 'ImageDescription' ),
			// author field of {{information}} template
			'author' => $this->getExtendedMetadataField( $extendedMetadata, 'Artist' ),
			// short name like 'CC BY-SA 4.0',  typically parsed from the first license template on the page
			'license' => $this->getExtendedMetadataField( $extendedMetadata, 'LicenseShortName' ),
			// DateTimeOriginal is the date field of {{information}} template.
			// DateTime is image creation date from EXIF or similar embedded metadata, with fallback
			// to the file upload date.
			'date' => $this->getExtendedMetadataField( $extendedMetadata, 'DateTimeOriginal' )
				?? $this->getExtendedMetadataField( $extendedMetadata, 'DateTime' ),
		];
	}

	/**
	 * @param array $extendedMetadata
	 * @param string $fieldName
	 * @return string|null
	 */
	private function getExtendedMetadataField( array $extendedMetadata, string $fieldName ) {
		if ( isset( $extendedMetadata[$fieldName]['value'] ) ) {
			$value = $extendedMetadata[$fieldName]['value'];
			if ( !is_array( $value ) ) {
				return $value;
			}
			// Array means the field is multilingual, we need to select the best language.
			foreach ( $this->languages as $language ) {
				if ( isset( $value[$language] ) ) {
					return $value[$language];
				}
			}
			// None of the languages are relevant to the user, we can't really rank them.
			// Just pick the first one.
			return $value ? reset( $value ) : null;
		}
		return null;
	}

	/**
	 * Construct the suggestion reason string when the suggested image is found in another project.
	 * Only return the localized string if the localized project name is available.
	 *
	 * @param string $projectId Wiki ID
	 * @param string $source 'wikipedia', 'wikidata-section-alignment' (see the
	 *   ImageRecommendationImage constants)
	 * @return string|null
	 */
	private function getWikipediaReasonOtherProject( string $projectId, string $source ): ?string {
		// Localized project name is from WikimediaMessages extension.
		$projectName = $this->localizer->msg( 'project-localized-name-' . $projectId );
		if ( $projectName->exists() ) {
			return $this->localizer->msg(
				"growthexperiments-addimage-reason-$source-project",
				$projectName->text()
			)->text();
		}
		return null;
	}

	/**
	 * Get an array of language codes for the projects in which the image suggestion is used.
	 *
	 * @param string[] $projects Projects in which the image suggestion is used
	 * @return array
	 */
	private function getLanguageCodesFromProjects( array $projects ): array {
		$siteLookup = $this->siteLookup;
		return array_reduce( $projects,
			static function ( array $result, string $projectId )
			use ( $siteLookup ) {
				$site = $siteLookup->getSite( $projectId );
				// SiteLookup::getSite and Site::getLanguageCode can return null.
				$languageCode = $site ? $site->getLanguageCode() : null;
				if ( is_string( $languageCode ) && strlen( $languageCode ) ) {
					$result[] = $languageCode;
				}
				return $result;
			}, [] );
	}

	/**
	 * Get an array of language codes to show in the suggestion reason, sorted by the fallback
	 * languages chain
	 *
	 * @param string[] $languageCodes Language codes of the projects in which the image is found
	 * @param int $targetCount Number of languages to show in the string
	 * @return array
	 */
	public function getShownLanguageCodes( array $languageCodes, int $targetCount ): array {
		$shownLanguageCodes = [];
		foreach ( $this->languages as $fallbackLanguage ) {
			if ( in_array( $fallbackLanguage, $languageCodes ) ) {
				$shownLanguageCodes[] = $fallbackLanguage;
				if ( count( $shownLanguageCodes ) === $targetCount ) {
					return $shownLanguageCodes;
				}
			}
		}
		return array_merge(
			$shownLanguageCodes,
			array_slice( array_diff( $languageCodes, $shownLanguageCodes ),
				0,
				$targetCount - count( $shownLanguageCodes )
			)
		);
	}

	/**
	 * Get the parameter for the concatenated list of languages shown in the suggestion reason.
	 * The parameter is used with "growthexperiments-addimage-reason-wikipedia-languages" key.
	 *
	 * @param string[] $languageCodes Language codes of the projects in which the image is found
	 * @return mixed
	 */
	private function getLanguagesListParam( array $languageCodes ) {
		$totalLanguages = count( $languageCodes );
		$shownLanguageCodes = $this->getShownLanguageCodes(
			$languageCodes,
			self::SUGGESTION_REASON_PROJECTS_SHOWN
		);
		$inLanguage = $this->localizer->getLanguage()->getCode();
		$shownLanguages = array_reduce( $shownLanguageCodes, function (
			array $result,
			string $languageCode
		) use ( $inLanguage ) {
			$languageName = $this->languageNameUtils->getLanguageName( $languageCode, $inLanguage );
			if ( $languageName ) {
				$result[] = $languageName;
			}
			return $result;
		}, [] );
		$otherLanguagesCount = $totalLanguages - count( $shownLanguages );

		if ( $otherLanguagesCount ) {
			$shownLanguages[] = $this->localizer->msg(
				'growthexperiments-addimage-reason-wikipedia-languages-others'
			)->numParams( $otherLanguagesCount )->text();
		}
		return Message::listParam( $shownLanguages, ListType::AND );
	}

	/**
	 * Construct the suggestion reason string when the suggested image is found in other projects
	 *
	 * @param string[] $projects Wiki IDs of projects in which the image suggestion is used
	 * @param string $source 'wikipedia' or 'wikidata-section-alignment' (see the
	 *   ImageRecommendationImage constants)
	 * @return string
	 */
	private function getWikipediaReason( array $projects, string $source ): string {
		$totalCount = count( $projects );
		if ( $totalCount === 1 ) {
			$reason = $this->getWikipediaReasonOtherProject( $projects[0], $source );
			if ( $reason ) {
				return $reason;
			}
		}

		$languageCodes = $this->getLanguageCodesFromProjects( $projects );
		if ( count( $languageCodes ) === 0 ) {
			return $this->localizer->msg( "growthexperiments-addimage-reason-$source" )
				->numParams( $totalCount )->text();
		}
		return $this->localizer->msg(
			"growthexperiments-addimage-reason-$source-languages",
				Message::numParam( $totalCount ),
				$this->getLanguagesListParam( $languageCodes )
			)->text();
	}

	/**
	 * Construct the suggestion reason string when the suggested image is based on other projects
	 *
	 * @param string[] $projects Wiki IDs of projects in which the image suggestion is used
	 * @return string
	 */
	private function getWikidataSectionIntersectionReason( $projects ): string {
		$firstProject = $projects[0];
		// Localized project name is from WikimediaMessages extension.
		$projectName = $this->localizer->msg( 'project-localized-name-' . $firstProject );
		if ( $projectName->exists() ) {
			if ( count( $projects ) === 1 ) {
				return $this->localizer->msg(
					'growthexperiments-addimage-reason-wikidata-section-intersection-single',
					$projectName
				)->text();
			} else {
				return $this->localizer->msg(
					'growthexperiments-addimage-reason-wikidata-section-intersection-multiple',
					$projectName,
					Message::numParam( count( $projects ) - 1 )
				)->text();
			}
		} else {
			return $this->localizer->msg(
				'growthexperiments-addimage-reason-wikidata-section-intersection-languages',
				Message::numParam( count( $projects ) )
			)->text();
		}
	}

	/**
	 * Get the localized string for suggestion reason
	 *
	 * @param array $suggestion Suggestion data
	 * @return string
	 */
	private function getSuggestionReason( array $suggestion ): string {
		$source = $suggestion['source'];
		if ( $source === ImageRecommendationImage::SOURCE_WIKIDATA ) {
			return $this->localizer->msg( 'growthexperiments-addimage-reason-wikidata' )->text();
		} elseif ( $source === ImageRecommendationImage::SOURCE_COMMONS ) {
			return $this->localizer->msg( 'growthexperiments-addimage-reason-commons' )->text();
		} elseif ( $source === ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_TOPICS ) {
			return $this->localizer->msg( 'growthexperiments-addimage-reason-wikidata-section-topics' )->text();
		} elseif ( $source === ImageRecommendationImage::SOURCE_WIKIPEDIA
			|| $source === ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_ALIGNMENT
		) {
			return $this->getWikipediaReason( $suggestion['projects'], $source );
		} elseif ( $source === ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_INTERSECTION ) {
			return $this->getWikidataSectionIntersectionReason( $suggestion['projects'] );
		}
		throw new InvalidArgumentException( "Unknown suggestion source: $source" );
	}

	/**
	 * Get the name of the content language localized in the user's language
	 */
	private function getLocalizedContentLanguage(): string {
		$inLanguage = $this->localizer->getLanguage()->getCode();
		return $this->languageNameUtils->getLanguageName( $this->contentLanguage, $inLanguage );
	}

	/**
	 * @param string $filename
	 * @return array|StatusValue
	 */
	public function getFileMetadata( string $filename ) {
		return $this->service->getFileMetadata( $filename );
	}

	/**
	 * Get metadata for the specified image file name
	 *
	 * @param array $suggestion Suggestion data, as returned by the API.
	 * @return array|StatusValue On success, an array with the following fields:
	 *   Image metadata:
	 *   - descriptionUrl: image description page URL
	 *   - thumbUrl: URL to image scaled to THUMB_WIDTH
	 *   - fullUrl: URL to original image
	 *   - originalWidth: full image width
	 *   - originalHeight: full image height
	 *   - mustRender: true if the original image wouldn't display correctly in a browser
	 *   - isVectorized: whether the image is a vector image (ie. has no max size)
	 *   Extended metadata:
	 *   - description: image description in content language, or null. Might contain HTML.
	 *   - author: original author of image, in content language, or null. Might contain HTML.
	 *   - license: short license name, in content language, or null. Might contain HTML.
	 *   - date: date of original image creation. Can be pretty much any format - ISO timestamp,
	 *     text in any language, HTML. Always present.
	 *   Metadata from the API of the image host:
	 *   - caption: MediaInfo caption as a plaintext string in the current wiki's content language,
	 *     or null.
	 *   - categories: non-hidden categories of the image as an array of unprefixed title strings
	 *     with spaces.
	 *   Other:
	 *   - reason: a human-readable representation of the suggestion's 'source' and 'project' fields.
	 */
	public function getMetadata( array $suggestion ) {
		$filename = $suggestion['filename'];
		$fileMetadata = $this->service->getFileMetadata( $filename );
		$extendedMetadata = $this->service->getExtendedMetadata( $filename );
		$apiMetadata = $this->service->getApiMetadata( $filename );
		foreach ( [ $fileMetadata, $extendedMetadata, $apiMetadata ] as $metadata ) {
			if ( $metadata instanceof StatusValue ) {
				return $metadata;
			}
		}
		return $fileMetadata + $this->filterExtendedMetadata( $extendedMetadata ) + $apiMetadata + [
				'reason' => $this->getSuggestionReason( $suggestion ),
				'contentLanguageName' => $this->getLocalizedContentLanguage(),
		];
	}
}
