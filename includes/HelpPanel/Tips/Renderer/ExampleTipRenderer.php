<?php

namespace GrowthExperiments\HelpPanel\Tips\Renderer;

use GrowthExperiments\HelpPanel\Tips\TipRenderParameters;
use Html;

class ExampleTipRenderer extends AbstractTipRenderer implements TipRendererInterface {

	/** @inheritDoc */
	public function render( TipRenderParameters $tipRenderParameters = null ): string {
		return Html::rawElement( 'div', [ 'class' => [
			'growthexperiments-quickstart-tips-tip',
			'growthexperiments-quickstart-tips-tip-' . $this->getTipConfig()->getTipTypeId()
		] ],
			Html::rawElement( 'p', [ 'class' => [
				'growthexperiments-quickstart-tips-tip-' . $this->getTipConfig()->getTipTypeId() . '-text'
			] ], $this->getContext()->msg( $this->getTipConfig()->getMessageKey() )->parse() )
		);
	}
}
