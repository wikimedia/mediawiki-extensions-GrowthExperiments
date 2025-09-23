/**
 * Packaged with all the GuidedTours modules.
 */
( function () {

	/**
	 * Calculate guider horizontal offset for reasonable tip positioning. Ideally we'd adjust the
	 * position of the tip instead but the guider library does not expose that.
	 *
	 * @param {string} targetSelector jQuery selector of the target element (can be a fallback
	 *   chain of multiple elements).
	 * @return {number|null}
	 */
	function getLeftOffset( targetSelector ) {
		const $targetSelector = $( targetSelector ).first();

		// Do not use any offset if the target does not exist (in which case the guider will show up
		// in the middle of the screen).
		if ( !$targetSelector.length ) {
			return null;
		}

		// The guider tip block is 42px wide and 21px away from the edge of the guider. Shift it
		// 21+(42/2) px to the right so the tip points at the right edge of the target, then
		// shift back <target width>/2.
		let offset = Math.floor( 42 - $targetSelector.width() / 2 );

		// Avoid offsetting beyond right viewport edge and causing a horizontal scrollbar.
		// The guider library tries to align right edges but it does not take the guider border
		// into account in the calculation so it ends up 2px off, which we need to adjust for.
		const availableSpace = document.documentElement.clientWidth -
			$targetSelector[ 0 ].getBoundingClientRect().right - 2;
		offset = Math.min( offset, availableSpace );

		return offset;
	}

	module.exports = {
		/**
		 * Modifies in-place and returns a GuidedTours tour step object while compensating for some
		 * of GuidedTours' inadequacies. The tour step must point to a personal toolbar item.
		 *
		 * @param {Object} tourStep
		 * @return {Object} Modified tour step (the same object that was passed in).
		 */
		adjustPersonalToolbarTourStep: function ( tourStep ) {
			// eslint-disable-next-line no-jquery/no-global-selector
			const isVectorCompactPersonalToolbar = $( '.vector-user-links' ).length,
				targetSelector = tourStep.attachTo;

			if ( tourStep.position === 'bottom' && isVectorCompactPersonalToolbar ) {
				// The guider library does not support different guider positioning and tip
				// positioning. 'bottom' will align the middle of the guider with the middle of
				// the target element, which makes the guider extend beyond the right edge of the
				// page in modern Vector with a compact personal bar. 'bottomRight' aligns the
				// right edge of the guider with the right edge of the target element (left/left
				// on RTL pages), which is more reasonable in theory when close to the screen
				// edge but handled poorly by the library. That will be fixed below.
				tourStep.position = 'bottomRight';
			}

			tourStep.offset = {
				// The compact personal toolbar has lots of whitespace, making the guider feel
				// detached. Compensate for it somewhat.
				top: isVectorCompactPersonalToolbar ? -10 : null,
				// The guider library has crude tip positioning logic. With 'bottomRight' the right
				// edge of the guider and the target will be aligned, and the tip will just be
				// placed some hardcoded number of pixels away from the edge. For a small target
				// like the notification icon, it's not even pointing to the right icon. Use an
				// offset to center it on the icon.
				left: isVectorCompactPersonalToolbar ? getLeftOffset( targetSelector ) : null,
			};

			return tourStep;
		},
	};

}() );
