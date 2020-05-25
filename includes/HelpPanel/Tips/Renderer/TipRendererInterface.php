<?php

namespace GrowthExperiments\HelpPanel\Tips\Renderer;

use GrowthExperiments\HelpPanel\Tips\TipRenderParameters;

interface TipRendererInterface {

	/**
	 * Renderer for a tip within a tipset.
	 *
	 * The renderer is responsible for taking the tip configuration and
	 * parameters and producing an HTML string with the tip contents.
	 *
	 * @param TipRenderParameters|null $tipRenderParameters
	 * @return string
	 */
	public function render( TipRenderParameters $tipRenderParameters = null ) :string;

}
