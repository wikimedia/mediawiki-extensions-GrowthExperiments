/**
 * Widget that displays the number of edits the user did in the last day.
 * Shown in the suggested edits module when there is no task preview
 * available
 *
 *
 * @class mw.libs.ge.LastDayEditsWidget
 * @extends OO.ui.Widget
 * @constructor
 * @param {Object} [config]
 * @param {number} [config.editCount]
 */

function LastDayEditsWidget( config ) {
	config = $.extend( {}, config, {
		classes: [ 'growthexperiments-last-day-edits-widget' ]
	} );
	LastDayEditsWidget.super.call( this, config );

	this.$editsCount = new OO.ui.Element( {
		classes: [ 'suggested-edits-metric-number' ],
		text: mw.language.convertNumber( config.editCount )
	} );

	this.$editsCountWrapper = new OO.ui.Element( {
		classes: [ 'suggested-edits-main' ],
		content: [
			new OO.ui.Element( {
				classes: [ 'suggested-edits-icon' ]
			} ),
			new OO.ui.Element( {
				classes: [ 'suggested-edits-metric' ],
				content: [
					this.$editsCount,
					new OO.ui.Element( {
						classes: [ 'suggested-edits-metric-subtitle' ],
						text: mw.message(
							'growthexperiments-homepage-suggestededits-mobilesummary-metricssubtitle'
						).params( [
							config.editCount
						] ).text()
					} )
				]
			} )
		]
	} );

	this.$footer = new OO.ui.Element( {
		classes: [ 'suggested-edits-footer' ],
		text: mw.message( 'growthexperiments-homepage-suggestededits-mobilesummary-footer' ).text()
	} );

	this.$element.empty().append(
		this.$editsCountWrapper.$element,
		this.$footer.$element
	);
}

OO.inheritClass( LastDayEditsWidget, OO.ui.Widget );

module.exports = LastDayEditsWidget;
