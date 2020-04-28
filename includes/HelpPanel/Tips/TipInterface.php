<?php

namespace GrowthExperiments\HelpPanel\Tips;

interface TipInterface {

	/**
	 * Render the tip as an HTML string.
	 *
	 * @param TipRenderParameters|null $tipRenderParameters
	 * @return string
	 */
	public function render( TipRenderParameters $tipRenderParameters = null ) :string;

	/**
	 * @return TipConfig
	 */
	public function getConfig() :TipConfig;

}
