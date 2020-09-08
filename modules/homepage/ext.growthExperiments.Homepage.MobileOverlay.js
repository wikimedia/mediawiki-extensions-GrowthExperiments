( function ( M ) {

	var mobile = M.require( 'mobile.startup' ),
		View = mobile.View,
		icons = mobile.icons,
		header = mobile.headers.header,
		promisedView = mobile.promisedView,
		Overlay = mobile.Overlay,
		util = mobile.util,
		Utils = require( '../utils/ext.growthExperiments.Utils.js' ),
		mfExtend = mobile.mfExtend;

	/**
	 * Displays homepage module in an overlay.
	 *
	 * @class MobileOverlay
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
		 * @memberof MobileOverlay
		 * @instance
		 */
		preRender: function () {
			var options = this.options,
				infoButton,
				headerActions = [],
				oouiPromise;
			function shouldShowInfoButton( moduleName ) {
				return Utils.isUserInVariant( [ 'C' ] ) && moduleName === 'suggested-edits';
			}
			if ( shouldShowInfoButton( options.moduleName ) ) {
				oouiPromise = mw.loader.using( 'oojs-ui' ).then( function () {
					infoButton = new OO.ui.ButtonWidget( {
						icon: 'info',
						framed: false,
						title: mw.msg( 'growthexperiments-homepage-suggestededits-more-info' )
					} );
					return View.make(
						{ class: 'homepage-module-overlay-info' },
						[ infoButton.$element ]
					);
				} );
				headerActions = [ promisedView( oouiPromise ) ];
			}

			this.options.headers = [
				header(
					options.heading,
					headerActions,
					icons.back(),
					'initial-header homepage-module-overlay-header'
				)
			];
		},

		/**
		 * @inheritdoc
		 * @memberof MobileOverlay
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
			mw.loader.using( resourceLoaderModules ).then( function () {
				appendHtml( moduleHtml );
				// It's important to always call the hook from a promise so it executes
				// after postRender() has finished. It ensures the module content is in
				// the overlay and can be manipulated.
				mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).fire(
					moduleName,
					this.$el.find( '.overlay-content' )
				);
			}.bind( this ) );
		}

	} );

	module.exports = MobileOverlay;

}( mw.mobileFrontend ) );
