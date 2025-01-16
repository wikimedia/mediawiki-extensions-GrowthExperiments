<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use GrowthExperiments\Util;
use InvalidArgumentException;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class WikiPageConfigWriterFactory {

	private WikiPageConfigLoader $wikiPageConfigLoader;
	private ConfigValidatorFactory $configValidatorFactory;
	private WikiPageFactory $wikiPageFactory;
	private TitleFactory $titleFactory;
	private UserFactory $userFactory;
	private HookContainer $hookContainer;
	private LoggerInterface $logger;

	/** @var User|null Injected system user, to allow injecting from tests */
	private ?User $systemUser;

	/**
	 * @param WikiPageConfigLoader $wikiPageConfigLoader
	 * @param ConfigValidatorFactory $configValidatorFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param HookContainer $hookContainer
	 * @param LoggerInterface $logger
	 * @param User|null $systemUser
	 */
	public function __construct(
		WikiPageConfigLoader $wikiPageConfigLoader,
		ConfigValidatorFactory $configValidatorFactory,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		UserFactory $userFactory,
		HookContainer $hookContainer,
		LoggerInterface $logger,
		?User $systemUser = null
	) {
		$this->wikiPageConfigLoader = $wikiPageConfigLoader;
		$this->configValidatorFactory = $configValidatorFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
		$this->hookContainer = $hookContainer;
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
		if ( Util::useCommunityConfiguration() ) {
			wfDeprecated( WikiPageConfigWriter::class, '1.44', 'GrowthExperiments' );
		}

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
			$this->hookContainer,
			$this->logger,
			$configPage,
			$performerTmp
		);
	}
}
