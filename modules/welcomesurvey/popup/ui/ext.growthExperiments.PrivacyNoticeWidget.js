( function () {

	/**
	 * The is the privacy notice containing the privacy statement link.
	 *
	 * @param {Object} config
	 * @cfg {string} url Privacy statement URL
	 * @constructor
	 */
	function PrivacyNoticeWidget( config ) {
		var $privacyStatementLink;
		PrivacyNoticeWidget.parent.call( this, config );
		$privacyStatementLink = $( '<a>' )
			.attr( {
				href: config.url,
				target: '_blank'
			} )
			.addClass( 'external' )
			.text( mw.msg( 'welcomesurvey-privacy-policy-link-text' ) );
		this.$element
			.addClass( 'mw-parser-output' )
			.append( mw.message( 'welcomesurvey-sidebar-privacy-text', $privacyStatementLink ).parse() );
	}
	OO.inheritClass( PrivacyNoticeWidget, OO.ui.Widget );
	PrivacyNoticeWidget.static.tagName = 'p';

	module.exports = PrivacyNoticeWidget;
}() );
