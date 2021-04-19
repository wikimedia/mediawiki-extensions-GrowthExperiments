<?php

namespace GrowthExperiments\Config;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use Psr\Log\LoggerInterface;
use TitleFactory;
use User;

class WikiPageConfigWriterFactory {
	/** @var WikiPageConfigLoader */
	private $wikiPageConfigLoader;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LoggerInterface */
	private $logger;

	/** @var User|null Injected system user, to allow injecting from tests */
	private $systemUser;

	/** @var WikiPageConfigValidation */
	private $wikiPageConfigValidation;

	/**
	 * @param WikiPageConfigLoader $wikiPageConfigLoader
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface $logger
	 * @param User|null $systemUser
	 */
	public function __construct(
		WikiPageConfigLoader $wikiPageConfigLoader,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		LoggerInterface $logger,
		?User $systemUser = null
	) {
		$this->wikiPageConfigValidation = new WikiPageConfigValidation();
		$this->wikiPageConfigLoader = $wikiPageConfigLoader;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->logger = $logger;
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
			$this->wikiPageConfigValidation,
			$this->wikiPageConfigLoader,
			$this->wikiPageFactory,
			$this->titleFactory,
			$this->logger,
			GrowthExperimentsMultiConfig::ALLOW_LIST,
			$configPage,
			$performerTmp
		);
	}
}
