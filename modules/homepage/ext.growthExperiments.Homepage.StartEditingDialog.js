/**
 * @external HomepageModuleLogger
 * @external mw.libs.ge.GrowthTasksApi
 */
var TopicSelectionWidget = require( 'ext.growthExperiments.Homepage.Topics' ).TopicSelectionWidget,
	TaskTypeSelectionWidget = require( './suggestededits/ext.growthExperiments.Homepage.TaskTypeSelectionWidget.js' ),
	ArticleCountWidget = require( './suggestededits/ext.growthExperiments.Homepage.ArticleCountWidget.js' ),
	router = require( 'mediawiki.router' );

/**
 * @param {Object} config
 * @param {string} config.mode Rendering mode. See constants in HomepageModule.php
 * @param {boolean} config.useTopicSelector Whether to show the topic selector in the intro panel
 * @param {boolean} config.useTaskTypeSelector Whether to show the task type selector in the difficulty panel
 * @param {boolean} config.activateWhenDone Whether to activate suggested edits when the user finishes the dialog
 * @param {HomepageModuleLogger} logger
 * @param {mw.libs.ge.GrowthTasksApi} api
 * @constructor
 */
function StartEditingDialog( config, logger, api ) {
	StartEditingDialog.super.call( this, config );
	this.logger = logger;
	this.api = api;
	this.mode = config.mode;
	this.enableTopics = mw.config.get( 'GEHomepageSuggestedEditsEnableTopics' );
	this.useTopicSelector = this.enableTopics && config.useTopicSelector;
	this.useTaskTypeSelector = config.useTaskTypeSelector;
	this.activateWhenDone = config.activateWhenDone;
	this.updateMatchCountDebounced = OO.ui.debounce( this.updateMatchCount.bind( this ) );
}

OO.inheritClass( StartEditingDialog, OO.ui.ProcessDialog );

StartEditingDialog.static.name = 'startediting';
StartEditingDialog.static.size = 'large';
StartEditingDialog.static.title = mw.msg( 'growthexperiments-homepage-startediting-dialog-header' );

StartEditingDialog.static.actions = [
	{
		action: 'close',
		// Use a label without an icon on desktop; an icon without a label on mobile
		label: OO.ui.isMobile() ?
			undefined :
			mw.msg( 'growthexperiments-homepage-startediting-dialog-intro-back' ),
		icon: OO.ui.isMobile() ?
			'close' :
			undefined,
		flags: [ 'safe' ],
		framed: true,
		modes: [ 'intro' ]
	},
	{
		action: 'back',
		// Use a label without an icon on desktop; an icon without a label on mobile
		label: OO.ui.isMobile() ?
			undefined :
			mw.msg( 'growthexperiments-homepage-startediting-dialog-difficulty-back' ),
		icon: OO.ui.isMobile() ?
			'previous' :
			undefined,
		flags: [ 'safe' ],
		framed: true,
		modes: [ 'difficulty' ]
	},
	{
		action: 'difficulty',
		label: mw.msg( 'growthexperiments-homepage-startediting-dialog-intro-forward' ),
		flags: [ 'progressive', 'primary' ],
		framed: true,
		modes: [ 'intro' ]
	},
	// Only used when activateWhenDone is true
	{
		action: 'activate',
		label: OO.ui.isMobile() ?
			mw.msg( 'growthexperiments-homepage-startediting-dialog-difficulty-forward-mobile' ) :
			mw.msg( 'growthexperiments-homepage-startediting-dialog-difficulty-forward' ),
		flags: [ 'progressive', 'primary' ],
		framed: true,
		modes: [ 'difficulty' ]
	},
	// Only used when activateWhenDone is false
	{
		action: 'done',
		label: mw.msg( 'growthexperiments-homepage-startediting-dialog-difficulty-forward-noactivate' ),
		flags: [ 'progressive', 'primary' ],
		framed: true,
		modes: [ 'difficulty' ]
	}
];

StartEditingDialog.prototype.initialize = function () {
	StartEditingDialog.super.prototype.initialize.call( this );

	this.introPanel = this.buildIntroPanel();
	this.articleCounter = new ArticleCountWidget();
	// Default to the maximum so there is not empty space when the footer is
	// first rendered, before the HTTP request has finished.
	this.articleCounter.setCount( 200 );
	this.articleCounterPanelLayout = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false,
		classes: [ OO.ui.isMobile() ?
			'suggested-edits-article-count-panel-layout-mobile' :
			'suggested-edits-article-count-panel-layout-desktop'
		]
	} ).toggle( this.showingTopicSelector() );
	this.articleCounterPanelLayout.$element.append( this.articleCounter.$element );

	this.difficultyPanel = this.buildDifficultyPanel();

	this.panels = new OO.ui.StackLayout();
	this.panels.addItems( [ this.introPanel, this.difficultyPanel ] );
	this.$body.append( this.panels.$element );

	this.$desktopFooter = $( '<div>' ).addClass( 'mw-ge-startediting-dialog-desktopFooter' );
	this.$desktopActions = $( '<div>' ).addClass( 'mw-ge-startediting-dialog-desktopFooter-desktopActions' );
	if ( !OO.ui.isMobile() ) {
		this.$foot.append( this.$desktopFooter );
	} else {
		this.$foot.append( this.articleCounterPanelLayout.$element );
	}

	this.$element.addClass( 'mw-ge-startediting-dialog' );
};

/**
 * Convenience function to check if topic matching is enabled and if any topics exist.
 *
 * @return {boolean}
 */
StartEditingDialog.prototype.topicsAvailable = function () {
	return this.enableTopics && this.topicSelector && this.topicSelector.getSuggestions().length;
};

/**
 * Convenience function to check if the topic selector will be shown.
 *
 * @return {boolean}
 */
StartEditingDialog.prototype.showingTopicSelector = function () {
	return this.useTopicSelector && this.topicsAvailable();
};

StartEditingDialog.prototype.swapPanel = function ( panel ) {
	if ( ( [ 'intro', 'difficulty' ].indexOf( panel ) ) === -1 ) {
		throw new Error( 'Unknown panel: ' + panel );
	}

	this.panels.setItem( this[ panel + 'Panel' ] );
	this.actions.setMode( panel );
};

StartEditingDialog.prototype.attachActions = function () {
	var i, len, actionWidgets = this.actions.get();

	// Parent method
	StartEditingDialog.super.prototype.attachActions.call( this );

	// On desktop, move all actions to the footer
	if ( !OO.ui.isMobile() ) {
		this.$desktopFooter.append( this.articleCounterPanelLayout.$element );
		this.$desktopActions.append(
			this.$safeActions.children(),
			this.$primaryActions.children()
		);
		this.$desktopFooter.append( this.$desktopActions );
		// HACK: OOUI has really aggressive styling for buttons inside ActionWidgets inside
		// ProgressDialogs that's pretty much impossible to override. Because we don't want our
		// buttons to look ugly (with left/right borders but no top/bottom borders), remove
		// the oo-ui-actionWidget class from our ActionWidgets so these OOUI styles don't apply.
		this.$desktopActions.find( '.oo-ui-actionWidget' ).removeClass( 'oo-ui-actionWidget' );
	}

	for ( i = 0, len = actionWidgets.length; i < len; i++ ) {
		// Find the 'activate' button so that we can make it the pending element later
		// (see getActionProcess)
		if ( actionWidgets[ i ].action === 'activate' ) {
			this.$activateButton = actionWidgets[ i ].$button;
		}
	}
};

StartEditingDialog.prototype.getSetupProcess = function ( data ) {
	var dialog = this;
	data = $.extend( {
		actions: this.constructor.static.actions.filter( function ( action ) {
			// If activateWhenDone is true, remove 'done'; otherwise remove 'activate'
			return action.action !== ( dialog.activateWhenDone ? 'done' : 'activate' );
		} )
	}, data );
	return StartEditingDialog.super.prototype.getSetupProcess
		.call( this, data )
		.next( function () {
			if ( this.showingTopicSelector() ) {
				this.updateMatchCount();
			}
			this.swapPanel( 'intro' );
		}, this );
};

StartEditingDialog.prototype.updateMatchCount = function () {
	var topics = this.topicSelector ? this.topicSelector.getSelectedTopics() : [],
		taskTypes = this.taskTypeSelector ?
			this.taskTypeSelector.getSelected() :
			this.api.defaultTaskTypes;

	this.api.fetchTasks( taskTypes, topics ).then( function ( data ) {
		this.articleCounter.setCount( Number( data.tasks.length ) );
	}.bind( this ) );
};

StartEditingDialog.prototype.getActionProcess = function ( action ) {
	var settings, logData,
		dialog = this,
		config = require( './config.json' );
	return StartEditingDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' || action === 'done' ) {
				this.close( { action: action } );
			}
			if ( action === 'difficulty' ) {
				this.logger.log( 'start-startediting', this.mode, 'se-cta-difficulty' );
				this.articleCounterPanelLayout.toggle( this.useTaskTypeSelector );
				this.swapPanel( 'difficulty' );
				// Force scroll position to top.
				this.$body.scrollTop( 0 );
			}
			if ( action === 'back' ) {
				this.articleCounterPanelLayout.toggle( this.showingTopicSelector() );
				this.logger.log( 'start-startediting', this.mode, 'se-cta-back' );
				this.swapPanel( 'intro' );
			}
			if ( action === 'activate' ) {
				// HACK: by default, the pending element is the head, but our head has height 0.
				// So make the 'activate' button the pending element instead, but don't do that in
				// initialization to avoid brief flashes of pending state when switching panels
				// or closing the dialog.
				this.setPendingElement( this.$activateButton );
				settings = {
					'growthexperiments-homepage-suggestededits-activated': 1
				};
				logData = {};
				if ( this.topicSelector ) {
					settings[ config.GENewcomerTasksTopicFiltersPref ] =
						this.topicSelector.getSelectedTopics().length > 0 ?
							JSON.stringify( this.topicSelector.getSelectedTopics() ) :
							null;
					logData.topics = this.topicSelector.getSelectedTopics();
				}
				if ( this.taskTypeSelector ) {
					settings[ 'growthexperiments-homepage-se-filters' ] =
						this.taskTypeSelector.getSelected().length > 0 ?
							JSON.stringify( this.taskTypeSelector.getSelected() ) :
							null;
				}
				return new mw.Api().saveOptions( settings )
					.then( function () {
						mw.user.options.set( settings );
						this.logger.log( 'start-startediting', this.mode, 'se-activate', logData );
						return this.setupSuggestedEditsModule();
					}.bind( this ) ).then( function () {
						dialog.close( { action: 'activate' } );
					} );
			}
		}, this );
};

StartEditingDialog.prototype.getBodyHeight = function () {
	// Measure the height of each panel, and find the tallest one
	var i, oldVisibility, panelHeight,
		maxHeight = 0,
		panels = this.panels.getItems();
	for ( i = 0; i < panels.length; i++ ) {
		// Make the panel visible so we can measure it
		oldVisibility = panels[ i ].isVisible();
		panels[ i ].toggle( true );
		panelHeight = panels[ i ].$element[ 0 ].scrollHeight;
		panels[ i ].toggle( oldVisibility );

		if ( panelHeight > maxHeight ) {
			maxHeight = panelHeight;
		}
	}

	return maxHeight;
};

StartEditingDialog.prototype.buildIntroPanel = function () {
	var $generalIntro, $generalImage, $responseIntro, surveyData, responseData, imageData, imageUrl, generalImageUrl,
		$topicIntro, $topicMessage, $topicSelectorWrapper, $topicDescription, descriptionImage,
		imagePath = mw.config.get( 'wgExtensionAssetsPath' ) + '/GrowthExperiments/images',
		config = require( './config.json' ),
		introLinks = config.GEHomepageSuggestedEditsIntroLinks,
		responseMap = {
			'add-image': {
				image: {
					withoutTopics: 'intro-add-image.svg',
					withTopics: {
						ltr: 'intro-topic-add-image-ltr.svg',
						rtl: 'intro-topic-add-image-rtl.svg'
					}
				},
				labelHtml: mw.message( 'growthexperiments-homepage-startediting-dialog-intro-response-add-image' )
					.params( [ mw.util.getUrl( introLinks.image ) ] )
					.parse()
			},
			'edit-typo': {
				image: {
					withoutTopics: {
						ltr: 'intro-typo-ltr.svg',
						rtl: 'intro-typo-rtl.svg'
					},
					withTopics: {
						ltr: 'intro-topic-typo-ltr.svg',
						rtl: 'intro-topic-typo-rtl.svg'
					}
				},
				labelHtml: mw.message( 'growthexperiments-homepage-startediting-dialog-intro-response-edit-typo' )
					.parse()
			},
			'new-page': {
				image: {
					withoutTopics: {
						ltr: 'intro-new-page-ltr.svg',
						rtl: 'intro-new-page-rtl.svg'
					},
					withTopics: {
						ltr: 'intro-topic-new-page-ltr.svg',
						rtl: 'intro-topic-new-page-rtl.svg'
					}
				},
				labelHtml: mw.message( 'growthexperiments-homepage-startediting-dialog-intro-response-new-page' )
					.params( [ mw.util.getUrl( introLinks.create ) ] )
					.parse()
			},
			'edit-info-add-change': {
				image: {
					withoutTopics: {
						ltr: 'intro-add-info-ltr.svg',
						rtl: 'intro-add-info-rtl.svg'
					},
					withTopics: {
						ltr: 'intro-topic-add-info-ltr.svg',
						rtl: 'intro-topic-add-info-rtl.svg'
					}
				},
				labelHtml: mw.message(
					'growthexperiments-homepage-startediting-dialog-intro-response-edit-info-add-change'
				).parse()
			}
		},
		introPanel = new OO.ui.PanelLayout( { padded: false, expanded: false } );

	try {
		surveyData = JSON.parse( mw.user.options.get( 'welcomesurvey-responses' ) ) || {};
	} catch ( e ) {
		surveyData = {};
	}

	// Construct the topic selector even if this.useTopicSelector is false, because
	// topicsAvailable() needs it
	this.topicSelector = this.enableTopics ? new TopicSelectionWidget() : false;

	generalImageUrl = this.topicsAvailable() ? 'intro-topic-general.svg' : 'intro-heart-article.svg';

	responseData = responseMap[ surveyData.reason ];
	if ( responseData ) {
		imageData = responseData.image[ this.topicsAvailable() ? 'withTopics' : 'withoutTopics' ];
		imageUrl = typeof imageData === 'string' ? imageData : imageData[ this.getDir() ];
	}

	if ( this.topicsAvailable() ) {
		$topicMessage = $( '<div>' )
			.addClass( 'mw-ge-startediting-dialog-intro-topic-message' )
			.append( responseData ?
				responseData.labelHtml :
				[
					$( '<p>' )
						.text( mw.message( 'growthexperiments-homepage-startediting-dialog-intro-header' ).text() ),
					$( '<p>' )
						.text( mw.message( 'growthexperiments-homepage-startediting-dialog-intro-subheader' ).text() )
				]
			);

		$topicIntro = $( '<div>' )
			.addClass( 'mw-ge-startediting-dialog-intro-topic' )
			.append(
				$( '<img>' )
					.addClass( 'mw-ge-startediting-dialog-intro-topic-image' )
					.attr( { src: imagePath + '/' + ( imageUrl || generalImageUrl ) } ),
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-intro-topic-title' )
					.text( mw.message( 'growthexperiments-homepage-startediting-dialog-intro-title' ).text() ),
				$topicMessage
			);

		if ( this.useTopicSelector ) {
			this.topicSelector.connect( this, {
				selectAll: function ( groupId ) {
					this.logger.log( 'suggested-edits', this.mode, 'se-topicfilter-select-all', {
						isCta: true,
						topicGroup: groupId
					} );
				},
				removeAll: function ( groupId ) {
					this.logger.log( 'suggested-edits', this.mode, 'se-topicfilter-remove-all', {
						isCta: true,
						topicGroup: groupId
					} );
				},
				// The "select all" buttons fire many toggleSelection events at once, so debounce them
				toggleSelection: 'updateMatchCountDebounced'
			} );
			$topicSelectorWrapper = $( '<div>' )
				.addClass( 'mw-ge-startediting-dialog-intro-topic-selector' )
				.append(
					$( '<p>' )
						.addClass( 'mw-ge-startediting-dialog-intro-topic-selector-header' )
						.text( mw.message(
							'growthexperiments-homepage-startediting-dialog-intro-topic-selector-header'
						).text() ),
					$( '<p>' )
						.addClass( 'mw-ge-startediting-dialog-intro-topic-selector-subheader' )
						.text( mw.message(
							'growthexperiments-homepage-startediting-dialog-intro-topic-selector-subheader'
						).text() ),
					this.topicSelector.$element
				);

			introPanel.$element.append(
				$topicIntro,
				$topicSelectorWrapper
			);
		} else {
			descriptionImage = OO.ui.isMobile() ? 'intro-topic-description-landscape.svg' :
				'intro-topic-description-square.svg';
			$topicDescription = $( '<div>' )
				.addClass( 'mw-ge-startediting-dialog-intro-topic-description' )
				.append(
					$( '<img>' )
						.addClass( 'mw-ge-startediting-dialog-intro-topic-description-image' )
						.attr( { src: imagePath + '/' + descriptionImage } ),
					$( '<div>' )
						.addClass( 'mw-ge-startediting-dialog-intro-topic-description-textWrapper' )
						.append(
							$( '<p>' )
								.addClass( 'mw-ge-startediting-dialog-intro-topic-description-header' )
								.text( mw.message(
									'growthexperiments-homepage-startediting-dialog-intro-topic-description-header'
								).text() ),
							$( '<p>' )
								.addClass( 'mw-ge-startediting-dialog-intro-topic-description-subheader' )
								.text( mw.message(
									'growthexperiments-homepage-startediting-dialog-intro-topic-description-subheader'
								).text() )
						)
				);
			introPanel.$element.append(
				$topicIntro,
				$topicDescription
			);
		}
	} else {
		$generalImage = $( '<img>' )
			.addClass( 'mw-ge-startediting-dialog-intro-general-image' )
			.attr( { src: imagePath + '/' + generalImageUrl } );

		$generalIntro = $( '<div>' )
			.addClass( 'mw-ge-startediting-dialog-intro-general' )
			.append(
				// Put the image after the first paragraph in general mode (when it isn't floated);
				// otherwise, put it before the first paragraph (when it is floated)
				responseData ?
					$generalImage :
					[],
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-intro-general-title' )
					.text( mw.message( 'growthexperiments-homepage-startediting-dialog-intro-title' ).text() ),
				responseData ?
					[] :
					$generalImage,
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-intro-general-header' )
					.text( mw.message( 'growthexperiments-homepage-startediting-dialog-intro-header' ).text() ),
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-intro-general-subheader' )
					.text( mw.message( 'growthexperiments-homepage-startediting-dialog-intro-subheader' ).text() )
			);

		if ( responseData ) {
			$responseIntro = $( '<div>' )
				.addClass( 'mw-ge-startediting-dialog-intro-response' )
				.append(
					$( '<img>' )
						.addClass( 'mw-ge-startediting-dialog-intro-response-image' )
						.attr( { src: imagePath + '/' + imageUrl } ),
					$( '<p>' )
						.addClass( 'mw-ge-startediting-dialog-intro-response-label' )
						.html( responseData.labelHtml )
				);
			introPanel.$element.addClass( 'mw-ge-startediting-dialog-intro-withresponse' );
		} else {
			$responseIntro = $( [] );
		}

		introPanel.$element.append(
			$generalIntro,
			$responseIntro
		);
	}

	introPanel.$element.prepend( this.buildProgressIndicator( 1, 2 ) );
	return introPanel;
};

StartEditingDialog.prototype.buildDifficultyPanel = function () {
	var difficultyPanel = new OO.ui.PanelLayout( { padded: false, expanded: false } );

	difficultyPanel.$element.append(
		this.buildProgressIndicator( 2, 2 ),
		$( '<div>' )
			.addClass( 'mw-ge-startediting-dialog-difficulty-banner' )
			.append(
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-difficulty-header' )
					.append( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-header' ).parse() ),
				$( '<p>' )
					.addClass( 'mw-ge-startediting-dialog-difficulty-subheader' )
					.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-subheader' ).text() )
			)
	);

	if ( this.useTaskTypeSelector ) {
		this.taskTypeSelector = new TaskTypeSelectionWidget( {
			selectedTaskTypes: require( './suggestededits/DefaultTaskTypes.json' ),
			introLinks: require( './config.json' ).GEHomepageSuggestedEditsIntroLinks,
			classes: [ 'mw-ge-startediting-dialog-difficulty-taskTypeSelector' ]
		} )
			.connect( this, {
				select: function ( topics ) {
					this.actions.get()[ 3 ].setDisabled( topics.length === 0 );
					this.updateMatchCount();
				}
			} );
		difficultyPanel.$element.append( this.taskTypeSelector.$element );
	} else {
		difficultyPanel.$element.append(
			$( '<div>' )
				.addClass( 'mw-ge-startediting-dialog-difficulty-legend' )
				.append( this.buildDifficultyLegend() )
		);
	}
	return difficultyPanel;
};

StartEditingDialog.prototype.buildDifficultyLegend = function () {
	return [ 'easy', 'medium', 'hard' ].map( function ( level ) {
		var classPrefix = 'mw-ge-startediting-dialog-difficulty-',
			labelMsg = 'growthexperiments-homepage-startediting-dialog-difficulty-level-' +
				level + '-label',
			headerMsg = 'growthexperiments-homepage-startediting-dialog-difficulty-level-' +
				level + '-description-header',
			bodyMsg = 'growthexperiments-homepage-startediting-dialog-difficulty-level-' +
				level + '-description-body';
		return $( '<div>' )
			.addClass( [ classPrefix + 'legend-row', classPrefix + 'legend-' + level ] )
			.append(
				$( '<div>' )
					.addClass( [ classPrefix + 'legend-cell', classPrefix + 'legend-label' ] )
					.append(
						// The following icons are used here:
						// * difficulty-easy
						// * difficulty-medium
						// * difficulty-hard
						new OO.ui.IconWidget( { icon: 'difficulty-' + level } ).$element,
						// The following messages are used here:
						// * growthexperiments-homepage-startediting-dialog-difficulty-level-easy-label
						// * growthexperiments-homepage-startediting-dialog-difficulty-level-medium-label
						// * growthexperiments-homepage-startediting-dialog-difficulty-level-hard-label
						$( '<span>' ).text( mw.msg( labelMsg ) )
					),
				$( '<div>' )
					.addClass( [ classPrefix + 'legend-cell', classPrefix + 'legend-description' ] )
					.append(
						$( '<p>' )
							.addClass( classPrefix + 'legend-description-header' )
							// The following messages are used here:
							// * growthexperiments-homepage-startediting-dialog-difficulty-level-easy-header
							// * growthexperiments-homepage-startediting-dialog-difficulty-level-medium-header
							// * growthexperiments-homepage-startediting-dialog-difficulty-level-hard-header
							.text( mw.msg( headerMsg ) ),
						$( '<p>' )
							.addClass( classPrefix + 'legend-description-body' )
							// The following messages are used here:
							// * growthexperiments-homepage-startediting-dialog-difficulty-level-easy-body
							// * growthexperiments-homepage-startediting-dialog-difficulty-level-medium-body
							// * growthexperiments-homepage-startediting-dialog-difficulty-level-hard-body
							.text( mw.msg( bodyMsg ) )
					)
			);
	} );
};

StartEditingDialog.prototype.buildProgressIndicator = function ( currentPage, totalPages ) {
	var i,
		$indicator = $( '<div>' ).addClass( 'mw-ge-startediting-dialog-progress' );
	for ( i = 0; i < totalPages; i++ ) {
		$indicator.append( $( '<span>' )
			.addClass( 'mw-ge-startediting-dialog-progress-indicator' )
			.addClass( i < currentPage ? 'mw-ge-startediting-dialog-progress-indicator-completed' : '' )
		);
	}
	$indicator.append( $( '<span>' )
		.addClass( 'mw-ge-startediting-dialog-progress-label' )
		.text( mw.message( 'growthexperiments-homepage-startediting-dialog-progress' ).params( [
			mw.language.convertNumber( currentPage ),
			mw.language.convertNumber( totalPages )
		] ) )
	);

	return $indicator;
};

/**
 * Rearranges the homepage and loads the suggested edits module.
 * In mobile-details mode, this method won't return and will send the browser to the suggested edits
 * page instead.
 *
 * @return {jQuery.Promise}
 */
StartEditingDialog.prototype.setupSuggestedEditsModule = function () {
	var $homepage, $homepageOverlay, $oldModule, moduleHtml, moduleDependencies;
	if ( this.mode === 'mobile-details' ) {
		window.location.href = mw.util.getUrl( new mw.Title( 'Special:Homepage/suggested-edits' ).toString() );
		// Keep the dialog open while the page is reloading.
		return $.Deferred();
	}

	// eslint-disable-next-line no-jquery/no-global-selector
	$homepage = $( '.growthexperiments-homepage-container:not(.homepage-module-overlay)' );
	// eslint-disable-next-line no-jquery/no-global-selector
	$homepageOverlay = $( '.growthexperiments-homepage-container.homepage-module-overlay' );
	moduleHtml = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ].html;
	moduleDependencies = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ].rlModules;

	// Rearrange the homepage.
	// FIXME needs to be kept in sync with the PHP code. Maybe the homepage layout
	//   (module containers) should be templated and made available via an API or JSON config.
	if ( this.mode === 'desktop' ) {
		// Remove StartEditing submodule from Start module (and update CSS classes for Start).
		$homepage.find( '.growthexperiments-homepage-module-start-startediting' ).remove();
		// Add SuggestedEdits module.
		$homepage.find( '.growthexperiments-homepage-module-start' )
			.addClass( 'growthexperiments-homepage-module-start-startediting-completed' )
			.after( moduleHtml );
		// Move Mentorship module to the sidebar.
		$homepage.find( '.growthexperiments-homepage-module-mentorship' )
			.prependTo( '.growthexperiments-homepage-group-sidebar-subgroup-primary' );
		// Mark suggested edits module as activated.
		$homepage.find( '.growthexperiments-homepage-module-suggested-edits' )
			.addClass( 'activated' );
	} else if ( this.mode === 'mobile-overlay' ) {
		// Update StartEditing module icon.
		$homepage.add( $homepageOverlay )
			.find( '.growthexperiments-homepage-module-start-startediting' )
			.addClass( 'growthexperiments-homepage-module-completed' )
			.find( '.growthexperiments-homepage-module-header-icon' )
			.html( new OO.ui.IconWidget( {
				icon: 'check',
				// see BaseModule::getHeaderIcon
				classes: [ 'oo-ui-image-invert', 'oo-ui-checkboxInputWidget-checkIcon' ]
			} ).$element );
		// Add SuggestedEdits module summary.
		$homepage.find( '.growthexperiments-homepage-module-start' ).parent().after( moduleHtml );
		// Mark suggested edits module as activated.
		$homepage.find( '.growthexperiments-homepage-module-suggested-edits' )
			.addClass( 'activated' )
			.removeClass( 'unactivated' );
	} else if ( this.mode === 'mobile-summary' ) {
		$oldModule = $homepage.find( '.growthexperiments-homepage-module-start-startediting' ).parent();
		// Add SuggestedEdits module summary
		$oldModule.after( moduleHtml );
		// Remove the start-startediting module
		$oldModule.detach();
		// Mark suggested edits module as activated.
		$homepage.find( '.growthexperiments-homepage-module-suggested-edits' )
			.addClass( 'activated' )
			.removeClass( 'unactivated' )
			.each( function ( i, module ) {
				require( 'ext.growthExperiments.Homepage.Mobile' )
					.loadExtraDataForSuggestedEdits( module );
			} );
	}

	return mw.loader.using( moduleDependencies ).then( function ( require ) {
		if ( this.mode === 'mobile-overlay' ) {
			// Replace the current URL, so that when the user exits the overlay or hits the back button,
			// they go to the main page. If we used router.navigate() here, they'd go back to the
			// start module overlay instead.
			window.history.replaceState( null, null, '#/homepage/suggested-edits' );
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		} else if ( this.mode === 'mobile-summary' ) {
			router.navigate( '#/homepage/suggested-edits' );
		}

		// Wait for the module to initialize.
		return require( 'ext.growthExperiments.Homepage.SuggestedEdits' );
	}.bind( this ) );
};

module.exports = StartEditingDialog;
