( function () {
	'use strict';

	/**
	 * @param {Object} config
	 * @param {string} config.id
	 * @param {string} config.taskTypeId The task type ID, if set.
	 * @param {string} [config.customSubheader] Override normal subheader text.
	 * @param {string} [config.subsubheader] Text of the second subheader.
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
			.addClass( [
				'mw-ge-help-panel-home-button',
				// The following classes are used here:
				// * mw-ge-help-panel-home-button-ask-help
				// * mw-ge-help-panel-home-button-general-help
				// * mw-ge-help-panel-home-button-suggested-edits
				'mw-ge-help-panel-home-button-' + this.config.id
			] )
			.append(
				$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-text' )
					.append(
						this.getPreHeader(),
						this.getHeader(),
						this.getSubsubheader(),
						this.getSubheader()
					),
				$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-image' ).append( this.getIcon() )
			);
		this.$element.append( $button );
	};

	HelpPanelHomeButtonWidget.prototype.getIcon = function () {
		var iconKeyMap = {
				'ask-help': 'userTalk',
				'ask-help-mentor': 'mentor',
				'general-help': 'help',
				'suggested-edits': 'suggestedEdits'
			},
			iconKey = iconKeyMap[ this.config.id ];
		return new OO.ui.IconWidget( {
			icon: iconKey,
			// FIXME: Not sure we need to set custom classes here, they don't
			// appear to be used.
			classes: [
				'mw-ge-help-panel-home-button-image-icon',
				// The following classes are used here:
				// * mw-ge-help-panel-home-button-image-icon-ask-help
				// * mw-ge-help-panel-home-button-image-icon-ask-help-mentor
				// * mw-ge-help-panel-home-button-image-icon-general-help
				// * mw-ge-help-panel-home-button-image-icon-suggested-edits
				'mw-ge-help-panel-home-button-image-icon-' + this.config.id
			]
		} ).$element;
	};

	HelpPanelHomeButtonWidget.prototype.getPreHeader = function () {
		if ( this.config.id !== 'suggested-edits' ) {
			return '';
		}
		return $( '<div>' ).addClass( 'mw-ge-help-panel-home-button-preheader' )
			.append(
				new OO.ui.IconWidget( {
					icon: 'lightbulb',
					classes: [ 'mw-ge-help-panel-home-button-preheader-icon' ]
				} ).$element,
				// The following messages are used here:
				// * growthexperiments-help-panel-button-header-general-help
				// * growthexperiments-help-panel-button-header-ask-help
				// * growthexperiments-help-panel-button-header-suggested-edits
				$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-preheader-text' )
					.text( mw.msg( 'growthexperiments-help-panel-button-preheader-' + this.config.id ) ) );

	};

	HelpPanelHomeButtonWidget.prototype.getHeader = function () {
		return $( '<h2>' ).addClass( 'mw-ge-help-panel-home-button-text-header' )
			// The following messages are used here:
			// * growthexperiments-help-panel-button-header-general-help
			// * growthexperiments-help-panel-button-header-ask-help
			// * growthexperiments-help-panel-button-header-ask-help-mentor
			// * growthexperiments-homepage-suggestededits-tasktype-name-copyedit
			// * growthexperiments-homepage-suggestededits-tasktype-name-references
			// * growthexperiments-homepage-suggestededits-tasktype-name-update
			// * growthexperiments-homepage-suggestededits-tasktype-name-links
			// * growthexperiments-homepage-suggestededits-tasktype-name-expand
			.text( this.config.id === 'suggested-edits' ?
				mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-name-' + this.config.taskTypeId ) :
				mw.msg( 'growthexperiments-help-panel-button-header-' + this.config.id ) );
	};

	HelpPanelHomeButtonWidget.prototype.getSubheader = function () {
		var text;

		if ( this.config.customSubheader ) {
			text = this.config.customSubheader;
		} else {
			// The following messages are used here:
			// * growthexperiments-help-panel-button-subheader-general-help
			// * growthexperiments-help-panel-button-subheader-ask-help
			// * growthexperiments-help-panel-button-header-suggested-edits
			text = mw.msg( 'growthexperiments-help-panel-button-subheader-' + this.config.id );
		}
		return $( '<div>' ).addClass( 'mw-ge-help-panel-home-button-text-subheader' ).text( text );
	};

	HelpPanelHomeButtonWidget.prototype.getSubsubheader = function () {
		if ( !this.config.subsubheader ) {
			return null;
		}
		return $( '<div>' ).addClass( 'mw-ge-help-panel-home-button-text-subsubheader' )
			.text( this.config.subsubheader );
	};

	module.exports = HelpPanelHomeButtonWidget;

}() );
