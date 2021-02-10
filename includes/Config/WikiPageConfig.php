<?php

namespace GrowthExperiments\Config;

use Config;
use ConfigException;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use TitleFactory;

class WikiPageConfig implements Config {
	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageConfigLoader|null */
	private $configLoader;

	/** @var LinkTarget|null */
	private $configTitle;

	/** @var string|null */
	private $rawConfigTitle;

	/**
	 * @param TitleFactory $titleFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param string $rawConfigTitle
	 */
	public function __construct(
		TitleFactory $titleFactory,
		WikiPageConfigLoader $configLoader,
		string $rawConfigTitle
	) {
		$this->titleFactory = $titleFactory;
		$this->configLoader = $configLoader;
		$this->rawConfigTitle = $rawConfigTitle;
	}

	/**
	 * Helper to late-construct Title
	 *
	 * Config is initialized pretty early. This allows us to delay construction of
	 * Title (which may talk to the DB) until whenever config is first fetched,
	 * which should be much later, and probably after init sequence finished.
	 *
	 * @throws ConfigException
	 * @return LinkTarget
	 */
	private function getConfigTitle(): LinkTarget {
		if ( $this->configTitle == null ) {
			$configTitle = $this->titleFactory->newFromText( $this->rawConfigTitle );

			if (
				$configTitle === null ||
				// TODO: This should be probably replaced with something else
				// once we will have our own editor (and content model)
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
	 * @throws ConfigException on an error
	 * @return array
	 */
	private function getConfigData(): array {
		$res = $this->configLoader->load( $this->getConfigTitle() );
		if ( $res instanceof StatusValue ) {
			throw new ConfigException(
				'Failed to load wiki page config: Config key was not found in WikiPageConfig'
			);
		}
		return $res;
	}

	/**
	 * @inheritDoc
	 */
	public function get( $name ) {
		if ( !$this->has( $name ) ) {
			throw new ConfigException( 'Config key was not found in WikiPageConfig' );
		}

		return $this->getConfigData()[ $name ];
	}

	/**
	 * @inheritDoc
	 */
	public function has( $name ) {
		return array_key_exists( $name, $this->getConfigData() );
	}
}
