<?php

namespace GrowthExperiments;

interface HomepageModule {

	const RENDER_DESKTOP  = 1;
	const RENDER_MOBILE_SUMMARY  = 2;
	const RENDER_MOBILE_DETAILS  = 3;

	/**
	 * Render the module in the given mode.
	 *
	 * @param int $mode One of RENDER_DESKTOP, RENDER_MOBILE_SUMMARY, RENDER_MOBILE_DETAILS
	 * @return string Html rendering of the module
	 */
	public function render( $mode );
}
