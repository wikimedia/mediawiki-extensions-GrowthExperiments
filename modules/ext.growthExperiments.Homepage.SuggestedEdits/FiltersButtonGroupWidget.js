const CONSTANTS = require( 'ext.growthExperiments.DataStore' ).CONSTANTS,
	TOPIC_MATCH_MODES = CONSTANTS.TOPIC_MATCH_MODES,
	TOPIC_DATA = CONSTANTS.TOPIC_DATA,
	ALL_TASK_TYPES = CONSTANTS.ALL_TASK_TYPES;

/**
 * Shared "is the interest selector open?" flag, backing the one Vue app mounted for the page.
 *
 * The module can be rendered more than once (on mobile, once for the server-rendered summary
 * and again when the overlay HTML loads), so the app is created lazily and reused rather than
 * mounted per widget.
 *
 * @type {Object|null} A Vue ref, or null before the app is created.
 */
let interestSelectorOpen = null;

/**
 * Get the shared flag controlling the interest selector, mounting its Vue app on first use.
 *
 * Also subscribes to the hook that other modules use to ask for the selector. That hook has to
 * be the trigger rather than the route, because the mobile overlay is built once and cached, so
 * a second visit to the same route neither rebuilds this widget nor re-reads the URL.
 *
 * mw.hook remembers its last fire, so a request made before this module finished loading still
 * reaches the handler registered here.
 *
 * @return {Object} A Vue ref holding whether the interest selector is open.
 */
function getInterestSelectorOpenRef() {
	if ( interestSelectorOpen ) {
		return interestSelectorOpen;
	}
	const Vue = require( 'vue' );
	const InterestSelectorDialog = require( './InterestSelectorDialog.vue' );
	interestSelectorOpen = Vue.ref( false );
	const mountPoint = document.createElement( 'div' );
	mountPoint.classList.add( 'growth-experiments-interest-selector-vue-app' );
	document.body.appendChild( mountPoint );
	Vue.createMwApp( {
		render: () => Vue.h( InterestSelectorDialog, {
			open: interestSelectorOpen.value,
			'onUpdate:open': ( value ) => {
				interestSelectorOpen.value = value;
			},
		} ),
	} ).mount( mountPoint );
	// The selector is mounted on the body rather than inside the mobile overlay, so closing
	// the overlay would otherwise leave it hanging over the summary. Its own watcher discards
	// whatever was selected, the same as any other dismissal.
	// Subscribed before the request below so that, in the unlikely event both hooks have
	// already been fired by the time this runs, the request to open wins.
	mw.hook( 'growthExperiments.mobileOverlayClosed.suggested-edits' ).add( () => {
		interestSelectorOpen.value = false;
	} );
	// Keep the hook name in sync with SuggestedEditsMobileSummary.js, which fires it.
	mw.hook( 'growthExperiments.openInterestSelector' ).add( () => {
		interestSelectorOpen.value = true;
	} );
	return interestSelectorOpen;
}

/**
 * @extends OO.ui.ButtonGroupWidget
 *
 * @param {Object} config Configuration options
 * @param {boolean} config.topicMatching If the topic filters should be enabled in the UI.
 * @param {boolean} config.useTopicMatchMode If topic match mode feature is enabled in the UI
 * @param {string} config.mode Rendering mode. See constants in IDashboardModule.php
 * @param {mw.libs.ge.DataStore} rootStore
 * @constructor
 */
function FiltersButtonGroupWidget( config, rootStore ) {
	const DifficultyFiltersDialog = require( './DifficultyFiltersDialog.js' ),
		TopicFiltersDialog = require( './TopicFiltersDialog.js' ),
		windowManager = new OO.ui.WindowManager( { modal: true } ),
		windows = [],
		buttonWidgets = [];

	this.mode = config.mode;
	this.topicMatching = config.topicMatching;
	this.filtersStore = rootStore.newcomerTasks.filters;
	this.interestsEnabled = this.filtersStore.interestsEnabled;

	if ( this.topicMatching ) {
		const shouldShowFunnelAddIcon = config.useTopicMatchMode && this.filtersStore.topicsMatchMode === TOPIC_MATCH_MODES.AND;
		// Button label is set in #updateButtonLabelAndIcon
		// eslint-disable-next-line mediawiki/no-unlabeled-buttonwidget
		this.topicFilterButtonWidget = new OO.ui.ButtonWidget( {
			icon: shouldShowFunnelAddIcon ? 'funnel-add' : 'funnel',
			classes: [ 'topic-matching', 'topic-filter-button' ],
			indicator: config.mode === 'desktop' ? null : 'down',
		} );
		buttonWidgets.push( this.topicFilterButtonWidget );
		if ( this.interestsEnabled ) {
			this.topicFilterButtonWidget.$element.addClass( 'interest-filter-button' );
			this.showInterestSelector = getInterestSelectorOpenRef();
		} else {
			this.topicFiltersDialog = new TopicFiltersDialog( rootStore ).connect( this, {
				done: function ( promise ) {
					this.emit( 'done', promise );
				},
				search: function () {
					this.emit( 'search' );
				},
				cancel: [ 'emit', 'cancel' ],
			} );
			this.topicFiltersDialog.$element.addClass( 'suggested-edits-topic-filters' );
			windows.push( this.topicFiltersDialog );
		}
	}

	// Button label is set in #updateButtonLabelAndIcon
	// eslint-disable-next-line mediawiki/no-unlabeled-buttonwidget
	this.difficultyFilterButtonWidget = new OO.ui.ButtonWidget( {
		icon: 'difficulty-outline',
		classes: this.topicMatching ? [ 'topic-matching', 'difficulty-filter-button' ] : [ '' ],
		indicator: config.mode === 'desktop' ? null : 'down',
	} );
	buttonWidgets.push( this.difficultyFilterButtonWidget );

	this.taskTypeFiltersDialog = new DifficultyFiltersDialog( rootStore ).connect( this, {
		done: function ( promise ) {
			this.emit( 'done', promise );
		},
		search: function () {
			this.emit( 'search' );
		},
		cancel: function () {
			this.emit( 'cancel' );
		},
	} );
	windows.push( this.taskTypeFiltersDialog );

	// eslint-disable-next-line no-jquery/no-global-selector
	$( 'body' ).append( windowManager.$element );
	windowManager.addWindows( windows );
	this.difficultyFilterButtonWidget.on( 'click', () => {
		windowManager.openWindow( this.taskTypeFiltersDialog );
		this.emit( 'open' );
	} );

	if ( this.topicFilterButtonWidget ) {
		this.topicFilterButtonWidget.on( 'click', () => {
			if ( this.interestsEnabled ) {
				this.showInterestSelector.value = true;
				// No 'open' event here. It backs up the task queue so that cancelling the OOUI
				// dialog can restore it, and it makes SuggestedEditsModule hold off on
				// redrawing the card until 'done' or 'cancel' arrives. Closing the interest
				// selector applies the selection instead of offering a cancel, so neither
				// event is ever emitted and 'open' would leave the card frozen.
				return;
			}
			windowManager.openWindow( this.topicFiltersDialog );
			this.emit( 'open' );
		} );
	}

	FiltersButtonGroupWidget.super.call( this, Object.assign( {}, config, {
		items: buttonWidgets,
	} ) );

	if (
		mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Homepage' &&
		window.location.hash === '#/homepage/suggested-edits/openTaskTypeDialog' &&
		mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' )
	) {
		setTimeout( () => {
			this.difficultyFilterButtonWidget.emit( 'click' );
		}, 0 );
	}

	this.filtersStore.on( CONSTANTS.EVENTS.FILTER_SELECTION_CHANGED, () => {
		this.taskTypeFiltersDialog.taskTypeSelector.setSelected( this.filtersStore.getSelectedTaskTypes() );
		if ( this.topicFiltersDialog ) {
			this.topicFiltersDialog.topicSelector.setFilters( this.filtersStore.getTopicsQuery() );
		}
	} );

	rootStore.newcomerTasks.on( CONSTANTS.EVENTS.TASK_QUEUE_LOADING, ( isLoading ) => {
		this.taskTypeFiltersDialog.updateLoadingState( { isLoading: isLoading, count: rootStore.newcomerTasks.getTaskCount() } );
		if ( this.topicFiltersDialog ) {
			this.topicFiltersDialog.updateLoadingState( { isLoading: isLoading, count: rootStore.newcomerTasks.getTaskCount() } );
		}
	} );
}

OO.inheritClass( FiltersButtonGroupWidget, OO.ui.ButtonGroupWidget );

/**
 * Update the article count in FiltersDialog, called from SuggestedEditsModule when the
 * article count changes when user selects a filter or cancels from FiltersDialog
 *
 * @param {number} count
 */
FiltersButtonGroupWidget.prototype.updateMatchCount = function ( count ) {
	this.taskTypeFiltersDialog.updateMatchCount( count );
	if ( this.topicFiltersDialog ) {
		this.topicFiltersDialog.updateMatchCount( count );
	}
};

/**
 * Update the state of the dialog header to "in progress" while the
 * NewcomerTaskStore is loading.
 *
 * @param {Object} state The relevant state properties
 * @param {boolean} state.isLoading Whereas the NewcomerTaskStore is fetching results
 * @param {number} state.count The number of tasks in the store queue
 */
FiltersButtonGroupWidget.prototype.updateLoadingState = function ( state ) {
	this.taskTypeFiltersDialog.updateLoadingState( state );
	if ( this.topicFiltersDialog ) {
		this.topicFiltersDialog.updateLoadingState( state );
	}
};

/**
 * Label the filter button with the user's interests.
 *
 * Keep this function in sync with HomepageModules\SuggestedEdits::getInterestFilterButtonWidget()
 *
 * TODO: The topic branch of updateButtonLabelAndIcon() nudges the user towards the dialog with
 * the progressive flag and a pulsating dot until they have engaged with it, keyed on
 * preferences.topicFilters being null rather than on the current selection being empty. There is
 * no such first-run nudge here, and adding one needs that same distinction, which the interests
 * are currently missing: wgGEInterestArticles is an empty array both for users who never picked
 * interests and for users who picked some and then removed them all. Disambiguating it means
 * reading growthexperiments-interest-articles-editing and telling an unset preference from a
 * stored empty list.
 */
FiltersButtonGroupWidget.prototype.updateInterestButtonLabel = function () {
	const interests = this.filtersStore.getSelectedInterests();
	let label;
	if ( !interests.length ) {
		label = mw.message(
			'growthexperiments-homepage-suggestededits-interest-filter-select-interests',
		).text();
	} else if ( interests.length < 3 ) {
		label = interests.join( mw.msg( 'comma-separator' ) );
	} else {
		label = mw.message(
			'growthexperiments-homepage-suggestededits-interests-button-interest-count',
		).params( [ mw.language.convertNumber( interests.length ) ] ).text();
	}
	this.topicFilterButtonWidget.setLabel( label );
	this.topicFilterButtonWidget.setIcon( 'funnel' );
};

/**
 * Update the button label and icon depending on task types selected.
 *
 * Keep this function in sync with HomepageModules\SuggestedEdits::getFiltersButtonGroupWidget()
 *
 * @param {string[]} taskTypeSearch List of task types to search for
 * @param {mw.libs.ge.TopicFilters} topicSearch TopicFilters object with list
 * of topics to search for and match mode
 */
FiltersButtonGroupWidget.prototype.updateButtonLabelAndIcon = function (
	taskTypeSearch, topicSearch,
) {
	const levels = {},
		topicMessages = [],
		isMatchModeAND = topicSearch &&
			topicSearch.getTopicsMatchMode() === TOPIC_MATCH_MODES.AND,
		messages = [];
	let topicLabel = '',
		separator = '';

	if ( this.topicFilterButtonWidget && this.interestsEnabled ) {
		this.updateInterestButtonLabel();
	} else if ( this.topicFilterButtonWidget ) {
		if ( !topicSearch.hasFilters() ) {
			this.topicFilterButtonWidget.setLabel(
				mw.message( 'growthexperiments-homepage-suggestededits-topic-filter-select-interests' ).text(),
			);
			// topicPresets will be a TopicFilters object if the user had saved topics
			// in the past, or null if they have never saved topics
			this.topicFilterButtonWidget.setFlags( {
				progressive: !this.filtersStore.preferences.topicFilters,
			} );
		} else {
			topicSearch.getTopics().forEach( ( topic ) => {
				if ( TOPIC_DATA[ topic ] && TOPIC_DATA[ topic ].name ) {
					topicMessages.push( TOPIC_DATA[ topic ].name );
				}
			} );
			// Unset the pulsating blue dot if it exists.
			this.topicFilterButtonWidget.$element.find( '.mw-pulsating-dot' ).remove();
			this.topicFilterButtonWidget.setFlags( { progressive: false } );
		}
		if ( topicMessages.length ) {
			if ( topicMessages.length < 3 ) {
				separator = isMatchModeAND ? ' + ' : mw.msg( 'comma-separator' );
				topicLabel = topicMessages.join( separator );
			} else {
				topicLabel = mw.message(
					'growthexperiments-homepage-suggestededits-topics-button-topic-count',
				).params( [ mw.language.convertNumber( topicMessages.length ) ] )
					.text();
			}
			this.topicFilterButtonWidget.setLabel( topicLabel );
		}
		this.topicFilterButtonWidget.setIcon( isMatchModeAND ? 'funnel-add' : 'funnel' );
	}

	if ( !taskTypeSearch.length ) {
		// User has deselected all filters, set generic outline and message in button label.
		this.difficultyFilterButtonWidget.setLabel(
			mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' ).text(),
		);
		this.difficultyFilterButtonWidget.setIcon( 'difficulty-outline' );
		return;
	}

	taskTypeSearch.forEach( ( taskType ) => {
		levels[ ALL_TASK_TYPES[ taskType ].difficulty ] = true;
	} );
	[ 'easy', 'medium', 'hard' ].forEach( ( level ) => {
		if ( !levels[ level ] ) {
			return;
		}
		// The following messages are used here:
		// * growthexperiments-homepage-suggestededits-difficulty-filter-label-easy
		// * growthexperiments-homepage-suggestededits-difficulty-filter-label-medium
		// * growthexperiments-homepage-suggestededits-difficulty-filter-label-hard
		const label = mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-label-' +
			level ).text();
		messages.push( label );
		// Icons: difficulty-easy, difficulty-medium, difficulty-hard
		this.difficultyFilterButtonWidget.setIcon( 'difficulty-' + level );
	} );
	if ( messages.length > 1 ) {
		this.difficultyFilterButtonWidget.setIcon( 'difficulty-outline' );
	}

	this.difficultyFilterButtonWidget.setLabel(
		mw.message( this.mode === 'desktop' ?
			'growthexperiments-homepage-suggestededits-difficulty-filter-label' :
			'growthexperiments-homepage-suggestededits-difficulty-filter-label-mobile',
		)
			.params( [ messages.join( mw.msg( 'comma-separator' ) ) ] )
			.text(),
	);
};

module.exports = FiltersButtonGroupWidget;
