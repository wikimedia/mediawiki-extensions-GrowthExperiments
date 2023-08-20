<?php

namespace GrowthExperiments\Config;

use Config;
use ConfigException;
use IDBAccessObject;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;
use StatusValue;

class WikiPageConfig implements Config, IDBAccessObject {

	private LoggerInterface $logger;
	private TitleFactory $titleFactory;
	private ?WikiPageConfigLoader $configLoader;
	private ?string $rawConfigTitle;
	private ?Title $configTitle = null;
	/**
	 * @var bool Hack to disable DB access in non-database tests.
	 */
	private bool $isTestWithStorageDisabled;

	/**
	 * @param LoggerInterface $logger
	 * @param TitleFactory $titleFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param string $rawConfigTitle
	 * @param bool $isTestWithStorageDisabled
	 */
	public function __construct(
		LoggerInterface $logger,
		TitleFactory $titleFactory,
		WikiPageConfigLoader $configLoader,
		string $rawConfigTitle,
		bool $isTestWithStorageDisabled
	) {
		$this->logger = $logger;
		$this->titleFactory = $titleFactory;
		$this->configLoader = $configLoader;
		$this->rawConfigTitle = $rawConfigTitle;
		$this->isTestWithStorageDisabled = $isTestWithStorageDisabled;
	}

	/**
	 * Helper to late-construct Title
	 *
	 * Config is initialized pretty early. This allows us to delay construction of
	 * Title (which may talk to the DB) until whenever config is first fetched,
	 * which should be much later, and probably after init sequence finished.
	 *
	 * @throws ConfigException
	 * @return Title
	 */
	private function getConfigTitle(): Title {
		if ( $this->configTitle == null ) {
			$configTitle = $this->titleFactory->newFromText( $this->rawConfigTitle );

			if (
				$configTitle === null ||
				!$configTitle->isSiteJsonConfigPage()
			) {
				throw new ConfigException( 'Invalid GEWikiConfigPageTitle' );
			}

			$this->configTitle = $configTitle;
		}

		return $this->configTitle;
	}

	/**
	 * Helper function to fetch config data from wiki page
	 *
	 * This may sound expensive, but WikiPageConfigLoader is supposed
	 * to take care about caching.
	 *
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 *
	 * @throws ConfigException on an error
	 * @return array
	 */
	private function getConfigData( int $flags = 0 ): array {
		if ( $this->isTestWithStorageDisabled ) {
			return [];
		}
		if ( !$this->getConfigTitle()->exists() ) {
			// configLoader throws an exception for no-page case
			return [];
		}
		$res = $this->configLoader->load( $this->getConfigTitle(), $flags );
		if ( $res instanceof StatusValue ) {
			// Loading config failed. This can happen in case of both a software error and
			// an error made by an administrator (setting the JSON blob manually to something
			// badly malformed, ie. set an array when a bool is expected). Log the error, and
			// pretend there is nothing in the JSON blob.

			$this->logger->error(
				__METHOD__ . ' failed to load config from wiki: {error}',
				[
					'error' => (string)$res,
					'impact' => 'Config stored in MediaWiki:GrowthExperimentsConfig.json ' .
						'is ignored, using sane fallbacks instead'
				]
			);

			// NOTE: This code branch SHOULD NOT throw a ConfigException. Throwing an exception
			// would make _both_ get() and has() throw an exception, while returning an empty
			// array means has() finishes nicely (with a false), while get still throws an
			// exception (as calling get with has() returning false is unexpected). That behavior
			// is necessary for GrowthExperimentsMultiConfig (which is a wrapper around
			// MultiConfig) to work. When has() returns false, MultiConfig consults the fallback(s),
			// but with an exception thrown, it stops processing, and the exception propagates up to
			// the user.
			return [];
		}
		return $res;
	}

	/**
	 * @inheritDoc
	 */
	public function get( $name ) {
		return $this->getWithFlags( $name );
	}

	/**
	 * @param string $name
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return mixed Config value
	 */
	public function getWithFlags( $name, int $flags = 0 ) {
		$configData = $this->getConfigData( $flags );
		if ( !array_key_exists( $name, $configData ) ) {
			throw new ConfigException( 'Config key was not found in WikiPageConfig' );
		}

		return $configData[ $name ];
	}

	/**
	 * @inheritDoc
	 */
	public function has( $name ) {
		return $this->hasWithFlags( $name );
	}

	/**
	 * @param string $name
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return bool
	 */
	public function hasWithFlags( $name, int $flags = 0 ) {
		return array_key_exists( $name, $this->getConfigData( $flags ) );
	}
}
