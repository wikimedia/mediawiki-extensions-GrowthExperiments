( function ( M ) {
	var mobile = M.require( 'mobile.startup' ),
		View = mobile.View,
		Icon = mobile.Icon,
		header = mobile.headers.header,
		promisedView = mobile.promisedView,
		Overlay = mobile.Overlay,
		util = mobile.util,
		mfExtend = mobile.mfExtend,
		initEllipsisMenu = require( '../ext.growthExperiments.Homepage.Mentorship/EllipsisMenu.js' );

	/**
	 * Displays homepage module in an overlay.
	 *
	 * @class mw.libs.ge.MobileOverlay
	 * @extends Overlay
	 * @param {Object} params Configuration options
	 */
	function MobileOverlay( params ) {
		Overlay.call( this,
			util.extend( {
				className: 'overlay growthexperiments-homepage-container homepage-module-overlay'
			}, params )
		);
	}

	mfExtend( MobileOverlay, Overlay, {

		/**
		 * @inheritdoc
		 * @memberof mw.libs.ge.MobileOverlay
		 * @instance
		 */
		preRender: function () {
			var options = this.options,
				infoButton,
				ellipsisMenu,
				headerActions = [];

			function shouldShowInfoButton( moduleName ) {
				return moduleName === 'suggested-edits';
			}

			function shouldShowEllipsisMenu( moduleName ) {
				return moduleName === 'mentorship';
			}

			if ( shouldShowInfoButton( options.moduleName ) ) {
				this.headerPromise = mw.loader.using( 'oojs-ui' ).then( function () {
					infoButton = new OO.ui.ButtonWidget( {
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
				headerActions = [ promisedView( this.headerPromise ) ];
			} else if ( shouldShowEllipsisMenu( options.moduleName ) ) {
				this.headerPromise = mw.loader.using( 'oojs-ui' ).then( function () {
					// eslint-disable-next-line no-jquery/no-global-selector
					ellipsisMenu = initEllipsisMenu( $( '.growthexperiments-homepage-container' ) );
					return View.make(
						{ class: 'homepage-module-overlay-ellipsis-menu' },
						[ ellipsisMenu.$element ]
					);

				} );
				headerActions = [ promisedView( this.headerPromise ) ];
			} else {
				this.headerPromise = $.Deferred().resolve().promise();
			}

			this.options.headers = [
				header(
					mw.html.escape( options.heading ),
					headerActions,
					new Icon( {
						tagName: 'button',
						name: 'previous',
						glyphPrefix: 'growth',
						additionalClassNames: 'back',
						label: 'mobile-frontend-overlay-close'
					} ),
					'initial-header homepage-module-overlay-header'
				)
			];
		},

		/**
		 * @inheritdoc
		 * @memberof mw.libs.ge.MobileOverlay
		 * @instance
		 */
		postRender: function () {
			var resourceLoaderModules = this.options.rlModules,
				moduleName = this.options.moduleName,
				moduleHtml = this.options.html,
				appendHtml = function ( html ) {
					this.$el.find( '.overlay-content' ).append( html );
				}.bind( this );
			Overlay.prototype.postRender.apply( this );
			// Load the RL modules if they were not loaded before the user tapped on the
			// module. Then add the HTML to the DOM, then fire a hook so that the JS in the RL
			// modules can operate on the HTML in the overlay.
			mw.loader.using( resourceLoaderModules )
				.then( function () {
					appendHtml( moduleHtml );
					// Wait for the header promise to finish before firing the hook
					return this.headerPromise;
				}.bind( this ) )
				.then( function () {
					// It's important to always call the hook from a promise so it executes
					// after postRender() has finished. It ensures the module content is in
					// the overlay and can be manipulated.
					mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).fire(
						moduleName,
						this.$el
					);
					this.onOverlayShown();
				}.bind( this ) );
		},

		/**
		 * Remove loading class once the overlay is shown
		 */
		onOverlayShown: function () {
			$( document.body ).removeClass(
				'growthexperiments-homepage-mobile-summary--opening-overlay'
			);
		}

	} );

	module.exports = MobileOverlay;

}( mw.mobileFrontend ) );
