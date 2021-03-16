<?php

namespace GrowthExperiments\Config;

use CommentStoreComment;
use FormatJson;
use InvalidArgumentException;
use JsonContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MWException;
use Status;
use TitleFactory;
use User;

class WikiPageConfigWriter {
	/** @var LinkTarget */
	private $configPage;

	/** @var User */
	private $performer;

	/** @var WikiPageConfigLoader */
	private $wikiPageConfigLoader;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var array|null */
	private $wikiConfig;

	/** @var string[] List of variables that can be overridden on wiki */
	private $allowList;

	/**
	 * @param WikiPageConfigLoader $wikiPageConfigLoader
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param string[] $allowList
	 * @param LinkTarget $configPage
	 * @param User $performer
	 */
	public function __construct(
		WikiPageConfigLoader $wikiPageConfigLoader,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		array $allowList,
		LinkTarget $configPage,
		User $performer
	) {
		$this->wikiPageConfigLoader = $wikiPageConfigLoader;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;

		$this->allowList = $allowList;
		$this->configPage = $configPage;
		$this->performer = $performer;
	}

	/**
	 * Load wiki-config via WikiPageConfigLoader, if some exists
	 */
	private function loadConfig(): void {
		if ( $this->titleFactory->newFromLinkTarget( $this->configPage )->exists() ) {
			$this->wikiConfig = $this->wikiPageConfigLoader->load(
				$this->configPage,
				WikiPageConfigLoader::READ_LATEST
			);
		} else {
			$this->wikiConfig = [];
		}
	}

	/**
	 * Validate an attempt to add a variable
	 *
	 * Currently only checks the allowlist in GEOnWikiConfigAllowList
	 *
	 * @param string $variable
	 * @param mixed $value
	 * @throws InvalidArgumentException In case of a validation error
	 */
	private function validateVariable( string $variable, $value ): void {
		if ( !in_array( $variable,  $this->allowList ) ) {
			throw new InvalidArgumentException(
				'Invalid attempt to set a variable via WikiPageConfigWriter'
			);
		}
	}

	/**
	 * Unset all config variables
	 *
	 * Useful for migration purposes, or for other places where we want to
	 * start with an empty config.
	 */
	public function pruneConfig(): void {
		$this->wikiConfig = [];
	}

	/**
	 * @param string $variable
	 * @param mixed $value
	 */
	public function setVariable( string $variable, $value ): void {
		if ( $this->wikiConfig === null ) {
			$this->loadConfig();
		}

		$this->validateVariable( $variable, $value );
		$this->wikiConfig[$variable] = $value;
	}

	/**
	 * @param array $variables
	 */
	public function setVariables( array $variables ): void {
		foreach ( $variables as $variable => $value ) {
			$this->setVariable( $variable, $value );
		}
	}

	/**
	 * @param string $summary
	 * @param bool $minor
	 * @return Status
	 * @throws MWException
	 */
	public function save( string $summary = '', bool $minor = false ): Status {
		// Load config if not done already, to support null-edits
		if ( $this->wikiConfig === null ) {
			$this->loadConfig();
		}

		// Sort config alphabetically
		ksort( $this->wikiConfig, SORT_STRING );

		$page = $this->wikiPageFactory->newFromLinkTarget( $this->configPage );
		$updater = $page->newPageUpdater( $this->performer );
		// TODO: T275976: Maybe we should get rid of JsonContent?
		$updater->setContent( SlotRecord::MAIN, new JsonContent(
			FormatJson::encode( $this->wikiConfig )
		) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( $summary ),
			$minor ? EDIT_MINOR : 0
		);
		$status = $updater->getStatus() ?? Status::newGood();

		// Invalidate config cache after the edit
		$this->wikiPageConfigLoader->invalidate( $this->configPage );

		return $status;
	}
}
