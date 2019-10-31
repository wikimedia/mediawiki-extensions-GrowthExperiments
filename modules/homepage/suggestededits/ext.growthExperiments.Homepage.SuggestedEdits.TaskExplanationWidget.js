( function () {
	'use strict';

	var taskTypes = require( './TaskTypes.json' );

	/**
	 * @param {Object} config
	 * @param {string} [config.tasktype] The task type (e.g. "copyedit").
	 * @constructor
	 */
	function TaskExplanationWidget( config ) {
		TaskExplanationWidget.super.call( this, config );

		this.taskType = config.tasktype;
		this.taskTypeData = taskTypes[ this.taskType ];
		if ( !this.taskTypeData ) {
			throw new Error( 'Unknown task type ' + this.taskType );
		}

		this.$element.append(
			$( '<div>' ).addClass( 'suggested-edits-task-explanation-wrapper' )
				.append( this.getInfoRow(), this.getDescriptionRow() )
		);

	}

	OO.inheritClass( TaskExplanationWidget, OO.ui.Widget );

	TaskExplanationWidget.prototype.getInfoRow = function () {
		var $infoRow = $( '<div>' ).addClass( 'suggested-edits-title-and-info' );
		$infoRow.append(
			this.getName(),
			this.getInfo().$element,
			this.getDifficultyIndicator()
		);
		return $infoRow;
	};

	TaskExplanationWidget.prototype.getDescriptionRow = function () {
		return $( '<p>' ).addClass( 'suggested-edits-short-description' )
			.text( this.taskTypeData.messages.shortdescription );
	};

	TaskExplanationWidget.prototype.getInfo = function () {
		var popupButtonWidget = new OO.ui.PopupButtonWidget( {
				icon: 'info',
				framed: false,
				label: this.taskTypeData.messages.shortdescription,
				invisibleLabel: true,
				popup: {
					head: true,
					label: this.getName(),
					$content: this.getDescription(),
					padded: true
				}
			} ),
			togglePopup = function ( buttonPopupWidget, toggle ) {
				return function () {
					buttonPopupWidget.getPopup().toggle( toggle );
				};
			};
		popupButtonWidget.$button
			.on( 'mouseenter', togglePopup( popupButtonWidget, true ) );
		return popupButtonWidget;
	};

	TaskExplanationWidget.prototype.getTimeEstimate = function () {
		return $( '<div>' )
			.addClass( 'suggested-edits-difficulty-level suggested-edits-difficulty-level-' + this.taskTypeData.difficulty )
			.text( this.taskTypeData.messages.timeestimate );
	};

	TaskExplanationWidget.prototype.getDescription = function () {
		return $( '<div>' ).addClass( 'suggested-edits-popup-detail' )
			.append(
				$( '<div>' ).addClass( 'suggested-edits-difficulty-time-estimate' ).append(
					this.getDifficultyIndicator(),
					this.getTimeEstimate()
				),
				$( '<p>' ).text( this.taskTypeData.messages.description )
				// TODO: Add a link with text set from the message of
				// growthexperiments-homepage-suggestededits-tasktype-learn-more-{taskType}
			);
	};

	TaskExplanationWidget.prototype.getDifficultyIndicator = function () {
		return $( '<div>' ).addClass( 'suggested-edits-difficulty-indicator' )
			.addClass( 'suggested-edits-difficulty-indicator-' + this.taskTypeData.difficulty )
			.text( mw.message( 'growthexperiments-homepage-suggestededits-difficulty-indicator-label-' + this.taskTypeData.difficulty ) );
	};

	TaskExplanationWidget.prototype.getName = function () {
		return $( '<h4>' ).text( this.taskTypeData.messages.name );
	};

	module.exports = TaskExplanationWidget;
}() );
