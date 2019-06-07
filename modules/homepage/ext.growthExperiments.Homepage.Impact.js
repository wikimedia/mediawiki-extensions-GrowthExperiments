( function () {
	var Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		pageviewsIconSelector = '.empty-pageviews',
		logToggle = function ( toggle ) {
			logger.log(
				'impact',
				toggle ? 'open-nopageviews-tooltip' : 'close-nopageviews-tooltip'
			);
		},
		togglePopup = function ( buttonPopupWidget, toggle ) {
			return function () {
				buttonPopupWidget.getPopup().toggle( toggle );
				logToggle( toggle );
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
				logToggle( true );
				OO.ui.alert( button.title, alertOptions ).then( function () {
					logToggle( false );
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
		handler = OO.ui.isMobile() ? mobileHandler : desktopHandler;

	$( pageviewsIconSelector ).each( handler );
}() );
