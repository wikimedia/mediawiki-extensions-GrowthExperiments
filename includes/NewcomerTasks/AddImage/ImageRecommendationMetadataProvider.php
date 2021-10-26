<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use DerivativeContext;
use MediaWiki\Languages\LanguageNameUtils;
use Message;
use SiteLookup;
use StatusValue;

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

	/** @var int Number of languages to show in the suggestion reason */
	private const SUGGESTION_REASON_PROJECTS_SHOWN = 2;

	/**
	 * ImageRecommendationMetadataProvider constructor.
	 *
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
	 * Construct the suggestion reason string when the suggested image is found in another project.
	 * Only return the localized string if the localized project name is available.
	 *
	 * @param string $projectId
	 * @return string|null
	 */
	private function getWikipediaReasonOtherProject( string $projectId ): ?string {
		// Localized project name is from WikimediaMessages extension.
		$projectName = $this->localizer->msg( 'project-localized-name-' . $projectId );
		if ( $projectName->exists() ) {
			return $this->localizer->msg(
				'growthexperiments-addimage-reason-wikipedia-project',
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
	 * @return array
	 */
	private function getLanguagesListParam( array $languageCodes ): array {
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
		return Message::listParam( $shownLanguages, 'text' );
	}

	/**
	 * Construct the suggestion reason string when the suggested image is found in other projects
	 *
	 * @param string[] $projects Projects in which the image suggestion is used
	 * @return string
	 */
	private function getWikipediaReason( array $projects ): string {
		$totalCount = count( $projects );
		if ( $totalCount === 1 ) {
			$reason = $this->getWikipediaReasonOtherProject( $projects[0] );
			if ( $reason ) {
				return $reason;
			}
		}

		$languageCodes = $this->getLanguageCodesFromProjects( $projects );
		if ( count( $languageCodes ) === 0 ) {
			return $this->localizer->msg( 'growthexperiments-addimage-reason-wikipedia' )
				->numParams( $totalCount )->text();
		}
		return $this->localizer->msg(
			'growthexperiments-addimage-reason-wikipedia-languages',
				Message::numParam( $totalCount ),
				$this->getLanguagesListParam( $languageCodes )
			)->text();
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
		}
		return $this->getWikipediaReason( $suggestion['projects'] );
	}

	/**
	 * Get metadata for the specified image file name
	 *
	 * @param array $suggestion
	 * @return array|StatusValue
	 */
	public function getMetadata( array $suggestion ) {
		$filename = $suggestion['filename'];
		$extendedMetadata = $this->service->getExtendedMetadata( $filename );
		$fileMetadata = $this->service->getFileMetadata( $filename );
		if ( $fileMetadata instanceof StatusValue ) {
			return $fileMetadata;
		}
		return [
			'description' => $this->getDescriptionValue( $extendedMetadata ),
			'reason' => $this->getSuggestionReason( $suggestion ),
		] + $fileMetadata;
	}
}
