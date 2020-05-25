<?php

namespace GrowthExperiments\HelpPanel\Tips;

use GrowthExperiments\HelpPanel\Tips\Renderer\TipRendererInterface;

class Tip implements TipInterface {

	/**
	 * @var TipConfig
	 */
	protected $tipConfig;

	/**
	 * @var TipRendererInterface
	 */
	private $tipRenderer;

	/**
	 * @param TipConfig $tipConfig
	 * @param TipRendererInterface $tipRenderer
	 */
	public function __construct(
		TipConfig $tipConfig, TipRendererInterface $tipRenderer
	) {
		$this->tipConfig = $tipConfig;
		$this->tipRenderer = $tipRenderer;
	}

	/**
	 * @param TipConfig $tipConfig
	 * @param TipRendererInterface $tipRenderer
	 * @return Tip
	 */
	public static function factory( TipConfig $tipConfig, TipRendererInterface $tipRenderer ) :Tip {
		return new self( $tipConfig, $tipRenderer );
	}

	/** @inheritDoc */
	public function render( TipRenderParameters $tipRenderParameters = null ) :string {
		return $this->tipRenderer->render( $tipRenderParameters );
	}

	/** @inheritDoc */
	public function getConfig() :TipConfig {
		return $this->tipConfig;
	}
}
