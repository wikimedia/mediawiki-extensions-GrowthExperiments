<?php

namespace GrowthExperiments\HelpPanel\Tips\Renderer;

use GrowthExperiments\HelpPanel\Tips\TipConfig;
use IContextSource;
use LogicException;

class TipRendererFactory {

	/**
	 * @param TipConfig $tipConfig
	 * @param IContextSource $context
	 * @return TipRendererInterface
	 */
	public static function newFromTipConfigAndContext(
		TipConfig $tipConfig, IContextSource $context
	) :TipRendererInterface {
		switch ( $tipConfig->getTipTypeId() ) {
			case 'main':
			case 'text':
				return new DefaultTipRenderer( $tipConfig, $context );
			case 'graphic':
				return new GraphicTipRenderer( $tipConfig, $context );
			case 'example':
				return new ExampleTipRenderer( $tipConfig, $context );
			default:
				throw new LogicException( $tipConfig->getTipTypeId() . ' is not a valid tip type ID.' );
		}
	}
}
