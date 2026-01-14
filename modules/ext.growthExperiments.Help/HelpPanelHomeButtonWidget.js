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
	const $button = $( '<div>' )
		.addClass( [
			'mw-ge-help-panel-home-button',
			// The following classes are used here:
			// * mw-ge-help-panel-home-button-ask-help
			// * mw-ge-help-panel-home-button-general-help
			// * mw-ge-help-panel-home-button-suggested-edits
			'mw-ge-help-panel-home-button-' + this.config.id,
		] )
		.append(
			$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-text' )
				.append(
					this.getPreHeader(),
					this.getHeader(),
					this.getSubsubheader(),
					this.getSubheader(),
				),
			$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-image' ).append( this.getIcon() ),
		);
	this.$element.append( $button );
};

HelpPanelHomeButtonWidget.prototype.getIcon = function () {
	const iconKeyMap = {
			'ask-help': 'userTalk',
			'ask-help-mentor': 'mentor',
			'general-help': 'help',
			'suggested-edits': 'suggestedEdits',
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
			'mw-ge-help-panel-home-button-image-icon-' + this.config.id,
		],
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
				classes: [ 'mw-ge-help-panel-home-button-preheader-icon' ],
			} ).$element,
			// The following messages are used here:
			// * growthexperiments-help-panel-button-header-general-help
			// * growthexperiments-help-panel-button-header-ask-help
			// * growthexperiments-help-panel-button-header-suggested-edits
			$( '<div>' ).addClass( 'mw-ge-help-panel-home-button-preheader-text' )
				.text( mw.msg( 'growthexperiments-help-panel-button-preheader-' + this.config.id ) ) );

};

HelpPanelHomeButtonWidget.prototype.getHeader = function () {
	let headerText;
	if ( this.config.id === 'suggested-edits' ) {
		// The following messages are used here:
		// * growthexperiments-homepage-suggestededits-tasktype-name-copyedit
		// * growthexperiments-homepage-suggestededits-tasktype-name-references
		// * growthexperiments-homepage-suggestededits-tasktype-name-update
		// * growthexperiments-homepage-suggestededits-tasktype-name-links
		// * growthexperiments-homepage-suggestededits-tasktype-name-expand
		headerText = mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-name-' + this.config.taskTypeId );
	} else if ( this.config.id === 'ask-help-mentor' ) {
		headerText = mw.message(
			'growthexperiments-help-panel-button-header-ask-help-mentor',
			this.getMentorGender(),
		).text();
	} else {
		// The following messages are used here:
		// * growthexperiments-help-panel-button-header-general-help
		// * growthexperiments-help-panel-button-header-ask-help
		headerText = mw.msg( 'growthexperiments-help-panel-button-header-' + this.config.id );
	}
	return $( '<h2>' ).addClass( 'mw-ge-help-panel-home-button-text-header' ).text( headerText );
};

HelpPanelHomeButtonWidget.prototype.getSubheader = function () {
	let text;

	if ( this.config.customSubheader ) {
		text = this.config.customSubheader;
	} else if ( this.config.id === 'suggested-edits' && this.config.taskTypeId === 'revise-tone' ) {
		text = mw.msg( 'growthexperiments-help-panel-revise-tone-refresh-skills' );
	} else {
		// The following messages are used here:
		// * growthexperiments-help-panel-button-subheader-general-help
		// * growthexperiments-help-panel-button-subheader-ask-help
		// * growthexperiments-help-panel-button-subheader-suggested-edits
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

/**
 * Get the gender of the mentor, used with messages that refer to the mentor in the third person
 *
 * @return {string}
 */
HelpPanelHomeButtonWidget.prototype.getMentorGender = function () {
	// This class is used in both the homepage and the article page.
	// GEHomepageMentorshipMentorGender is outputted via HomepageHooks,
	// wgGEHelpPanelMentorData via HelpPanelHooks.
	return mw.config.get( 'GEHomepageMentorshipMentorGender' ) ||
		( mw.config.get( 'wgGEHelpPanelMentorData' ) || {} ).gender;
};

module.exports = HelpPanelHomeButtonWidget;
