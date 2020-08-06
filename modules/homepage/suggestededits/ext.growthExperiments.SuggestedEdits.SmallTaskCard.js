'use strict';

/**
 * Displays a task card. Sort of a standalone module, but not formally defined as such, since
 * ResourceLoader module definitions are expensive. Instead, when including it into some module,
 * include the following files together:
 * - styles: homepage/suggestededits/ext.growthExperiments.SuggestedEdits.SmallTaskCard.js
 * - scripts: homepage/suggestededits/ext.growthExperiments.SuggestedEdits.SmallTaskCard.less
 * - messages: growthexperiments-homepage-suggestededits-pageviews
 * - dependencies: oojs-ui.styles.icons-media, ext.growthExperiments.Homepage.icons
 * and use homepage/suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js to get the task
 * parameter for the constructor, and the [ "GrowthExperiments\\HomepageHooks", "getTaskTypesJson" ]
 * callback to get the taskTypes parameter.
 */
( function () {
	/**
	 * @class mw.libs.ge.SmallTaskCard
	 *
	 * @constructor
	 * @param {Object} config
	 * @param {Object} config.task Task data, as returned by GrowthTasksApi.
	 * @param {Object} config.taskTypes Task type data, as returned by
	 *   HomepageHooks::getTaskTypesJson.
	 * @param {string} [config.taskUrl] The URL the task links to. Will be generated from task
	 *   data when omitted.
	 */
	function SmallTaskCard( config ) {
		SmallTaskCard.super.call( this, config );
		OO.EventEmitter.call( this );
		this.task = config.task;
		this.taskType = config.taskTypes[ this.task.tasktype ];
		this.taskUrl = config.taskUrl || new mw.Title( this.task.title ).getUrl();
		this.buildCard();
	}
	OO.inheritClass( SmallTaskCard, OO.ui.Element );
	OO.mixinClass( SmallTaskCard, OO.EventEmitter );
	SmallTaskCard.static.tagName = 'a';

	/**
	 * Build the card DOM.
	 */
	SmallTaskCard.prototype.buildCard = function () {
		var $image, $title, $description, $pageviews, $taskType,
			$cardTextContainer, $glue, $cardMetadataContainer;

		$image = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-image' );
		if ( this.task.thumbnailSource ) {
			$image.css( 'background-image', 'url("' + this.task.thumbnailSource + '")' );
		} else {
			$image.addClass( 'mw-ge-small-task-card-image-placeholder' );
		}
		$title = $( '<span>' )
			.addClass( 'mw-ge-small-task-card-title' )
			.text( this.task.title );

		if ( this.task.description ) {
			$description = $( '<div>' )
				.addClass( 'mw-ge-small-task-card-description' )
				.text( this.task.description );
		}

		if ( this.task.pageviews ) {
			$pageviews = $( '<span>' )
				.addClass( 'mw-ge-small-task-card-pageviews' )
				.text( mw.message( 'growthexperiments-homepage-suggestededits-pageviews',
					mw.language.convertNumber( this.task.pageviews ) ).text() )
				.prepend( new OO.ui.IconWidget( { icon: 'chart' } ).$element );
		}

		$taskType = $( '<span>' )
			.addClass( 'mw-ge-small-task-card-tasktype' )
			// The following classes are used here:
			// * mw-ge-small-task-card-tasktype-difficulty-easy
			// * mw-ge-small-task-card-tasktype-difficulty-medium
			// * mw-ge-small-task-card-tasktype-difficulty-hard
			.addClass( 'mw-ge-small-task-card-tasktype-difficulty-' + this.taskType.difficulty )
			.text( this.taskType.messages.name )
			.prepend( new OO.ui.IconWidget( { icon: 'difficulty-' + this.taskType.difficulty } ).$element );

		$glue = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-glue' );
		$cardMetadataContainer = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-metadata-container' )
			.append( $pageviews, $taskType );
		$cardTextContainer = $( '<div>' )
			.addClass( 'mw-ge-small-task-card-text-container' )
			.append( $title, $description, $glue, $cardMetadataContainer );
		this.$element
			.addClass( 'mw-ge-small-task-card' )
			.addClass( OO.ui.isMobile() ?
				'mw-ge-small-task-card-mobile' : 'mw-ge-small-task-card-desktop' )
			.attr( 'href', this.taskUrl )
			.append( $image, $cardTextContainer );
		this.$element.on( 'click', this.emit.bind( this, 'click' ) );
	};

	module.exports = SmallTaskCard;
}() );
