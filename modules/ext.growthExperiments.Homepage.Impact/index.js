( function () {
	var Logger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		pageviewsIconSelector = '.empty-pageviews',
		seeSuggestedEditsButtonSelector = '.see-suggested-edits-button',
		logToggle = function ( toggle, $sourceElement ) {
			var mode = $sourceElement
				.closest( '.growthexperiments-homepage-module' )
				.data( 'mode' );
			logger.log(
				'impact',
				mode,
				toggle ? 'open-nopageviews-tooltip' : 'close-nopageviews-tooltip'
			);
		},
		togglePopup = function ( buttonPopupWidget, toggle ) {
			return function () {
				buttonPopupWidget.getPopup().toggle( toggle );
				logToggle( toggle, buttonPopupWidget.$element );
			};
		},
		alertOptions = {
			actions: [ {
				label: mw.msg( 'growthexperiments-homepage-impact-empty-pageviews-tooltip-button' ),
				flags: [ 'progressive' ]
			} ]
		},
		mobileHandler = function () {
			var button = OO.ui.infuse( this );
			button.on( 'click', function () {
				logToggle( true, button.$element );
				OO.ui.alert( button.title, alertOptions ).then( function () {
					logToggle( false, button.$element );
				} );
			} );
		},
		desktopHandler = function () {
			var buttonConfig = $( this ).data( 'ooui' ),
				buttonPopupWidget = new OO.ui.PopupButtonWidget( $.extend(
					buttonConfig,
					{
						title: null,
						popup: {
							padded: true,
							align: 'backwards',
							position: 'above',
							$content: $( '<p>' ).text( buttonConfig.title )
						}
					}
				) );
			buttonPopupWidget.$button
				.off( 'click' )
				.on( 'mouseenter', togglePopup( buttonPopupWidget, true ) )
				.on( 'mouseleave', togglePopup( buttonPopupWidget, false ) );
			$( this ).replaceWith( buttonPopupWidget.$element );
		},
		handler = OO.ui.isMobile() ? mobileHandler : desktopHandler,
		onSuggestedEditsButtonClicked = function ( event ) {
			var modulePath = $( this ).data( 'link-module-path' );
			event.preventDefault();
			// "launchCta" isn't a real path, it's our cue to run the launchCta method
			// in the start editing module.
			if ( modulePath === 'launchCta' ) {
				mw.track( 'growthexperiments.startediting', {
					moduleName: 'impact',
					trigger: 'impact'
				} );
				return;
			}
			// TODO: Make the following lines sharable via Utils.js
			window.history.replaceState( null, null, modulePath );
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		};
	// See comments in ext.growthExperiments.Homepage.Mentorship/index.js and
	// ext.growthExperiments.Homepage.mobile/MobileOverlay.js
	$( pageviewsIconSelector ).each( handler );
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'impact' ) {
			$content.find( pageviewsIconSelector ).each( handler );
			$content.find( seeSuggestedEditsButtonSelector ).on( 'click', onSuggestedEditsButtonClicked );
		}
	} );
}() );
