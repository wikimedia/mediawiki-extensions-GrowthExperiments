/**
 * Widget that informs the user that there are no tasks available and what they can do about it
 * Shown in the suggested edits module when there is no task preview
 * available
 *
 * @class mw.libs.ge.MobileNoTasksWidget
 * @extends OO.ui.Widget
 * @constructor
 */
/* eslint-disable mediawiki/class-doc */

function MobileNoTasksWidget() {
	const baseClass = 'growthexperiments-suggestededits-mobilesummary-notasks-widget';

	MobileNoTasksWidget.super.call( this, {
		classes: [ baseClass ],
	} );

	const titleText = mw.message( 'growthexperiments-homepage-suggestededits-mobilesummary-notasks-title' ).text();
	const subtitleText = mw.message( 'growthexperiments-homepage-suggestededits-mobilesummary-notasks-subtitle' ).text();
	const footerText = mw.message( 'growthexperiments-homepage-suggestededits-mobilesummary-footer' ).text();

	this.$widgetWrapper = new OO.ui.Element( {
		classes: [ `${ baseClass }__main` ],
		content: [
			new OO.ui.Element( {
				classes: [ `${ baseClass }__icon` ],
			} ),
			new OO.ui.Element( {
				content: [
					new OO.ui.Element( {
						classes: [ `${ baseClass }__title` ],
						text: titleText,
					} ),
					new OO.ui.Element( {
						classes: [ `${ baseClass }__subtitle` ],
						text: subtitleText,
					} ),
				],
			} ),
		],
	} );

	this.$footer = new OO.ui.Element( {
		classes: [ `${ baseClass }__footer` ],
		text: footerText,
	} );

	this.$element.empty().append(
		this.$widgetWrapper.$element,
		this.$footer.$element,
	);
}

OO.inheritClass( MobileNoTasksWidget, OO.ui.Widget );

module.exports = MobileNoTasksWidget;
