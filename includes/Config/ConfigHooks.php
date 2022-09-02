<?php

namespace GrowthExperiments\Config;

use Config;
use Content;
use DeferredUpdates;
use FormatJson;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use IContextSource;
use JsonContent;
use MediaWiki\Content\Hook\JsonValidateSaveHook;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use SpecialPage;
use Status;
use StatusValue;
use TextContent;
use TitleFactory;
use TitleValue;
use User;

class ConfigHooks implements
	EditFilterMergedContentHook,
	JsonValidateSaveHook,
	PageSaveCompleteHook,
	SkinTemplateNavigation__UniversalHook
{
	/** @var ConfigValidatorFactory */
	private $configValidatorFactory;

	/** @var WikiPageConfigLoader */
	private $configLoader;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Config */
	private $config;

	/**
	 * @param ConfigValidatorFactory $configValidatorFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param TitleFactory $titleFactory
	 * @param Config $config
	 */
	public function __construct(
		ConfigValidatorFactory $configValidatorFactory,
		WikiPageConfigLoader $configLoader,
		TitleFactory $titleFactory,
		Config $config
	) {
		$this->configValidatorFactory = $configValidatorFactory;
		$this->configLoader = $configLoader;
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
			}
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onJsonValidateSave(
		JsonContent $content, PageIdentity $pageIdentity, StatusValue $status
	) {
		foreach ( $this->configValidatorFactory->getSupportedConfigPages() as $configTitle ) {
			if ( $pageIdentity->isSamePageAs( $configTitle ) ) {
				$data = FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC )->getValue();
				$status->merge(
					$this->configValidatorFactory
						// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
						->newConfigValidator( TitleValue::castPageToLinkTarget( $pageIdentity ) )
						->validate( $data )
				);
				if ( !$status->isGood() ) {
					// JsonValidateSave expects a fatal status on failure, but the validator uses isGood()
					$status->setOK( false );
				}
				return $status->isOK();
			}
		}
	}

	/**
	 * Invalidate configuration cache when needed.
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		DeferredUpdates::addCallableUpdate( function () use ( $wikiPage ) {
			$title = $wikiPage->getTitle();
			foreach ( $this->configValidatorFactory->getSupportedConfigPages() as $configTitle ) {
				if ( $title->equals( $configTitle ) ) {
					$this->configLoader->invalidate( $configTitle );
				}
			}
		} );
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
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
