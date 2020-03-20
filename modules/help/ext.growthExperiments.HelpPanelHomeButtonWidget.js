( function () {
	'use strict';

	/**
	 * @param {Object} config
	 * @param {string} config.id
	 * @constructor
	 */
	function HelpPanelHomeButtonWidget( config ) {
		HelpPanelHomeButtonWidget.super.call( this, config );
		this.config = config;
		this.build();
	}

	OO.inheritClass( HelpPanelHomeButtonWidget, OO.ui.Widget );

	HelpPanelHomeButtonWidget.prototype.build = function () {
		var $button = $( '<div>' )
			.addClass( [ 'mw-ge-help-panel-home-button', 'mw-ge-help-panel-home-button-' + this.config.id ] )
			.append(
				$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-text' )
					.append(
						this.getHeader(),
						this.getSubheader()
					),
				$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-image' ).append( this.getIcon() )
			);
		this.$element.append( $button );
	};

	HelpPanelHomeButtonWidget.prototype.getIcon = function () {
		var iconKeyMap = {
				'ask-help': 'userTalk',
				'general-help': 'help'
			},
			iconKey = iconKeyMap[ this.config.id ];
		return new OO.ui.IconWidget( {
			icon: iconKey,
			classes: [ 'mw-ge-help-panel-home-button-image-icon' ]
		} ).$element;
	};

	HelpPanelHomeButtonWidget.prototype.getHeader = function () {
		return $( '<h2>' ).addClass( 'mw-ge-help-panel-home-button-text-header' )
			// growthexperiments-help-panel-button-header-general-help
			// growthexperiments-help-panel-button-header-ask-help
			.text( mw.msg( 'growthexperiments-help-panel-button-header-' + this.config.id ) );
	};

	HelpPanelHomeButtonWidget.prototype.getSubheader = function () {
		return $( '<div>' ).addClass( 'mw-ge-help-panel-home-button-text-subheader' )
			// growthexperiments-help-panel-button-subheader-general-help
			// growthexperiments-help-panel-button-subheader-ask-help
			.text( mw.msg( 'growthexperiments-help-panel-button-subheader-' + this.config.id ) );
	};

	module.exports = HelpPanelHomeButtonWidget;

}() );
