/**
 * Manage presets used for MenteeOverview.
 */
( function () {
	'use strict';

	function MenteeOverviewPresets() {
		this.optionName = 'growthexperiments-mentee-overview-presets';
	}

	/**
	 * Get presets blob.
	 *
	 * @return {Object}
	 */
	MenteeOverviewPresets.prototype.getPresets = function () {
		return JSON.parse( mw.user.options.get( this.optionName ) ) || {};
	};

	/**
	 * Get a preset from the presets blob.
	 *
	 * @param {string} presetName
	 * @return {string|Array|Object|number}
	 */
	MenteeOverviewPresets.prototype.getPreset = function ( presetName ) {
		if ( typeof presetName !== 'string' ) {
			throw new Error( 'presetName ' + JSON.stringify( presetName ) + ' is not a string.' );
		}
		return this.getPresets()[ presetName ];
	};

	/**
	 * @param {string} presetName
	 * @param {string|Array|Object|number} value
	 * @return {jQuery.Promise}
	 */
	MenteeOverviewPresets.prototype.setPreset = function ( presetName, value ) {
		if ( typeof presetName !== 'string' ) {
			throw new Error( 'presetName ' + JSON.stringify( presetName ) + ' is not a string.' );
		}
		var newPresets = this.getPresets();
		newPresets[ presetName ] = value;
		mw.user.options.set( this.optionName, JSON.stringify( newPresets ) );
		return new mw.Api().saveOption(
			this.optionName,
			JSON.stringify( newPresets )
		);
	};

	module.exports = MenteeOverviewPresets;
}() );
