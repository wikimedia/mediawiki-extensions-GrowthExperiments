'use strict';
/**
 * Widget to switch between topic match modes.
 *
 * @inheritDoc
 *
 * @class mw.libs.ge.MatchModeSelectWidget
 * @extends OO.ui.Widget
 *
 * @param {Object} config
 * @param {string} config.initialValue value to pre-select an option
 * @param {Object[]} config.options available select options. Options
 *   required properties: "data", "label". ie: { data: 'OR', label: 'Match ANY' }
 * @param {jQuery|true} [config.$overlay] Overlay to display the widget in, or true to use default OOUI window
 * @param {string[]} config.classes
 */
function MatchModeSelectWidget( config ) {
	const defaultClasses = [ 'mw-ge-MatchModeSelectWidget' ];
	config = Object.assign( {}, config );
	config.classes = defaultClasses.concat( config.classes );
	MatchModeSelectWidget.super.call( this, config );

	this.infoButton = new OO.ui.PopupButtonWidget( {
		icon: 'info-unpadded',
		classes: [ 'mw-ge-MatchModeSelectWidget__popup' ],
		framed: false,
		invisibleLabel: true,
		$overlay: config.$overlay,
		popup: {
			label: mw.message(
				'growthexperiments-homepage-suggestededits-topics-match-mode-description',
			).text(),
			$content: $( '<p>' )
				.addClass( 'mw-ge-MatchModeSelectWidget__info-text' )
				.text( mw.message(
					'growthexperiments-homepage-suggestededits-topics-match-mode-description',
				).text() ),
			padded: true,
		},
	} );

	this.modeSelect = new OO.ui.DropdownWidget( {
		classes: [ 'mw-ge-MatchModeSelectWidget__dropdown' ],
		menu: {
			items: this.configOptionsToItems( config.options ),
		},
	} );
	this.modeSelect.getMenu().selectItemByData( config.initialValue );
	this.modeSelect.getMenu().connect( this, {
		choose: function ( selected ) {
			this.emit( 'toggleMatchMode', selected.getData() );
		},
	} );
	this.$element.append(
		this.modeSelect.$element,
		this.infoButton.$element,
	);
}

OO.inheritClass( MatchModeSelectWidget, OO.ui.Widget );

/**
 * Map select options provided in config to Dropdown menu items
 *
 * @param  {Object[]} options configuration options
 * @return {OO.ui.MenuOptionWidget[]}
 */
MatchModeSelectWidget.prototype.configOptionsToItems = function ( options ) {
	return options.map( ( opt ) => new OO.ui.MenuOptionWidget( opt ) );
};

/**
 * Get the current selected mode
 *
 * @return {string} One of ('AND', 'OR')
 * @see TOPIC_MATCH_MODE_OPTIONS
 */
MatchModeSelectWidget.prototype.getSelectedMode = function () {
	return this.modeSelect.getMenu().findSelectedItem().getData();
};

/**
 * Set the current selected mode
 *
 * @param {string} mode One of ('AND', 'OR')
 * @see TOPIC_MATCH_MODE_OPTIONS
 */
MatchModeSelectWidget.prototype.setSelectedMode = function ( mode ) {
	this.modeSelect.getMenu().selectItemByData( mode );
};

module.exports = MatchModeSelectWidget;
