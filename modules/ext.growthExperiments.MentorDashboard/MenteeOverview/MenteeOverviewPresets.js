/**
 * Client for mentee REST API
 */
( function () {
	'use strict';

	function MenteeOverviewPresets() {
		this.optionName = 'growthexperiments-mentee-overview-presets';
	}

	MenteeOverviewPresets.prototype.getPresets = function () {
		return JSON.parse( mw.user.options.get( this.optionName ) ) || {};
	};

	MenteeOverviewPresets.prototype.getPreset = function ( presetName ) {
		return this.getPresets()[ presetName ];
	};

	MenteeOverviewPresets.prototype.setPreset = function ( presetName, value ) {
		var newPresets = this.getPresets();
		newPresets[ presetName ] = value;
		return new mw.Api().saveOption(
			this.optionName,
			JSON.stringify( newPresets )
		);
	};

	module.exports = MenteeOverviewPresets;
}() );
