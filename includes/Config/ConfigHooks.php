<?php

namespace GrowthExperiments\Config;

use Config;
use Content;
use FormatJson;
use IContextSource;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\SkinTemplateNavigationHook;
use SpecialPage;
use Status;
use TextContent;
use TitleFactory;
use User;

class ConfigHooks implements EditFilterMergedContentHook, SkinTemplateNavigationHook {
	/** @var WikiPageConfigValidation */
	private $configValidation;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Config */
	private $config;

	/**
	 * @param TitleFactory $titleFactory
	 * @param Config $config
	 */
	public function __construct(
		TitleFactory $titleFactory,
		Config $config
	) {
		$this->configValidation = new WikiPageConfigValidation();
		$this->titleFactory = $titleFactory;
		$this->config = $config;
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

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation( $sktemplate, &$links ): void {
		// Code inspired by MassMessageHooks::onSkinTemplateNavigation

		$title = $sktemplate->getTitle();
		$configTitle = $this->titleFactory
			->newFromText(
				$this->config->get( 'GEWikiConfigPageTitle' )
			);
		if (
			array_key_exists( 'edit', $links['views'] ) &&
			$configTitle !== null &&
			$title->equals( $configTitle ) &&
			$title->hasContentModel( CONTENT_MODEL_JSON )
		) {
			// Get the revision being viewed, if applicable
			$request = $sktemplate->getRequest();
			// $oldid is guaranteed to be an integer, 0 if invalid
			$oldid = $request->getInt( 'oldid' );

			// Show normal JSON editor if the user is trying to edit old version
			if ( $oldid == 0 ) {
				$links['views']['edit']['href'] = SpecialPage::getTitleFor(
					'EditGrowthConfig',
					$title
				)->getFullUrl();
			}
		}
	}
}
