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
 * and use ext.growthExperiments.Homepage.SuggestedEdits/GrowthTasksApi.js to get the task
 * parameter for the constructor, and the [ "GrowthExperiments\\HomepageHooks", "getTaskTypesJson" ]
 * callback to get the taskTypes parameter.
 */
( function () {
	var IconUtils = require( '../utils/IconUtils.js' );

	/**
	 * @class mw.libs.ge.SmallTaskCard
	 *
	 * @constructor
	 * @param {Object} config
	 * @param {mw.libs.ge.TaskData} config.task Task data, as returned by GrowthTasksApi.
	 * @param {Object} config.taskTypes Task type data, as returned by
	 *   HomepageHooks::getTaskTypesJson.
	 * @param {string|null} [config.taskUrl] The URL the task links to. Will be generated from task
	 *   data when omitted. Null disables linking.
	 */
	function SmallTaskCard( config ) {
		this.task = config.task;
		// Must precede parent constructor as getTagName behavior depends on this.
		this.taskUrl = ( 'taskUrl' in config ) ? config.taskUrl : new mw.Title( this.task.title ).getUrl();
		SmallTaskCard.super.call( this, config );
		OO.EventEmitter.call( this );
		this.taskType = config.taskTypes[ this.task.tasktype ];
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

		var $image, $title, $description, $pageviews, $taskType,
			$cardTextContainer, $glue, $cardMetadataContainer;

		$image = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-image' );
		if ( this.task.thumbnailSource ) {
			$image.css( 'background-image', 'url("' + this.task.thumbnailSource + '")' );
		} else if ( this.task.thumbnailSource === undefined ) {
			$image.addClass( 'mw-ge-small-task-card-image-skeleton' );
		} else {
			$image.addClass( 'mw-ge-small-task-card-image-placeholder' );
		}
		$title = $( '<span>' )
			.addClass( 'mw-ge-small-task-card-title' )
			.text( this.task.title );

		if ( this.task.description ) {
			$title.addClass( 'mw-ge-small-task-card-title--with-description' );

			$description = $( '<div>' )
				.addClass( 'mw-ge-small-task-card-description' )
				.text( this.task.description );
		} else if ( this.task.description === undefined ) {
			$description = $( '<div>' ).addClass( 'mw-ge-small-task-card-description skeleton' );
		}

		if ( this.task.pageviews ) {
			$pageviews = $( '<span>' )
				.addClass( 'mw-ge-small-task-card-pageviews' )
				.text( mw.message( 'growthexperiments-homepage-suggestededits-pageviews',
					mw.language.convertNumber( this.task.pageviews ) ).text() )
				.prepend( new OO.ui.IconWidget( { icon: 'chart' } ).$element );
		} else if ( this.task.pageviews === undefined ) {
			$pageviews = $( '<span>' ).addClass( 'mw-ge-small-task-card-pageviews skeleton' );
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
			.text( this.taskType.messages.name )
		);

		$glue = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-glue' );
		$cardMetadataContainer = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-metadata-container' )
			.append( $pageviews, $taskType );
		$cardTextContainer = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-text-container' )
			// Distribute the flex growth to empty glue divs. This will center the
			// title + description within the empty area above the metadata.
			.append( $glue, $title, $description, $glue.clone(), $cardMetadataContainer );
		// eslint-disable-next-line mediawiki/class-doc
		this.$element
			.addClass( 'mw-ge-small-task-card mw-ge-tasktype-' + this.task.tasktype )
			.addClass( OO.ui.isMobile() ?
				'mw-ge-small-task-card-mobile' : 'mw-ge-small-task-card-desktop' )
			.attr( 'href', this.taskUrl )
			.append( $image, $cardTextContainer );
		this.$element.on( 'click', function () {
			this.emit( 'click' );
		}.bind( this ) );
	};

	module.exports = SmallTaskCard;
}() );
