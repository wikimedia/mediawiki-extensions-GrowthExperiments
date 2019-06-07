( function () {
	var Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		handleHover = function ( action ) {
			return function () {
				var $module = $( this ),
					moduleName = $module.data( 'module-name' );
				logger.log( moduleName, 'hover-' + action );
			};
		},
		moduleSelector = '.growthexperiments-homepage-module',
		handleClick = function ( e ) {
			var $link = $( this ),
				$module = $link.closest( moduleSelector ),
				linkId = $link.data( 'link-id' ),
				moduleName = $module.data( 'module-name' );
			logger.log( moduleName, 'link-click', { linkId: linkId } );

			// This is needed so this handler doesn't fire twice for links
			// that are inside a module that is inside another module.
			e.stopPropagation();
		},
		logImpression = function () {
			var $module = $( this ),
				moduleName = $module.data( 'module-name' );
			logger.log( moduleName, 'impression' );
		};

	$( moduleSelector )
		.on( 'mouseenter', handleHover( 'in' ) )
		.on( 'mouseleave', handleHover( 'out' ) )
		.on( 'click', '[data-link-id]', handleClick )
		.each( logImpression );
}() );
