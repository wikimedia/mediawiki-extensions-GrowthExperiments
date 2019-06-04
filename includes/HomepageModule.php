<?php

namespace GrowthExperiments;

interface HomepageModule {

	const RENDER_DESKTOP = 'desktop';
	const RENDER_MOBILE_SUMMARY = 'mobile-summary';
	const RENDER_MOBILE_DETAILS = 'mobile-details';
	const RENDER_MOBILE_DETAILS_OVERLAY = 'mobile-overlay';

	/**
	 * Render the module in the given mode.
	 *
	 * @param string $mode One of RENDER_DESKTOP, RENDER_MOBILE_SUMMARY, RENDER_MOBILE_DETAILS
	 * @return string Html rendering of the module
	 */
	public function render( $mode );

	/**
	 * Get an array of data needed to render the module details in a MobileFrontend Overlay.
	 *
	 * @return array
	 */
	public function getDataForOverlay();
}
