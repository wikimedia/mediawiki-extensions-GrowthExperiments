( function () {
	'use strict';

	/**
	 * @param {Object} config
	 * @param {string} [config.tasktype] The task type (e.g. "copyedit").
	 * @param {string} [config.difficulty] The difficulty level of the task (e.g. "easy").
	 * @constructor
	 */
	function TaskExplanationWidget( config ) {
		TaskExplanationWidget.super.call( this, config );
		this.config = config;
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
			.text(
				// growthexperiments-homepage-suggestededits-tasktype-shortdescription-copyedit
				// growthexperiments-homepage-suggestededits-tasktype-shortdescription-references
				// growthexperiments-homepage-suggestededits-tasktype-shortdescription-update
				// growthexperiments-homepage-suggestededits-tasktype-shortdescription-links
				// growthexperiments-homepage-suggestededits-tasktype-shortdescription-expand
				mw.message( 'growthexperiments-homepage-suggestededits-tasktype-shortdescription-' + this.config.tasktype ).text()
			);
	};

	TaskExplanationWidget.prototype.getInfo = function () {
		var popupButtonWidget = new OO.ui.PopupButtonWidget( {
				icon: 'info',
				framed: false,
				label: this.getPopupLabel(),
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

	TaskExplanationWidget.prototype.getPopupLabel = function () {
		// growthexperiments-homepage-suggestededits-tasktype-shortdescription-copyedit
		// growthexperiments-homepage-suggestededits-tasktype-shortdescription-references
		// growthexperiments-homepage-suggestededits-tasktype-shortdescription-update
		// growthexperiments-homepage-suggestededits-tasktype-shortdescription-links
		// growthexperiments-homepage-suggestededits-tasktype-shortdescription-expand
		return mw.message( 'growthexperiments-homepage-suggestededits-tasktype-shortdescription-' + this.config.tasktype ).text();
	};

	TaskExplanationWidget.prototype.getTimeEstimate = function () {
		// growthexperiments-homepage-suggestededits-tasktype-time-copyedit
		// growthexperiments-homepage-suggestededits-tasktype-time-references
		// growthexperiments-homepage-suggestededits-tasktype-time-update
		// growthexperiments-homepage-suggestededits-tasktype-time-links
		// growthexperiments-homepage-suggestededits-tasktype-time-expand
		return $( '<div>' )
			.addClass( 'suggested-edits-difficulty-level suggested-edits-difficulty-level-' + this.config.difficulty )
			.text( mw.message( 'growthexperiments-homepage-suggestededits-tasktype-time-' + this.config.tasktype ).text() );
	};

	TaskExplanationWidget.prototype.getDescription = function () {
		return $( '<div>' ).addClass( 'suggested-edits-popup-detail' )
			.append(
				$( '<div>' ).addClass( 'suggested-edits-difficulty-time-estimate' ).append(
					this.getDifficultyIndicator(),
					this.getTimeEstimate()
				),
				$( '<p>' ).text(
					// growthexperiments-homepage-suggestededits-tasktype-description-copyedit
					// growthexperiments-homepage-suggestededits-tasktype-description-references
					// growthexperiments-homepage-suggestededits-tasktype-description-update
					// growthexperiments-homepage-suggestededits-tasktype-description-links
					// growthexperiments-homepage-suggestededits-tasktype-description-expand
					mw.message( 'growthexperiments-homepage-suggestededits-tasktype-description-' + this.config.tasktype ).text()
				)
				// TODO: Add a link with text set from the message of
				// growthexperiments-homepage-suggestededits-tasktype-learn-more-{this.config.tasktype}
			);
	};

	TaskExplanationWidget.prototype.getDifficultyIndicator = function () {
		return $( '<div>' ).addClass( 'suggested-edits-difficulty-indicator' )
			.addClass( 'suggested-edits-difficulty-indicator-' + this.config.difficulty )
			.text( mw.message( 'growthexperiments-homepage-suggestededits-difficulty-indicator-label-' + this.config.difficulty ) );
	};

	TaskExplanationWidget.prototype.getName = function () {
		// growthexperiments-homepage-suggestededits-tasktype-name-copyedit
		// growthexperiments-homepage-suggestededits-tasktype-name-references
		// growthexperiments-homepage-suggestededits-tasktype-name-update
		// growthexperiments-homepage-suggestededits-tasktype-name-links
		// growthexperiments-homepage-suggestededits-tasktype-name-expand
		return $( '<h4>' ).text(
			mw.message( 'growthexperiments-homepage-suggestededits-tasktype-name-' + this.config.tasktype ).text()
		);
	};

	module.exports = TaskExplanationWidget;
}() );
