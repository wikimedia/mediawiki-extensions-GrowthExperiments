( function () {
	const mobile = require( 'mobile.startup' ),
		View = mobile.View,
		header = mobile.overlayHeader,
		promisedView = mobile.promisedView,
		Overlay = mobile.Overlay,
		initEllipsisMenu = require( '../ext.growthExperiments.Homepage.Mentorship/EllipsisMenu.js' );

	/**
	 * Creates the header actions for the overlay. It also returns
	 * a promise that will resolve when the header actions are ready.
	 *
	 * @param {Object} options
	 * @return {{headerActions:{Array}, headerPromise:jQuery.Promise}}
	 */
	function prepareHeader( options ) {
		let headerActions = [];
		let headerPromise = $.Deferred().resolve().promise();

		function shouldShowInfoButton( moduleName ) {
			return moduleName === 'suggested-edits';
		}

		function shouldShowEllipsisMenu( moduleName ) {
			return moduleName === 'mentorship';
		}
		if ( shouldShowInfoButton( options.moduleName ) ) {
			headerPromise = mw.loader.using( 'oojs-ui' ).then( () => {
				const infoButton = new OO.ui.ButtonWidget( {
					id: 'mw-ge-homepage-suggestededits-info',
					icon: 'info-unpadded',
					framed: false,
					title: mw.msg( 'growthexperiments-homepage-suggestededits-more-info' ),
					label: mw.msg( 'growthexperiments-homepage-suggestededits-more-info' ),
					invisibleLabel: true
				} );
				// We need to embed the module name and mode so that we can access it
				// on the mobile overlay info button, see setupCtaButton in
				// ext.growthExperiments.Homepage.SuggestedEdits/StartEditing.js
				infoButton.$element.data( 'module-name', options.moduleName );
				infoButton.$element.data( 'mode', 'mobile-overlay' );
				// HACK: make infusing this button (pretend to) work, even though
				//   it was created in JS
				infoButton.$element.data( 'ooui-infused', infoButton );
				return View.make(
					{ class: 'homepage-module-overlay-info' },
					[ infoButton.$element ]
				);
			} );
			headerActions = [ promisedView( headerPromise ) ];
		} else if ( shouldShowEllipsisMenu( options.moduleName ) ) {
			headerPromise = mw.loader.using( 'oojs-ui' ).then( () => {
				// eslint-disable-next-line no-jquery/no-global-selector
				const ellipsisMenu = initEllipsisMenu( $( '.growthexperiments-homepage-container' ) );
				return View.make(
					{ class: 'homepage-module-overlay-ellipsis-menu' },
					[ ellipsisMenu.$element ]
				);

			} );
			headerActions = [ promisedView( headerPromise ) ];
		}

		return { headerActions, headerPromise };
	}

	/**
	 * Prepare overlay options before rendering
	 *
	 * @param {Object} options
	 * @return {Object} options
	 */
	const preRender = ( options ) => {
		const { headerActions, headerPromise } = prepareHeader( options );
		const $button = $( '<button>' )
			.addClass( [
				'cdx-button',
				'cdx-button--weight-quiet',
				'cdx-button--size--large',
				'cdx-button--icon-only',
				'back'
			] )
			.append(
				$( '<span>' )
					.attr( 'aria-hidden', 'true' )
					.addClass( [
						'growthexperiments-icon-previous',
						'cdx-button__icon'
					] ),
				$( '<span>' )
					.text(
						mw.msg( 'mobile-frontend-overlay-close' )
					)
			);
		options.headers = [
			header(
				mw.html.escape( options.heading ),
				headerActions,
				View.make( {}, [ $button ] ),
				'initial-header homepage-module-overlay-header'
			)
		];
		options.headerPromise = headerPromise;
		return options;
	};

	/**
	 * @typedef {Object} View
	 * @see MobileFrontend/src/mobile.startup/View.js
	 */
	/**
	 * Creates a view for the overlay content appending
	 * the module HTML and loading its RL modules.
	 *
	 * @param {Object} options
	 * @return {View} view
	 */
	const postRender = function ( options ) {
		const $el = $( '<div>' ).addClass( [
			'growthexperiments-homepage-container',
			'homepage-module-overlay'
		] );
		// Load the RL modules if they were not loaded before the user tapped on the
		// module. Then add the HTML to the DOM, then fire a hook so that the JS in the RL
		// modules can operate on the HTML in the overlay.
		mw.loader.using( options.rlModules )
			.then( () => $el.append( options.html ) );

		return View.make( {}, [ $el ] );
	};

	/**
	 * Remove loading class once the overlay is shown
	 */
	const onOverlayShown = function () {
		$( document.body ).removeClass(
			'growthexperiments-homepage-mobile-summary--opening-overlay'
		);
	};

	/**
	 * @typedef {Object} Overlay
	 * @see MobileFrontend/src/mobile.startup/Overlay.js
	 */
	const MobileOverlay = {
		/**
		 * @param {Object} options
		 * @return {Object} Overlay
		 */
		make: ( options ) => {
			const opts = preRender( options );
			const view = postRender( opts );
			let overlay = null;
			// Wait for the header promise to finish before firing the hook
			opts.headerPromise.then( () => {
				// It's important to always call the hook from a promise so it executes
				// after postRender() has finished. It ensures the module content is in
				// the overlay and can be manipulated.
				mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).fire(
					opts.moduleName,
					overlay.$el
				);
				onOverlayShown();
			} );
			overlay = Overlay.make( opts, view );
			return overlay;
		}
	};

	module.exports = MobileOverlay;

}() );
