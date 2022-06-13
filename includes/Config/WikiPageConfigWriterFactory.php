<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use TitleFactory;
use User;

class WikiPageConfigWriterFactory {
	/** @var WikiPageConfigLoader */
	private $wikiPageConfigLoader;

	/** @var ConfigValidatorFactory */
	private $configValidatorFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var UserFactory */
	private $userFactory;

	/** @var LoggerInterface */
	private $logger;

	/** @var User|null Injected system user, to allow injecting from tests */
	private $systemUser;

	/**
	 * @param WikiPageConfigLoader $wikiPageConfigLoader
	 * @param ConfigValidatorFactory $configValidatorFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param LoggerInterface $logger
	 * @param User|null $systemUser
	 */
	public function __construct(
		WikiPageConfigLoader $wikiPageConfigLoader,
		ConfigValidatorFactory $configValidatorFactory,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		UserFactory $userFactory,
		LoggerInterface $logger,
		?User $systemUser = null
	) {
		$this->wikiPageConfigLoader = $wikiPageConfigLoader;
		$this->configValidatorFactory = $configValidatorFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
		$this->logger = $logger;
		$this->systemUser = $systemUser;
	}

	/**
	 * @param LinkTarget $configPage
	 * @param UserIdentity|null $performer If null is passed, a system account will be used.
	 * @return WikiPageConfigWriter
	 */
	public function newWikiPageConfigWriter(
		LinkTarget $configPage,
		?UserIdentity $performer = null
	): WikiPageConfigWriter {
		$performerTmp = $performer
			?? $this->systemUser
			?? User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );
		if ( $performerTmp === null ) {
			throw new InvalidArgumentException( 'Invalid performer passed' );
		}
		return new WikiPageConfigWriter(
			$this->configValidatorFactory->newConfigValidator( $configPage ),
			$this->wikiPageConfigLoader,
			$this->wikiPageFactory,
			$this->titleFactory,
			$this->userFactory,
			$this->logger,
			GrowthExperimentsMultiConfig::ALLOW_LIST,
			$configPage,
			$performerTmp
		);
	}
}
