<?php

namespace GrowthExperiments\Config;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use TitleFactory;
use User;

class WikiPageConfigWriterFactory {
	/** @var WikiPageConfigLoader */
	private $wikiPageConfigLoader;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var User|null Injected system user, to allow injecting from tests */
	private $systemUser;

	/**
	 * @param WikiPageConfigLoader $wikiPageConfigLoader
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param User|null $systemUser
	 */
	public function __construct(
		WikiPageConfigLoader $wikiPageConfigLoader,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		?User $systemUser = null
	) {
		$this->wikiPageConfigLoader = $wikiPageConfigLoader;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->systemUser = $systemUser;
	}

	/**
	 * @param LinkTarget $configPage
	 * @param User|null $performer
	 * @return WikiPageConfigWriter
	 */
	public function newWikiPageConfigWriter(
		LinkTarget $configPage,
		?User $performer = null
	): WikiPageConfigWriter {
		$performerTmp = $performer
			?? $this->systemUser
			?? User::newSystemUser( 'MediaWiki default' );
		if ( $performerTmp === null ) {
			throw new InvalidArgumentException( 'Invalid performer passed' );
		}
		return new WikiPageConfigWriter(
			$this->wikiPageConfigLoader,
			$this->wikiPageFactory,
			$this->titleFactory,
			GrowthExperimentsMultiConfig::ALLOW_LIST,
			$configPage,
			$performerTmp
		);
	}
}
