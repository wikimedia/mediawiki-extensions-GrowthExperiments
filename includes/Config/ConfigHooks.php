<?php

namespace GrowthExperiments\Config;

use Content;
use FormatJson;
use IContextSource;
use MediaWiki\Hook\EditFilterMergedContentHook;
use Status;
use TextContent;
use TitleFactory;
use User;

class ConfigHooks implements EditFilterMergedContentHook {
	/** @var WikiPageConfigValidation */
	private $configValidation;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->configValidation = new WikiPageConfigValidation();
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) {
		// Do not proceed on non-config pages
		$title = $context->getTitle();
		$configTitle = $this->titleFactory
			->newFromText(
				$context->getConfig()->get( 'GEWikiConfigPageTitle' )
			);
		if (
			$title === null ||
			$configTitle === null ||
			!$title->equals( $configTitle )
		) {
			return true;
		}

		if (
			$content->getModel() !== CONTENT_MODEL_JSON ||
			!( $content instanceof TextContent )
		) {
			$status->fatal(
				'growthexperiments-config-validator-contentmodel-mismatch',
				$content->getModel()
			);
			return false;
		}

		$loadedConfigStatus = FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC );
		if ( !$loadedConfigStatus->isOK() ) {
			$status->merge( $loadedConfigStatus );
			return true;
		}
		$status->merge( $this->configValidation->validate(
			$loadedConfigStatus->getValue()
		) );
	}
}
