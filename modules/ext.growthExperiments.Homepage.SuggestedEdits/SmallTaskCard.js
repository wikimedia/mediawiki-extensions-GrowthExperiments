'use strict';

/**
 * Displays a task card. Sort of a standalone module, but not formally defined as such, since
 * ResourceLoader module definitions are expensive. Instead, when including it into some module,
 * include the following files together:
 * - scripts: ext.growthExperiments.Homepage.SuggestedEdits/SmallTaskCard.js,
 *   utils/IconUtils.js
 * - styles: ext.growthExperiments.Homepage.SuggestedEdits/SmallTaskCard.less
 * - messages: growthexperiments-homepage-suggestededits-pageviews
 * - dependencies: oojs-ui.styles.icons-media, ext.growthExperiments.icons
 * and use ext.growthExperiments.DataStore/GrowthTasksApi.js to get the task
 * parameter for the constructor, and the [ "GrowthExperiments\\HomepageHooks", "getTaskTypesJson" ]
 * callback to get the taskTypes parameter.
 */
( function () {
	const IconUtils = require( '../utils/IconUtils.js' );

	/**
	 * @class mw.libs.ge.SmallTaskCard
	 *
	 * @constructor
	 * @param {Object} config
	 * @param {mw.libs.ge.TaskData} [config.task] Task data, as returned by GrowthTasksApi.
	 *   When omitted, the card will show a loading skeleton.
	 * @param {Object} config.taskTypes Task type data, as returned by
	 *   HomepageHooks::getTaskTypesJson.
	 * @param {string|null} [config.taskUrl] The URL the task links to. Will be generated from task
	 *   data when omitted. Null disables linking.
	 */
	function SmallTaskCard( config ) {
		if ( config.task ) {
			this.task = config.task;
			// Must precede parent constructor as getTagName behavior depends on this.
			this.taskUrl = ( 'taskUrl' in config ) ? config.taskUrl : new mw.Title( this.task.title ).getUrl();
			this.taskType = config.taskTypes[ this.task.tasktype ];
		}
		SmallTaskCard.super.call( this, config );
		OO.EventEmitter.call( this );
		this.buildCard();
	}
	OO.inheritClass( SmallTaskCard, OO.ui.Element );
	OO.mixinClass( SmallTaskCard, OO.EventEmitter );

	/**
	 * @inheritDoc
	 */
	SmallTaskCard.prototype.getTagName = function () {
		return ( this.taskUrl === null ) ? 'div' : 'a';
	};

	/**
	 * Build the card DOM.
	 */
	SmallTaskCard.prototype.buildCard = function () {
		// Keep HTML structure in sync with SuggestedEdits::getTaskCard().

		const $image = $( '<div>' ).addClass( 'mw-ge-small-task-card-image mw-ge-small-task-card-image-skeleton' ),
			$title = $( '<span>' ).addClass( 'mw-ge-small-task-card-title skeleton' ),
			$description = $( '<span>' ).addClass( 'mw-ge-small-task-card-description skeleton' ),
			$pageviews = $( '<span>' ).addClass( 'mw-ge-small-task-card-pageviews skeleton' );

		let $taskType;
		if ( this.task ) {
			$title.removeClass( 'skeleton' ).text( this.task.title );
			$image.removeClass( 'mw-ge-small-task-card-image-skeleton' );
			$description.removeClass( 'skeleton' );
			$pageviews.removeClass( 'skeleton' );

			if ( this.task.thumbnailSource ) {
				$image
					.addClass( 'mw-no-invert' )
					.css( 'background-image', 'url("' + this.task.thumbnailSource + '")' );
			} else if ( this.task.thumbnailSource === undefined ) {
				$image.addClass( 'mw-ge-small-task-card-image-skeleton' );
			} else {
				$image.addClass( 'mw-ge-small-task-card-image-placeholder' );
			}

			if ( this.task.description ) {
				$title.addClass( 'mw-ge-small-task-card-title--with-description' );

				$description.text( this.task.description );
			} else if ( this.task.description === undefined ) {
				$description.addClass( 'skeleton' );
			}

			if ( this.task.pageviews ) {
				$pageviews
					.text( mw.message(
						'growthexperiments-homepage-suggestededits-pageviews',
						mw.language.convertNumber( this.task.pageviews ) ).text(),
					)
					.prepend( new OO.ui.IconWidget( { icon: 'chart' } ).$element );
			} else if ( this.task.pageviews === undefined ) {
				$pageviews.addClass( 'skeleton' );
			}
			$taskType = $( '<span>' )
				.addClass( 'mw-ge-small-task-card-tasktype' )
				// The following classes are used here:
				// * mw-ge-small-task-card-tasktype-difficulty-easy
				// * mw-ge-small-task-card-tasktype-difficulty-medium
				// * mw-ge-small-task-card-tasktype-difficulty-hard
				.addClass( 'mw-ge-small-task-card-tasktype-difficulty-' + this.taskType.difficulty )
				// The following icons are used here:
				// * difficulty-easy
				// * difficulty-medium
				// * difficulty-hard
				.prepend( new OO.ui.IconWidget( { icon: 'difficulty-' + this.taskType.difficulty } ).$element );

			$taskType.prepend( IconUtils.getIconElementForTaskType( this.taskType.iconData ) );
			$taskType.append( $( '<span>' )
				.addClass( 'mw-ge-small-task-card-tasktype-taskname' )
				.text( this.taskType.messages.name ),
			);
		}

		const $glue = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-glue' );
		const $cardMetadataContainer = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-metadata-container' )
			.append( $pageviews, $taskType );
		const $cardTextContainer = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-text-container' )
			// Distribute the flex growth to empty glue divs. This will center the
			// title + description within the empty area above the metadata.
			.append( $glue, $title, $description, $glue.clone(), $cardMetadataContainer );
		// eslint-disable-next-line mediawiki/class-doc
		this.$element
			.addClass( 'mw-ge-small-task-card' )
			.addClass( this.task ? 'mw-ge-tasktype-' + this.task.tasktype : '' )
			.addClass( OO.ui.isMobile() ?
				'mw-ge-small-task-card-mobile' : 'mw-ge-small-task-card-desktop' )
			.attr( 'href', this.taskUrl )
			.append( $image, $cardTextContainer );
		this.$element.on( 'click', () => {
			this.emit( 'click' );
		} );
	};

	module.exports = SmallTaskCard;
}() );
