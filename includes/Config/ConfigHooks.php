<?php

namespace GrowthExperiments\Config;

use Config;
use Content;
use FormatJson;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use IContextSource;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\SkinTemplateNavigationHook;
use SpecialPage;
use Status;
use TextContent;
use TitleFactory;
use User;

class ConfigHooks implements EditFilterMergedContentHook, SkinTemplateNavigationHook {
	/** @var ConfigValidatorFactory */
	private $configValidatorFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Config */
	private $config;

	/**
	 * @param ConfigValidatorFactory $configValidatorFactory
	 * @param TitleFactory $titleFactory
	 * @param Config $config
	 */
	public function __construct(
		ConfigValidatorFactory $configValidatorFactory,
		TitleFactory $titleFactory,
		Config $config
	) {
		$this->configValidatorFactory = $configValidatorFactory;
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
		// Check whether this is a config page edited
		$title = $context->getTitle();
		foreach ( $this->configValidatorFactory->getSupportedConfigPages() as $configTitle ) {
			if ( $title->equals( $configTitle ) ) {
				// Check content model
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

				// Try to parse the config, and validate if parsing succeeded
				$loadedConfigStatus = FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC );
				if ( !$loadedConfigStatus->isOK() ) {
					$status->merge( $loadedConfigStatus );
				} else {
					$status->merge(
						$this->configValidatorFactory
							->newConfigValidator( $title )
							->validate( $loadedConfigStatus->getValue() )
					);
				}

				return $status->isOK();
			}
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation( $sktemplate, &$links ): void {
		// Code inspired by MassMessageHooks::onSkinTemplateNavigation

		$title = $sktemplate->getTitle();
		$geConfigTitle = $this->titleFactory
			->newFromText(
				$this->config->get( 'GEWikiConfigPageTitle' )
			);
		$newcomerTasksConfigTitle = $this->titleFactory
			->newFromText(
				$this->config->get( 'GENewcomerTasksConfigTitle' )
			);
		if (
			array_key_exists( 'edit', $links['views'] ) &&
			(
				( $geConfigTitle !== null && $title->equals( $geConfigTitle ) ) ||
				( $newcomerTasksConfigTitle !== null && $title->equals( $newcomerTasksConfigTitle ) )
			) &&
			$title->hasContentModel( CONTENT_MODEL_JSON )
		) {
			// Get the revision being viewed, if applicable
			$request = $sktemplate->getRequest();
			// $oldid is guaranteed to be an integer, 0 if invalid
			$oldid = $request->getInt( 'oldid' );

			// Show normal JSON editor if the user is trying to edit old version
			if ( $oldid == 0 ) {
				$links['views']['edit']['href'] = SpecialPage::getTitleFor(
					'EditGrowthConfig'
				)->getFullUrl();
			}
		}
	}
}
