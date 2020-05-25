<?php

namespace GrowthExperiments\HelpPanel\Tips\Renderer;

use GrowthExperiments\HelpPanel\Tips\TipRenderParameters;
use Html;

class GraphicTipRenderer extends AbstractTipRenderer implements TipRendererInterface {

	/** @inheritDoc */
	public function render( TipRenderParameters $tipRenderParameters = null ): string {
		return $tipRenderParameters ? Html::rawElement( 'img', [
			'class' => $this->getBaseCssClassesConfig(),
			'src' => current( $tipRenderParameters->getExtraParameters() ),
			// Leaving blank per T245786#6115403; screen readers should ignore
			// this purely decorative image.
			'alt' => '',
		] ) : '';
	}
}
