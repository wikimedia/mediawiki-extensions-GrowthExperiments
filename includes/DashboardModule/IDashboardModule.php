<?php

namespace GrowthExperiments\DashboardModule;

interface IDashboardModule {
	public const RENDER_DESKTOP = 'desktop';
	public const RENDER_MOBILE_SUMMARY = 'mobile-summary';
	public const RENDER_MOBILE_DETAILS = 'mobile-details';
	public const RENDER_MOBILE_DETAILS_OVERLAY = 'mobile-overlay';

	/**
	 * Render the module in the given mode.
	 *
	 * @param string $mode One of RENDER_* constants
	 * @return string Html rendering of the module
	 */
	public function render( $mode );

	/**
	 * Get an array of data needed by the Javascript code related to this module.
	 *
	 * The default implementation doesn't return anything.
	 *
	 * @param string $mode One of RENDER_* constants
	 * @return array
	 */
	public function getJsData( $mode );

	/**
	 * Whether this module supports the given mode. If this returns false, render() and
	 * getJsData() should not be called with this mode.
	 * @param string $mode One of RENDER_* constants
	 * @return bool
	 */
	public function supports( $mode );
}
