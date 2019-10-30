( function () {
	'use strict';

	var taskTypes = require( './TaskTypes.json' );

	/**
	 * @param {Object} config
	 * @param {string} [config.taskType] The task type (e.g. "copyedit").
	 * @param {string} config.mode Rendering mode. See constants in HomepageModule.php
	 * @param {HomepageModuleLogger} logger
	 * @constructor
	 */
	function TaskExplanationWidget( config, logger ) {
		TaskExplanationWidget.super.call( this, config );

		this.taskType = config.taskType;
		this.taskTypeData = taskTypes[ this.taskType ];
		if ( !this.taskTypeData ) {
			throw new Error( 'Unknown task type ' + this.taskType );
		}
		this.logger = logger;
		this.mode = config.mode;

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
		popupButtonWidget.getPopup().connect( this, {
			toggle: function ( show ) {
				this.logger.log( 'suggested-edits', this.mode, 'se-task-explanation-' +
					( show ? 'open' : 'close' ), { taskType: this.taskType } );
			}
		} );
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
				$( '<p>' ).text( this.taskTypeData.messages.description ),
				this.getLearnMoreLink()
			);
	};

	TaskExplanationWidget.prototype.getLearnMoreLink = function () {
		if ( !this.taskTypeData.learnMoreLink ) {
			return $( [] );
		}
		return $( '<p>' )
			.append( $( '<a>' )
				.text( mw.message( 'growthexperiments-homepage-suggestededits-tasktype-learn-more' ).text() )
				.attr( 'href', mw.util.getUrl( this.taskTypeData.learnMoreLink ) )
				.on( 'click', function () {
					this.logger.log( 'suggested-edits', this.mode, 'se-explanation-link-click',
						{ taskType: this.taskType } );
				}.bind( this ) )
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
