const SmallTaskCard = require( '../ext.growthExperiments.Homepage.SuggestedEdits/SmallTaskCard.js' );

/**
 * Widget that displays a pagination text in the form "1 of 2 suggestions"
 * and a preview of the first suggestion (SmallTaskCard).
 *
 * @class mw.libs.ge.TaskPreviewWidget
 * @extends OO.ui.Widget
 * @constructor
 * @param {Object} config
 * @param {Object} config.taskTypes Task type data, as returned by
 *   HomepageHooks::getTaskTypesJson.
 * @param {Object} [config.task] The task preview data merged with the extra PCS
 * @param {number} [config.taskCount] The number of available tasks for preview data,
 * default is 1
 * @param {number} [config.taskPosition] The position of the current task within the queue,
 * 1 based index, default is 1
 */
function TaskPreviewWidget( config ) {
	const defaultConfig = {
		taskCount: 1,
		taskPosition: 1,
	};
	config = Object.assign( defaultConfig, config, {
		classes: [ 'growthexperiments-task-preview-widget' ],
	} );
	TaskPreviewWidget.super.call( this, config );

	this.taskPagination = new OO.ui.Element( {
		classes: [ 'suggested-edits-preview-pager' ],
	} );

	this.taskPagination.$element.html( this.getPaginationHtml(
		config.taskPosition,
		config.taskCount,
	) );

	this.taskCard = new SmallTaskCard( {
		task: config.task,
		taskTypes: config.taskTypes,
		taskUrl: null,
	} );

	this.ctaButton = new OO.ui.Element( {
		classes: [ 'suggested-edits-preview-footer' ],
		content: [
			new OO.ui.ButtonWidget( {
				classes: [ 'suggested-edits-preview-cta-button' ],
				$button: $( '<span>' ),
				flags: [ 'primary', 'progressive' ],
				label: mw.message(
					'growthexperiments-homepage-suggestededits-mobilesummary-footer-button',
				).text(),
			} ),
		],
	} );

	this.$element.empty().append(
		this.taskPagination.$element,
		this.taskCard.$element,
		this.ctaButton.$element,
	);
}

OO.inheritClass( TaskPreviewWidget, OO.ui.Widget );

TaskPreviewWidget.prototype.getPaginationHtml = function ( pageNumber, pageCount ) {
	return mw.message( 'growthexperiments-homepage-suggestededits-pager' )
		.params( [
			mw.language.convertNumber( pageNumber ),
			mw.language.convertNumber( pageCount ),
		] ).parse();
};

module.exports = TaskPreviewWidget;
