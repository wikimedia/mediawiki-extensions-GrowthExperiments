<?php

namespace GrowthExperiments\Config\Validation;

use Config;
use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use Title;
use TitleFactory;

class ConfigValidatorFactory {
	/** @var Config */
	private $config;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param Config $config
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		Config $config,
		TitleFactory $titleFactory
	) {
		$this->config = $config;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * Code helper for comparing titles
	 *
	 * @param Title $configTitle
	 * @param string $var
	 * @return bool
	 */
	private function titleEquals( Title $configTitle, string $var ): bool {
		$varTitle = $this->titleFactory
			->newFromText( $this->config->get( $var ) );
		return $varTitle !== null && $configTitle->equals( $varTitle );
	}

	/**
	 * @param LinkTarget $configPage
	 * @return IConfigValidator
	 */
	public function newConfigValidator( LinkTarget $configPage ): IConfigValidator {
		$title = $this->titleFactory->newFromLinkTarget( $configPage );

		if ( $this->titleEquals( $title, 'GEWikiConfigPageTitle' ) ) {
			return new GrowthConfigValidation();
		}

		throw new InvalidArgumentException( 'Unsupported config page' );
	}
}
