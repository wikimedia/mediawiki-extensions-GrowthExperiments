'use strict';
/**
 * Widget to switch between topic match modes.
 *
 * @inheritDoc
 *
 * @class mw.libs.ge.MatchModeSelectWidget
 * @extends OO.ui.Widget
 * @param {Object} config
 * @param {string} config.initialValue value to pre-select an option
 * @param {Object[]} config.options available select options. Options
 * required properties: "data", "label". ie: { data: 'OR', label: 'Match ANY' }
 */
function MatchModeSelectWidget( config ) {
	var defaultClasses = [ 'mw-ge-MatchModeSelectWidget' ];
	if ( OO.ui.isMobile() ) {
		defaultClasses.push( 'mw-ge-MatchModeSelectWidget--mobile' );
	}
	config = $.extend( {}, config );
	config.classes = defaultClasses.concat( config.classes );
	MatchModeSelectWidget.super.call( this, config );

	this.hintText = new OO.ui.Element( {
		classes: [ 'mw-ge-MatchModeSelectWidget__description-text' ],
		text: mw.message(
			'growthexperiments-homepage-suggestededits-topics-match-mode-description'
		).text()
	} );

	this.modeSelect = new OO.ui.ButtonSelectWidget( {
		classes: [ 'mw-ge-MatchModeSelectWidget__button-group' ],
		items: this.configOptionsToItems( config.options, config.initialValue )
	} );
	this.modeSelect.connect( this, {
		select: function ( selected ) {
			this.emit( 'toggleSelection', selected.getData() );
		}
	} );
	this.$element.append(
		this.hintText.$element,
		this.modeSelect.$element
	);
}

OO.inheritClass( MatchModeSelectWidget, OO.ui.Widget );

/**
 * Map select options provided in config to OO.ui.ButtonOptionWidget
 * elements
 *
 * @param  {Object[]} options configuration options
 * @param  {string} [initialValue] value to pre-select an option
 * @return {OO.ui.ButtonOptionWidget[]}
 */
MatchModeSelectWidget.prototype.configOptionsToItems = function ( options, initialValue ) {
	var self = this;
	return options.map( function ( opt ) {
		var button = new OO.ui.ButtonOptionWidget( {
			data: opt.data,
			label: opt.label,
			selected: opt.data === initialValue
		} );
		button.on( 'click', function () {
			self.emit( 'onMatchModeClick', opt.data );
		} );
		return button;
	} );
};

/**
 * Get the current selected mode
 *
 * @return {string} One of ('AND', 'OR')
 * @see TOPIC_MATCH_MODE_OPTIONS
 */
MatchModeSelectWidget.prototype.getSelectedMode = function () {
	return this.modeSelect.findSelectedItem().getData();
};

/**
 * Set the current selected mode
 *
 * @param {string} mode One of ('AND', 'OR')
 * @see TOPIC_MATCH_MODE_OPTIONS
 */
MatchModeSelectWidget.prototype.setSelectedMode = function ( mode ) {
	this.modeSelect.selectItemByData( mode );
};

module.exports = MatchModeSelectWidget;
