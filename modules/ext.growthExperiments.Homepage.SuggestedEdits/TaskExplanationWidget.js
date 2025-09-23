( function () {
	'use strict';

	const mobileStatus = mw.loader.getState( 'mobile.startup' ),
		suggestedEditsPeek = require( '../ui-components/SuggestedEditsPeek.js' ),
		Drawer = mobileStatus && mobileStatus !== 'registered' ? require( 'mobile.startup' ).Drawer : undefined,
		IconUtils = require( '../utils/IconUtils.js' );

	/**
	 * @param {Object} config
	 * @param {string} [config.taskType] The task type (e.g. "copyedit").
	 * @param {string} config.mode Rendering mode. See constants in IDashboardModule.php
	 * @param {HomepageModuleLogger} logger
	 * @param {Object} TASK_TYPES Data about each task type
	 *
	 * @constructor
	 */
	function TaskExplanationWidget( config, logger, TASK_TYPES ) {
		TaskExplanationWidget.super.call( this, config );

		this.taskType = config.taskType;
		this.taskTypeData = TASK_TYPES[ this.taskType ];
		if ( !this.taskTypeData ) {
			throw new Error( 'Unknown task type ' + this.taskType );
		}
		this.logger = logger;
		this.mode = config.mode;

		this.$element.append(
			$( '<div>' ).addClass( 'suggested-edits-task-explanation-wrapper' )
				.append(
					this.getInfoRow(),
					suggestedEditsPeek.getDifficultyAndTime(
						this.taskTypeData.difficulty,
						this.taskTypeData.messages.timeestimate,
						this.getIcon(),
					),
					this.getDescriptionRow(),
				),
		);

	}

	OO.inheritClass( TaskExplanationWidget, OO.ui.Widget );

	TaskExplanationWidget.prototype.getInfoRow = function () {
		const $infoRow = $( '<div>' ).addClass( 'suggested-edits-taskexplanation-additional-info' );
		$infoRow.append(
			// Use a span so that wrapped second line text appears in the same
			// line with the info icon.
			this.getName( '<span>' ),
			this.getInfo().$element,
		);
		return $infoRow;
	};

	TaskExplanationWidget.prototype.getIcon = function () {
		return IconUtils.getIconElementForTaskType( this.taskTypeData.iconData, {
			classes: [ 'suggested-edits-task-explanation-icon' ],
		} );
	};

	TaskExplanationWidget.prototype.getDescriptionRow = function () {
		return $( '<p>' ).addClass( 'suggested-edits-short-description' )
			.text( this.taskTypeData.messages.shortdescription );
	};

	TaskExplanationWidget.prototype.getInfo = function () {
		const $name = this.getName( '<h4>' ),
			popupButtonWidget = new OO.ui.PopupButtonWidget( {
				icon: 'info-unpadded',
				framed: false,
				label: this.taskTypeData.messages.shortdescription,
				invisibleLabel: true,
				popup: {
					$content: $name.add( this.getDescription() ),
					padded: true,
				},
				classes: [ 'suggested-edits-task-explanation-info-button' ],
			} );
		popupButtonWidget.$button.on( 'mouseenter', () => {
			if ( 'ontouchstart' in document.documentElement ) {
				// On touch devices mouseenter might fire on a click, just before the
				// click handler, which would then re-close the popup.
				return;
			}
			popupButtonWidget.getPopup().toggle( true );
		} );
		popupButtonWidget.getPopup().$element.on( 'mouseleave', () => {
			popupButtonWidget.getPopup().toggle( false );
		} );
		popupButtonWidget.getPopup().connect( this, {
			toggle: function ( show ) {
				if ( show && OO.ui.isMobile() ) {
					const drawer = new Drawer( {
						children: [
							this.getName( '<h4>' ),
							$( '<div>' )
								.addClass( 'suggested-edits-taskexplanation-additional-info' )
								.html( this.getDescription() ),
						],
						className: 'suggested-edits-taskexplanation-drawer',
						onBeforeHide: function ( innerDrawer ) {
							// Wait for the CSS animation before removing.
							setTimeout( () => {
								innerDrawer.$el.remove();
							}, 250 );
						},
					} );
					document.body.appendChild( drawer.$el[ 0 ] );
					drawer.show();
				}
				this.logger.log( 'suggested-edits', this.mode, 'se-explanation-' +
					( show ? 'open' : 'close' ), { taskType: this.taskType } );
			}.bind( this ),
		} );
		return popupButtonWidget;
	};

	TaskExplanationWidget.prototype.getTimeEstimate = function () {

		return $( '<div>' )
			.addClass( 'suggested-edits-difficulty-level' )
			.text( this.taskTypeData.messages.timeestimate );
	};

	/**
	 * @return {jQuery}
	 */
	TaskExplanationWidget.prototype.getDescription = function () {
		return $( '<div>' ).addClass( 'suggested-edits-popup-detail' )
			.append(
				$( '<div>' )
					.addClass( 'suggested-edits-difficulty-time-estimate' )
					.append(
						this.getIcon(),
						this.getDifficultyIndicator(),
						this.getTimeEstimate(),
					),
				$( '<p>' ).html( this.taskTypeData.messages.description ),
				this.getLearnMoreLink(),
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
				.on( 'click', () => {
					this.logger.log( 'suggested-edits', this.mode, 'se-explanation-link-click',
						{ taskType: this.taskType } );
				} ),
			);
	};

	TaskExplanationWidget.prototype.getDifficultyIndicator = function () {
		return $( '<div>' ).addClass( 'suggested-edits-difficulty-indicator' )
			// The following classes are used here:
			// * suggested-edits-difficulty-indicator-easy
			// * suggested-edits-difficulty-indicator-medium
			// * suggested-edits-difficulty-indicator-hard
			.addClass( 'suggested-edits-difficulty-indicator-' + this.taskTypeData.difficulty )
			.text( mw.message(
				// The following messages are used here:
				// * growthexperiments-homepage-suggestededits-difficulty-indicator-label-easy
				// * growthexperiments-homepage-suggestededits-difficulty-indicator-label-medium
				// * growthexperiments-homepage-suggestededits-difficulty-indicator-label-hard
				'growthexperiments-homepage-suggestededits-difficulty-indicator-label-' + this.taskTypeData.difficulty,
			).text() );
	};

	/**
	 * @param {string} tagName E.g. '<h4>', '<span>'
	 * @return {jQuery}
	 */
	TaskExplanationWidget.prototype.getName = function ( tagName ) {
		return $( tagName )
			.addClass( 'suggested-edits-task-explanation-heading' )
			.text( this.taskTypeData.messages.name );
	};

	module.exports = TaskExplanationWidget;
}() );
