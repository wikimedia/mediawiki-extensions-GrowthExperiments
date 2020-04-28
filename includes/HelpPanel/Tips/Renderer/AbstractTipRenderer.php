<?php

namespace GrowthExperiments\HelpPanel\Tips\Renderer;

use GrowthExperiments\HelpPanel\Tips\TipConfig;
use IContextSource;

abstract class AbstractTipRenderer implements TipRendererInterface {

	/**
	 * @var TipConfig
	 */
	private $tipConfig;
	/**
	 * @var IContextSource
	 */
	private $context;

	/**
	 * @param TipConfig $tipConfig
	 * @param IContextSource $context
	 */
	public function __construct(
		TipConfig $tipConfig, IContextSource $context
	) {
		$this->tipConfig = $tipConfig;
		$this->context = $context;
	}

	/**
	 * @return TipConfig
	 */
	public function getTipConfig(): TipConfig {
		return $this->tipConfig;
	}

	/**
	 * @return IContextSource
	 */
	public function getContext(): IContextSource {
		return $this->context;
	}

	/**
	 * Config to pass to Html::rawElement for CSS class definition.
	 * @return array|string[]
	 */
	protected function getBaseCssClassesConfig() :array {
		return [
			'growthexperiments-quickstart-tips-tip',
			'growthexperiments-quickstart-tips-tip-' . $this->getTipConfig()->getTipTypeId()
		];
	}

}
