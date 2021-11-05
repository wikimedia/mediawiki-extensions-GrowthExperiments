'use strict';

/**
 * @param {Object} config
 * @param {string[]} config.gates
 * @param {Object} config.gateConfig
 * @constructor
 */
function QualityGate( config ) {
	this.config = config;
	this.checkHandlers = {
		'image-recommendation': {
			dailyLimit: function () {
				return this.checkDailyLimitForTaskType( 'image-recommendation' );
			}.bind( this )
		}
	};
	this.errorHandlers = {
		'image-recommendation': {
			dailyLimit: function () {
				return this.showImageRecommendationDailyLimitAlertDialog();
			}.bind( this )
		}
	};
}

/**
 * Check all quality gates for a task type.
 *
 * The checkers are defined in this.checkHandlers; the gates to check are defined in each task
 * type (see TaskType.php getQualityGateIds() )
 *
 * @param {string} taskType
 * @return {jQuery.Promise}
 */
QualityGate.prototype.checkAll = function ( taskType ) {
	var promises = [];

	this.config.gates.forEach( function ( gate ) {
		if ( this.checkHandlers[ taskType ][ gate ] ) {
			promises.push( this.checkHandlers[ taskType ][ gate ]() );
		}
	}.bind( this ) );

	return $.when.apply( $, promises );
};

/**
 * Check if the task type passes the daily limit gate.
 *
 * @param {string} taskType
 * @return {jQuery.Promise} A rejected or resolved promise depending on whether the gate is passed
 *   otherwise.
 */
QualityGate.prototype.checkDailyLimitForTaskType = function ( taskType ) {
	if ( this.config.gateConfig[ taskType ].dailyLimit ) {
		return $.Deferred().reject( 'dailyLimit' ).promise();
	}
	return $.Deferred().resolve().promise();
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
 * Show an alert dialog for dailyLimit gate for image-recommendation task type.
 */
QualityGate.prototype.showImageRecommendationDailyLimitAlertDialog = function () {
	// TODO: Instrument the dialog.
	OO.ui.alert( mw.message( 'growthexperiments-addimage-daily-task-limit-exceeded' ).parse(), {
		actions: [ {
			action: 'accept', label: mw.message( 'growthexperiments-addimage-daily-task-limit-exceeded-dialog-button' ).text(), flags: 'primary'
		} ]
	} );
};

module.exports = QualityGate;
