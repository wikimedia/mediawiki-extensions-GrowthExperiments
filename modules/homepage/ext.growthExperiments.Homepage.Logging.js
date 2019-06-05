( function () {
	var Logger = require( './ext.growthExperiments.Homepage.Logger.js' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		getModuleState = function ( moduleName ) {
			return mw.config.get( 'wgGEHomepageModuleState-' + moduleName ) || '';
		},
		getModuleActionData = function ( moduleName ) {
			return mw.config.get( 'wgGEHomepageModuleActionData-' + moduleName ) || {};
		},
		handleHover = function ( action ) {
			return function () {
				var $module = $( this ),
					moduleName = $module.data( 'module-name' );
				logger.log( moduleName, 'hover-' + action, getModuleState( moduleName ), getModuleActionData( moduleName ) );
			};
		},
		moduleSelector = '.growthexperiments-homepage-module',
		handleClick = function ( e ) {
			var $link = $( this ),
				$module = $link.closest( moduleSelector ),
				linkId = $link.data( 'link-id' ),
				moduleName = $module.data( 'module-name' );
			logger.log( moduleName, 'link-click', getModuleState( moduleName ),
				$.extend( { linkId: linkId }, getModuleActionData( moduleName ) ) );

			// This is needed so this handler doesn't fire twice for links
			// that are inside a module that is inside another module.
			e.stopPropagation();
		},
		logImpression = function () {
			var $module = $( this ),
				moduleName = $module.data( 'module-name' );
			logger.log( moduleName, 'impression', getModuleState( moduleName ), getModuleActionData( moduleName ) );
		};

	/* eslint-disable no-jquery/no-event-shorthand */
	$( moduleSelector )
		.mouseenter( handleHover( 'in' ) )
		.mouseleave( handleHover( 'out' ) )
		.on( 'click', '[data-link-id]', handleClick )
		.each( logImpression );
	/* eslint-enable no-jquery/no-event-shorthand */
}() );
