'use strict';

const ImageSuggestionInteractionLogger = require(
	'../ext.growthExperiments.StructuredTask/addimage/ImageSuggestionInteractionLogger.js',
);
const LinkSuggestionInteractionLogger = require(
	'../ext.growthExperiments.StructuredTask/addlink/LinkSuggestionInteractionLogger.js',
);

/**
 * @param {Object} config
 * @param {string[]} config.gates
 * @param {Object} config.gateConfig
 * @param {Object} config.loggers Loggers for each task type.
 * @param {Object} config.loggerMetadataOverrides Overrides to pass to the logger.log() call.
 * @constructor
 */
function QualityGate( config ) {
	this.config = config;
	this.checkHandlers = {
		'image-recommendation': {
			dailyLimit: function () {
				return this.checkDailyLimitForTaskType( 'image-recommendation' );
			}.bind( this ),
		},
		'section-image-recommendation': {
			dailyLimit: function () {
				return this.checkDailyLimitForTaskType( 'section-image-recommendation' );
			}.bind( this ),
		},
		'link-recommendation': {
			dailyLimit: function () {
				return this.checkDailyLimitForTaskType( 'link-recommendation' );
			}.bind( this ),
		},
	};
	this.errorHandlers = {
		'image-recommendation': {
			dailyLimit: function () {
				return this.showImageRecommendationDailyLimitAlertDialog();
			}.bind( this ),
		},
		'section-image-recommendation': {
			dailyLimit: function () {
				return this.showSectionImageRecommendationDailyLimitAlertDialog();
			}.bind( this ),
		},
		'link-recommendation': {
			dailyLimit: function () {
				return this.showLinkRecommendationDailyLimitAlertDialog();
			}.bind( this ),
		},
	};
	this.loggers = config.loggers;
	// Used for alert dialogs. showAlertDialog() creates it, so the gate check needs no OOUI.
	this.windowManager = null;
}

/**
 * Make a quality gate for a task, with the loggers for the quality gate dialog.
 *
 * @param {mw.libs.ge.TaskData} task
 * @param {Object} options
 * @param {string|null} options.pageviewToken The pageview token of the page that shows the task
 * @param {Object} [options.gateConfig] Quality gate config. The default is the config of the task.
 * @return {QualityGate}
 */
QualityGate.newFromTask = function ( task, options ) {
	/* eslint-disable camelcase */
	const loggerConfig = {
		is_mobile: mw.config.get( 'skin' ) === 'minerva',
		active_interface: 'qualitygate_dialog',
	};
	return new QualityGate( {
		gates: task.qualityGateIds || [],
		gateConfig: options.gateConfig || task.qualityGateConfig || {},
		loggers: {
			'image-recommendation': new ImageSuggestionInteractionLogger( loggerConfig ),
			'section-image-recommendation': new ImageSuggestionInteractionLogger( loggerConfig ),
			'link-recommendation': new LinkSuggestionInteractionLogger( loggerConfig ),
		},
		loggerMetadataOverrides: {
			newcomer_task_token: task.token,
			homepage_pageview_token: options.pageviewToken,
			page_id: task.pageId,
			page_title: task.title,
		},
	} );
	/* eslint-enable camelcase */
};

/**
 * @param {string} taskType
 * @return {string|null} The ID of the failed gate, null if no gate failed.
 */
QualityGate.prototype.getFailedGate = function ( taskType ) {
	const handlers = this.checkHandlers[ taskType ] || {};
	const failedGate = this.config.gates.find(
		( gate ) => handlers[ gate ] && !handlers[ gate ](),
	);
	return failedGate || null;
};

/**
 * Check all quality gates for a task type.
 *
 * The checkers are defined in this.checkHandlers; the gates to check are defined in each task
 * type (see TaskType.php getQualityGateIds() )
 *
 * @param {string} taskType
 * @return {boolean} Whether the task passed the gates.
 */
QualityGate.prototype.checkAll = function ( taskType ) {
	const gate = this.getFailedGate( taskType );
	if ( gate ) {
		this.handleGateFailure( taskType, gate );
		return false;
	}
	return true;
};

/**
 * Check if the task type passes the daily limit gate.
 *
 * "dailyLimit" is set to true if the user has exceeded the maxTasksPerDay value in
 * NewcomerTasks.json. The value is exported in QualityGateDecorator.php
 *
 * @param {string} taskType
 * @return {boolean} Whether the task passed the gate.
 */
QualityGate.prototype.checkDailyLimitForTaskType = function ( taskType ) {
	return !this.config.gateConfig[ taskType ].dailyLimit;
};

/**
 * Handle failure for a particular gate.
 *
 * @param {string} taskType
 * @param {string} gate The ID of the gate, e.g. 'dailyLimit'. Corresponds to an entry in
 *   this.errorHandlers.
 */
QualityGate.prototype.handleGateFailure = function ( taskType, gate ) {
	this.errorHandlers[ taskType ][ gate ]();
};

/**
 * Log that the dialog for a failed gate is shown.
 *
 * Use this when the caller shows its own dialog, not the alert dialog of this class.
 *
 * @param {string} taskType
 * @param {string} gate The ID of the failed gate, e.g. 'dailyLimit'
 */
QualityGate.prototype.logImpression = function ( taskType, gate ) {
	this.loggers[ taskType ].log( 'impression', gate, this.config.loggerMetadataOverrides );
};

/**
 * Show an alert dialog for dailyLimit gate for image-recommendation task type.
 */
QualityGate.prototype.showImageRecommendationDailyLimitAlertDialog = function () {
	this.loggers[ 'image-recommendation' ].log( 'impression', 'dailyLimit', this.config.loggerMetadataOverrides );
	this.showAlertDialog(
		'dailyLimit',
		mw.message( 'growthexperiments-addimage-daily-task-limit-exceeded' ).parse(),
		{
			action: 'accept',
			label: mw.message( 'growthexperiments-addimage-daily-task-limit-exceeded-dialog-button' ).text(),
			flags: 'primary',
		},
	);
};

/**
 * Show an alert dialog for dailyLimit gate for section-image-recommendation task type.
 */
QualityGate.prototype.showSectionImageRecommendationDailyLimitAlertDialog = function () {
	this.loggers[ 'section-image-recommendation' ].log( 'impression', 'dailyLimit', this.config.loggerMetadataOverrides );
	this.showAlertDialog(
		'dailyLimit',
		mw.message( 'growthexperiments-addsectionimage-daily-task-limit-exceeded' ).parse(),
		{
			action: 'accept',
			label: mw.message( 'growthexperiments-addsectionimage-daily-task-limit-exceeded-dialog-button' ).text(),
			flags: 'primary',
		},
	);
};

/**
 * Show an alert dialog.
 *
 * @param {string} qualityGateId The dialog identifier
 * @param {string} message
 * @param {Object} action
 * @param {string} action.action The type of action to show, e.g. "accept" or "reject".
 * @param {string} action.label The label to show with the action button
 * @param {string|Array} action.flags The flags to use with the action, e.g. 'primary'
 * @return {OO.ui.WindowInstance}
 */
QualityGate.prototype.showAlertDialog = function ( qualityGateId, message, action ) {
	const messageDialog = new OO.ui.MessageDialog();
	messageDialog.$element
		.addClass( 'ge-qualitygate-alert-dialog' )
		// The following classes are used here:
		// * ge-qualitygate-alert-dialog-dailyLimit
		// * eslint insists on having one more line here
		.addClass( 'ge-qualitygate-alert-dialog-' + qualityGateId );
	if ( !this.windowManager ) {
		this.windowManager = new OO.ui.WindowManager();
		$( document.body ).append( this.windowManager.$element );
	}
	this.windowManager.addWindows( [ messageDialog ] );
	return this.windowManager.openWindow( messageDialog, {
		message: message,
		actions: [ action ],
	} );
};

/**
 * Show an alert dialog for dailyLimit gate for link-recommendation task type.
 */
QualityGate.prototype.showLinkRecommendationDailyLimitAlertDialog = function () {
	this.loggers[ 'link-recommendation' ].log( 'impression', 'dailyLimit', this.config.loggerMetadataOverrides );
	this.showAlertDialog(
		'dailyLimit',
		mw.message( 'growthexperiments-addlink-daily-task-limit-exceeded' ).parse(),
		{
			action: 'accept',
			label: mw.message( 'growthexperiments-addlink-daily-task-limit-exceeded-dialog-button' ).text(),
			flags: 'primary',
		},
	);
};

module.exports = QualityGate;
