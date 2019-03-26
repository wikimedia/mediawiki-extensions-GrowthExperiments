<?php

namespace GrowthExperiments;

interface HomepageModule {

	/**
	 * Render this module as HTML.
	 *
	 * @return string Html rendering of the module
	 */
	public function render();
}
