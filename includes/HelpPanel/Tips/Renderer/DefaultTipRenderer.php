<?php

namespace GrowthExperiments\HelpPanel\Tips\Renderer;

use GrowthExperiments\HelpPanel\Tips\TipRenderParameters;
use Html;

class DefaultTipRenderer extends AbstractTipRenderer implements TipRendererInterface {

	/** @inheritDoc */
	public function render( TipRenderParameters $tipRenderParameters = null ) :string {
		return Html::rawElement( 'div', [ 'class' => $this->getBaseCssClassesConfig() ],
			$this->getContext()->msg(
				$tipRenderParameters->getMessageKey(),
				$tipRenderParameters->getExtraParameters()
			)->parse()
		);
	}
}
