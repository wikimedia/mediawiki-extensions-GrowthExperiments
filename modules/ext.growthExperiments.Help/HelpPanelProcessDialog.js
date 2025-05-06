( function () {

	/**
	 * @class mw.libs.ge.HelpPanelProcessDialog
	 * @extends OO.ui.ProcessDialog
	 *
	 * @constructor
	 * @param {Object} config
	 * @param {mw.libs.ge.HelpPanelLogger} config.logger
	 * @param {string} [config.layoutType] Dialog layout: 'helppanel' (Growth help panel look) or 'dialog'
	 *   (standard OOUI process dialog look). Default is 'helppanel'. This only affects the few
	 *   layout options which have to be handled dynamically in JS code.
	 * @param {string} config.askSource Logical name of the source to use with the HelpPanelPostQuestion API
	 * @param {SuggestedEditSession} config.suggestedEditSession The suggested edit session
	 * @param {string} config.taskTypeId The ID of the suggested edit task type.
	 * @param {boolean} [config.isQuestionPoster] Whether the dialog from QuestionPoster (Special:Homepage)
	 */
	const configData = require( './data.json' ),
		SuggestedEditsPanel = require( './HelpPanelProcessDialog.SuggestedEditsPanel.js' ),
		TASK_TYPES = require( 'ext.growthExperiments.DataStore' ).CONSTANTS.ALL_TASK_TYPES,
		linksConfig = configData.GEHelpPanelLinks,
		HelpPanelProcessDialog = function helpPanelProcessDialog( config ) {
			HelpPanelProcessDialog.super.call( this, config );
			this.useHelpPanelLayout = ( config.layoutType !== 'dialog' );
			this.suggestedEditSession = config.suggestedEditSession;
			this.suggestedEditSession.connect( this, {
				save: 'onSuggestedEditSessionSave'
			} );
			this.logger = config.logger;
			this.askHelpEnabled = config.askHelpEnabled;
			this.taskTypeId = config.taskTypeId;
			this.panelTitleMessages = {
				// Messages for ask-help and questioncomplete are added later via
				// AskHelpPanel.getPanelTitleMessages since they vary based on panel context.
				home: mw.message( 'growthexperiments-help-panel-home-title' ).text(),
				'general-help': mw.message( 'growthexperiments-help-panel-general-help-title' ).text(),
				'suggested-edits': mw.message( 'growthexperiments-help-panel-suggestededits-title' ).text()
			};
			this.isQuestionPoster = config.isQuestionPoster;
			this.askSource = config.askSource;
		},
		HelpPanelSearchWidget = require( './HelpPanelSearchWidget.js' ),
		HelpPanelHomeButtonWidget = require( './HelpPanelHomeButtonWidget.js' ),
		AskHelpPanel = require( './AskHelpPanel.js' ),
		MIN_DIALOG_HEIGHT = 368;

	OO.inheritClass( HelpPanelProcessDialog, OO.ui.ProcessDialog );

	HelpPanelProcessDialog.static.name = 'HelpPanel';
	HelpPanelProcessDialog.static.actions = [
		// The "Publish" button on the ask help subpanel, see onAskHelpTextInputChange.
		{
			label: OO.ui.deferMsg( 'growthexperiments-help-panel-submit-question-button-text' ),
			modes: [ 'ask-help', 'ask-help-locked' ],
			classes: [ 'mw-ge-help-panel-post' ],
			flags: [ 'progressive', 'primary' ],
			action: 'questioncomplete'
		},
		{
			label: mw.message( 'growthexperiments-help-panel-return-home-button-text' ).text(),
			modes: [ 'questioncomplete' ],
			flags: [ 'progressive', 'primary' ],
			classes: [ 'mw-ge-help-panel-done' ],
			action: 'home'
		},
		{
			label: mw.message( 'growthexperiments-help-panel-close' ).text(),
			modes: [ 'questioncomplete-locked' ],
			flags: [ 'progressive', 'primary' ],
			classes: [ 'mw-ge-help-panel-done' ],
			action: 'close'
		},
		// Show a close icon in the primary position (so it doesn't interfere with the back
		// icon), except in ask-help / questioncomplete which already have a primary action.
		{
			flags: [ 'primary', 'close' ],
			action: 'close',
			framed: false,
			modes: [ 'home', 'general-help', 'general-help-locked', 'suggested-edits',
				'suggested-edits-locked', 'search' ]
		},
		{
			icon: 'close',
			flags: 'safe',
			action: 'close',
			modes: [ 'ask-help-locked' ]
		},
		// Use a back icon for all non-home panels if they are not locked.
		{
			icon: 'arrowPrevious',
			flags: 'safe',
			action: 'home',
			modes: [ 'ask-help', 'general-help', 'questioncomplete', 'search', 'suggested-edits' ]
		}
	];

	/**
	 * Swap the state of the help panel dialog.
	 *
	 * Modeled off of VisualEditor's swapPanel().
	 *
	 * @param {string} panelToSwitchTo One of 'home', 'ask-help', 'general-help',
	 *   'questioncomplete' or 'suggested-edits'
	 * @throws {Error} Unknown panel.
	 */
	HelpPanelProcessDialog.prototype.swapPanel = function ( panelToSwitchTo ) {
		const panelObj = this[ panelToSwitchTo.replace( '-', '' ) + 'Panel' ],
			titleMsg = this.panelTitleMessages[ panelToSwitchTo ] || this.panelTitleMessages.home;

		this.title.setLabel( titleMsg );

		if ( ![
			'home',
			'suggested-edits',
			'general-help',
			'ask-help',
			'questioncomplete'
		].includes( panelToSwitchTo ) ) {
			throw new Error( 'Unknown panel: ' + panelToSwitchTo );
		}

		this.$content
			// Classes that can be used here:
			// * mw-ge-help-panel-processdialog-activepanel-home
			// * mw-ge-help-panel-processdialog-activepanel-suggested-edits
			// * mw-ge-help-panel-processdialog-activepanel-general-help
			// * mw-ge-help-panel-processdialog-activepanel-ask-help
			// * mw-ge-help-panel-processdialog-activepanel-questioncomplete
			.removeClass( 'mw-ge-help-panel-processdialog-activepanel-' + this.currentPanel )
			.addClass( 'mw-ge-help-panel-processdialog-activepanel-' + panelToSwitchTo );

		if ( panelToSwitchTo === 'home' ) {
			this.toggleSearchResults( false );
		}
		if ( panelToSwitchTo === 'general-help' ) {
			this.toggleSearchResults( false );
		}
		if ( panelToSwitchTo === 'ask-help' ) {
			this.askhelpPanel.prepareToShowPanel();
		}
		// When navigating to the home panel, don't change which panel is visible in this.panels
		// The current panel needs to remain visible while the sliding transition happens
		if ( panelToSwitchTo !== 'home' ) {
			this.panels.setItem( panelObj );
		}

		if ( this.suggestedEditSession.active ) {
			this.updateSuggestedEditSession( {
				helpPanelCurrentPanel: panelToSwitchTo
			} );
		}

		this.currentPanel = panelToSwitchTo;
		this.updateMode();

	};

	HelpPanelProcessDialog.prototype.setNotificationLabelText = function () {
		const $messageList = $( '<dl>' ).addClass( 'mw-ge-help-panel-notifications' );

		let emailMessage, button;
		if ( this.userEmail ) {
			if ( this.userEmailConfirmed ) {
				const $link = $( '<a>' )
					.attr( {
						href: mw.util.getUrl( 'Special:ChangeEmail' ),
						target: '_blank',
						'data-link-id': 'special-change-email'
					} )
					.text( mw.message(
						'growthexperiments-help-panel-questioncomplete-notifications-email-change'
					).text() );
				emailMessage = mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-email' )
					.params( [ this.userEmail, $link ] )
					.parse();
			} else {
				emailMessage = mw.message(
					'growthexperiments-help-panel-questioncomplete-notifications-email-unconfirmed'
				).params( [ this.userEmail, mw.user ] ).escaped();
				button = new OO.ui.ButtonWidget( {
					label: mw.message(
						'growthexperiments-help-panel-questioncomplete-notifications-email-unconfirmed-confirm'
					).text(),
					href: mw.util.getUrl( 'Special:ConfirmEmail' ),
					target: '_blank'
				} );
				button.$button.attr( 'data-link-id', 'special-confirm-email' );
			}
		} else {
			emailMessage = mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-email-missing' )
				.params( [ mw.user ] )
				.escaped();
			button = new OO.ui.ButtonWidget( {
				label: mw.message(
					'growthexperiments-help-panel-questioncomplete-notifications-email-missing-add'
				).text(),
				href: mw.util.getUrl( 'Special:ChangeEmail' ),
				target: '_blank'
			} );
			button.$button.attr( 'data-link-id', 'special-change-email' );
		}

		$messageList.append(
			$( '<dt>' ).append( new OO.ui.IconWidget( { icon: 'bell' } ).$element ),
			$( '<dd>' ).text(
				mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-wiki' ).text()
			),
			$( '<dt>' ).append( new OO.ui.IconWidget( { icon: 'message' } ).$element ),
			$( '<dd>' ).html( emailMessage )
		);
		if ( button ) {
			$messageList.append( $( '<dd>' ).append( button.$element ) );
		}

		this.questionCompleteNotificationsText.setLabel( new OO.ui.HtmlSnippet(
			$( '<p>' )
				.append(
					$( '<h2>' )
						.addClass( 'mw-ge-help-panel-questioncomplete-notifications-section' )
						.text( mw.message(
							'growthexperiments-help-panel-questioncomplete-notifications-section-header'
						).text() ),
					$messageList
				)
		) );
	};

	/**
	 * Return a list of links providing extra information about the help panel, as a jQuery
	 * collection of <a> tags.
	 *
	 * @return {jQuery}
	 */
	HelpPanelProcessDialog.prototype.getInfoLinks = function () {
		const links = [];

		links.push(
			new OO.ui.ButtonWidget( {
				href: 'https://www.mediawiki.org/wiki/Special:MyLanguage/Growth/Focus_on_help_desk/Help_panel',
				label: mw.message(
					'growthexperiments-help-panel-questioncomplete-more-about-this-feature-text'
				).text(),
				icon: 'infoFilled',
				data: 'more-about-this-feature'
			} )
		);
		if ( this.taskTypeId ) {
			links.push(
				new OO.ui.ButtonWidget( {
					href: 'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:Growth/Tools/Suggested_edits',
					label: mw.message( 'growthexperiments-help-panel-suggested-edits-faq-link-text' ).text(),
					icon: 'lightbulb',
					data: 'suggested-edits-faq'
				} )
			);
		}

		// The help panel is shown when gesuggestededit query parameter is present regardless of
		// whether the help panel preference is enabled. Only show the link to disable the
		// preference if it's enabled. (T284088)
		if ( mw.user.options.get( 'growthexperiments-help-panel-tog-help-panel' ) ) {
			links.push(
				new OO.ui.ButtonWidget( {
					href: new mw.Title( 'Special:Preferences#mw-prefsection-personal-homepage' ).getUrl(),
					label: mw.message(
						'growthexperiments-help-panel-settings-cog-preferences-link'
					).text(),
					icon: 'settings',
					data: 'special-preferences'
				} )
			);
		}

		return links.reduce( ( $list, button ) => {
			// This is a bit of a hack as these buttons are in no way progressive,
			// but the progressive button style matches the intended visual style well.
			button.setTarget( '_blank' ).toggleFramed( false ).setFlags( 'progressive' );
			button.$element.find( 'a' ).attr( 'data-link-id', button.getData() );
			return $list.add( button.$element );
		}, $() );
	};

	HelpPanelProcessDialog.prototype.buildHomePanelButtons = function () {
		const buttonIds = [ 'general-help' ];
		if ( this.askHelpEnabled || mw.config.get( 'wgGEHelpPanelAskMentor' ) ) {
			buttonIds.unshift( 'ask-help' );
		}
		if ( this.taskTypeId ) {
			buttonIds.unshift( 'suggested-edits' );
		}
		buttonIds.forEach( ( id ) => {
			const mentorData = mw.config.get( 'wgGEHelpPanelMentorData' );
			// Asking the mentor needs a different button but the same panel / logging.
			// FIXME find a nicer way to do this.
			let buttonId = id;
			if ( id === 'ask-help' && mw.config.get( 'wgGEHelpPanelAskMentor' ) &&
				// Do not try to use mentor data when it is not present. This is the case on the
				// homepage when the help panel is disabled. Home button widgets are not used
				// on the homepage but the build method still needs to run without error.
				mentorData && mentorData.name
			) {
				buttonId = 'ask-help-mentor';
			}
			this.homePanel.$element.append(
				new HelpPanelHomeButtonWidget( {
					id: buttonId,
					taskTypeId: this.taskTypeId,
					customSubheader: ( buttonId === 'ask-help-mentor' ) ?
						mw.message( 'growthexperiments-help-panel-button-subsubheader-ask-help-mentor' )
							.params( [
								mw.language.convertNumber( mentorData.editCount ),
								mentorData.lastActive
							] ).text() :
						null,
					subsubheader: ( buttonId === 'ask-help-mentor' ) ? mentorData.name : null
				} ).$element
					.on( 'click', () => {
						this.logger.log( id );
						this.swapPanel( id );
					} )
			);
		} );
	};

	/** @inheritDoc **/
	HelpPanelProcessDialog.prototype.initialize = function () {
		HelpPanelProcessDialog.super.prototype.initialize.call( this );

		this.$content
			.addClass( [
				'mw-ge-help-panel-processdialog',
				OO.ui.isMobile() ?
					'mw-ge-help-panel-processdialog-mobile' :
					'mw-ge-help-panel-processdialog-desktop'
			] );

		this.panels = new OO.ui.StackLayout( {
			classes: [ 'mw-ge-help-panel-processdialog-subpanels' ]
		} );
		this.homePanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false,
			scrollable: true,
			classes: [ 'mw-ge-help-panel-processdialog-homepanel' ]
		} );

		this.userEmail = mw.config.get( 'wgGEHelpPanelUserEmail' );
		this.userEmailConfirmed = mw.config.get( 'wgGEHelpPanelUserEmailConfirmed' );

		this.searchWidget = new HelpPanelSearchWidget( this.logger, {
			searchNamespaces: configData.GEHelpPanelSearchNamespaces,
			foreignApi: configData.GEHelpPanelSearchForeignAPI
		} ).connect( this, {
			enterSearch: [ 'executeAction', 'entersearch' ],
			leaveSearch: [ 'executeAction', 'leavesearch' ]
		} );

		this.suggestededitsPanel = new SuggestedEditsPanel( {
			// Unlike the other panels, we have no padding on this one
			// because of the design that has the navigation and header
			// content of the panel with a solid constant background color.
			taskTypeData: TASK_TYPES[ this.taskTypeId ],
			editorInterface: this.logger.getEditor(),
			currentTip: this.suggestedEditSession.helpPanelCurrentTip,
			parentWindow: this,
			preferredEditor: configData.GEHelpPanelSuggestedEditsPreferredEditor[
				this.suggestedEditSession.taskType
			]
		} );
		const guidanceTipsPromise = this.suggestededitsPanel.build();

		this.askhelpPanel = new AskHelpPanel( {
			askSource: this.askSource,
			logger: this.logger,
			relevantTitle: mw.Title.newFromText( mw.config.get( 'wgRelevantPageName' ) )
		} );
		this.askhelpPanel.on( 'askHelpTextInputChange', this.onAskHelpTextInputChange.bind( this ) );
		this.panelTitleMessages = Object.assign(
			this.panelTitleMessages,
			this.askhelpPanel.getPanelTitleMessages()
		);

		this.generalhelpPanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );

		this.questioncompletePanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: true
		} );

		// Build the content of General Help panel.
		this.generalhelpSearchFieldContent = new OO.ui.FieldLayout(
			this.searchWidget, {
				align: 'top',
				label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-search-label' ).text() ),
				classes: [ 'mw-ge-help-panel-popup-search' ]
			}
		);

		// Add editing links after search.
		this.$generalhelpPanelEditingLinksHeader = $( '<h2>' )
			.append( $( '<strong>' )
				.text( mw.message( 'growthexperiments-help-panel-editing-help-links-widget-header' ).text() ) );

		this.$generalhelpPanelEditingLinks = $( linksConfig.helpPanelLinks );
		this.$generalhelpPanelEditingLinksViewMore = $( '<p>' )
			.append( $( '<strong>' ).html( linksConfig.viewMoreLink ) );
		this.generalhelpPanel.$element.append(
			this.generalhelpSearchFieldContent.$element,
			this.$generalhelpPanelEditingLinksHeader,
			this.$generalhelpPanelEditingLinks,
			this.$generalhelpPanelEditingLinksViewMore
		);

		// Build the content of Home panel.
		this.buildHomePanelButtons();
		this.getInfoLinks().appendTo( this.homePanel.$element )
			.wrapAll( $( '<ul>' ).addClass( 'mw-ge-help-panel-info-links' ) )
			.wrap( '<li>' );

		// Build the content of Question Complete panel.
		this.questionCompleteContent = new OO.ui.FieldsetLayout( {
			label: new OO.ui.HorizontalLayout( {
				items: [
					new OO.ui.IconWidget( { icon: 'check', flags: [ 'progressive' ] } ),
					new OO.ui.LabelWidget( {
						label: mw.message( 'growthexperiments-help-panel-questioncomplete-header' ).text()
					} )
				],
				classes: [ 'mw-ge-help-panel-question-complete' ]
			} ).$element
		} );

		this.questionCompleteConfirmationLabel = new OO.ui.LabelWidget( {
			label: $( '<p>' ).text( this.askhelpPanel.getQuestionCompleteConfirmationLabel() )
		} );
		this.questionCompleteFirstEditText = new OO.ui.LabelWidget( {
			label: $( '<p>' )
				.addClass( 'mw-ge-help-panel-questioncomplete-first-edit' )
				.text( mw.message( 'growthexperiments-help-panel-questioncomplete-first-edit' ).text() )
		} );
		this.questionCompleteViewQuestionText = new OO.ui.LabelWidget();
		this.questionCompleteNotificationsText = new OO.ui.LabelWidget();
		this.questionCompleteContent.addItems( [
			this.questionCompleteConfirmationLabel,
			this.questionCompleteViewQuestionText,
			this.questionCompleteNotificationsText,
			this.questionCompleteFirstEditText

		] );
		this.questioncompletePanel.$element.append( this.questionCompleteContent.$element );

		this.panels.addItems( [
			this.suggestededitsPanel,
			this.askhelpPanel,
			this.generalhelpPanel,
			this.questioncompletePanel
		] );

		// The home panel is at the top level, outside of the StackLayout containing the other
		// panels, which is positioned next to it outside the dialog. When navigating, both slide
		// left or right
		this.$body.append( this.homePanel.$element, this.panels.$element );
		this.$element.on( 'click', 'a[data-link-id]', this.logLinkClick.bind( this ) );

		guidanceTipsPromise.then( ( helpPanelHasTips ) => {
			if ( !helpPanelHasTips ) {
				return;
			}
			// IndexLayout does not provide any way to differentiate between human and programmatic
			// tab selection so we must go deeper.
			this.suggestededitsPanel.tipsPanel.tabIndexLayout.getTabs().on( 'choose', ( item ) => {
				const tabName = item.data;
				this.updateSuggestedEditSession( {
					helpPanelCurrentTip: tabName,
					helpPanelSuggestedEditsInteractionHappened: true
				} );
				this.logger.log( 'guidance-tab-click', {
					taskType: this.taskTypeId,
					tabName: tabName
				} );
			} );
		} );

		// Disable pending effect in the header; it breaks the background transition when navigating
		// back from the suggested-edits panel to the home panel. In getActionProcess(), we set the
		// pending element back to the default where needed.
		this.$backupPendingElement = this.$pending;
		this.setPendingElement( $( [] ) );

		/**
		 * Get the panel to switch to based on examining the active session.
		 *
		 * @param {SuggestedEditSession} suggestedEditSession
		 * @param {boolean} isEditing
		 * @return {string}
		 */
		function getPanelFromSession( suggestedEditSession, isEditing ) {
			if ( !suggestedEditSession.active ) {
				return 'home';
			}

			// If the user is editing, they are on the guidance screen, and they
			// have not interacted with guidance, switch them over to the home panel.
			if ( isEditing && !suggestedEditSession.helpPanelSuggestedEditsInteractionHappened ) {
				return 'home';
			}
			return suggestedEditSession.helpPanelCurrentPanel === null ?
				'suggested-edits' :
				suggestedEditSession.helpPanelCurrentPanel;
		}

		this.swapPanel(
			this.getDefaultPanelForSuggestedEditSession() ||
			getPanelFromSession( this.suggestedEditSession, this.logger.isEditing() ) );

		if ( this.shouldAutoAdvanceUponInit() ) {
			this.setGuidanceAutoAdvance( true );
		}
	};

	/**
	 * Check whether the guidance panel should auto-advance upon initialization
	 *
	 * @return {boolean}
	 */
	HelpPanelProcessDialog.prototype.shouldAutoAdvanceUponInit = function () {
		// Only auto advance if the help panel is open and the user hasn't interacted with it
		let helpPanelShouldOpen = this.suggestedEditSession.helpPanelShouldOpen;
		if ( OO.ui.isMobile() ) {
			// If mobile peek needs to be shown, the help panel shouldn't open.
			helpPanelShouldOpen = helpPanelShouldOpen && this.suggestedEditSession.mobilePeekShown;
		}
		return helpPanelShouldOpen &&
			!this.suggestedEditSession.helpPanelSuggestedEditsInteractionHappened;
	};

	/**
	 * @param {Object} event
	 */
	HelpPanelProcessDialog.prototype.logLinkClick = function ( event ) {
		const linkId = $( event.currentTarget ).data( 'link-id' );
		if ( linkId ) {
			this.logger.log( 'link-click', linkId );
		}
	};

	/** @inheritDoc **/
	HelpPanelProcessDialog.prototype.getSetupProcess = function ( data ) {
		return HelpPanelProcessDialog.super.prototype.getSetupProcess
			.call( this, data )
			.next( function () {
				if ( OO.ui.isMobile() ) {
					this.updateEditMode();
				}
				this.swapPanel( data.panel || 'home' );
			}, this );
	};

	/** @inheritDoc **/
	HelpPanelProcessDialog.prototype.getTeardownProcess = function ( data ) {
		return HelpPanelProcessDialog.super.prototype.getTeardownProcess
			.call( this, data )
			.next( function () {
				if ( this.suggestedEditSession.active ) {
					this.updateSuggestedEditSession( {
						helpPanelShouldOpen: false
					} );
				}
			}, this )
			// Wait 400ms before hiding the dialog, to allow the animation to complete
			// This value should be kept in sync with @help-panel-transition-duration in
			// variables.less
			.next( 400 );
	};

	/**
	 * Set the process dialog mode based on the current panel, mode and the
	 * status of the suggested edit session.
	 */
	HelpPanelProcessDialog.prototype.updateMode = function () {
		this.setMode(
			this.suggestedEditSession.helpPanelShouldBeLocked ?
				this.currentPanel + '-locked' :
				this.currentPanel
		);
	};

	/**
	 * Update the suggested edit session.
	 *
	 * @param {Object} update The updates to save to the session.
	 */
	HelpPanelProcessDialog.prototype.updateSuggestedEditSession = function ( update ) {
		if ( this.suggestedEditSession.active ) {
			this.suggestedEditSession = Object.assign( this.suggestedEditSession, update );
			this.suggestedEditSession.save();
		}
	};

	/**
	 * Respond to 'save' events from the SuggestedEditSession.
	 *
	 * @param {Object} session The values saved to the suggested edit session storage
	 */
	HelpPanelProcessDialog.prototype.onSuggestedEditSessionSave = function ( session ) {
		if ( session.helpPanelSuggestedEditsInteractionHappened ) {
			this.setGuidanceAutoAdvance( false );
		}
	};

	/**
	 * Set "edit mode" which removes the footer from the suggested edits panel
	 * and potentially swaps the panel to home, depending on whether the user
	 * has interacted with guidance.
	 */
	HelpPanelProcessDialog.prototype.updateEditMode = function () {
		this.suggestededitsPanel.toggleFooter( this.logger.isEditing() );
		this.updateMode();
		this.suggestededitsPanel.toggleSwitchEditorPanel(
			this.logger.isEditing(),
			this.logger.getEditor()
		);

		// If the user is editing, they are on the guidance screen, and they
		// have not interacted with guidance, switch them over to the home panel.
		if ( this.logger.isEditing() &&
			!this.suggestedEditSession.helpPanelSuggestedEditsInteractionHappened &&
			!this.getDefaultPanelForSuggestedEditSession() ) {
			// But now that they have seen the root screen, let's pretend
			// an interaction happened so that the user doesn't get swapped
			// over without asking again.
			this.updateSuggestedEditSession( {
				helpPanelSuggestedEditsInteractionHappened: true
			} );
			this.swapPanel( 'home' );
		}
	};

	/**
	 * Show/hide search results interface.
	 *
	 * The search results interface does not exist as a separate panel (unlike home, question
	 * review, and question complete panels). Instead, we show or hide the search results
	 * depending on whether a search is active. When a search is active, we need to also hide
	 * the other home panel elements (links, header, view more, footer).
	 *
	 * @param {boolean} toggle
	 */
	HelpPanelProcessDialog.prototype.toggleSearchResults = function ( toggle ) {
		// Hide/show editing links section and footer if search is active.
		this.$generalhelpPanelEditingLinks.toggle( !toggle );
		this.$generalhelpPanelEditingLinksHeader.toggle( !toggle );
		this.$generalhelpPanelEditingLinksViewMore.toggle( !toggle );
		// Show/hide search results.
		this.searchWidget.toggleSearchResults( toggle );

	};

	/** @inheritDoc **/
	HelpPanelProcessDialog.prototype.getActionProcess = function ( action ) {
		return HelpPanelProcessDialog.super.prototype.getActionProcess.call( this, action )
			.next( () => {
				if ( action === 'close' || !action ) {
					this.logger.log( 'close' );
					if ( this.askhelpPanel.shouldShowHomepageMentorTour() ) {
						this.launchIntroTour(
							'homepage_mentor',
							'growthexperiments-tour-homepage-mentorship'
						);
					}
					this.close();
				}
				if ( action === 'home' ) {
					// We count "back" as an interaction on the suggested edits
					// screen.
					if ( this.currentPanel === 'suggested-edits' ) {
						this.updateSuggestedEditSession( {
							helpPanelSuggestedEditsInteractionHappened: true
						} );
					}
					if ( this.currentMode === 'search' ) {
						action = 'general-help';
					}
					// One of: back-home, back-general-help
					this.logger.log( 'back-' + action, { from: this.currentPanel } );
					this.swapPanel( action );
				}
				if ( action === 'ask-help' ) {
					this.logger.log( 'ask-help' );
					this.swapPanel( action );
				}
				if ( action === 'entersearch' ) {
					this.toggleSearchResults( true );
					this.setMode( 'search' );
				}
				if ( action === 'leavesearch' ) {
					this.logger.log( 'back-home', { from: 'blank-search-input' } );
					this.swapPanel( 'general-help' );
				}
				if ( action === 'questioncomplete' ) {
					// A promise is returned to show the ProcessDialog's loading state.
					return this.publishQuestionDeferred();
				}
			} );
	};

	/**
	 * Launches a tour if the tour has never been shown before and marks the tour as viewed
	 *
	 * @param {string} tourName
	 * @param {string} tourPreferenceKey
	 */
	HelpPanelProcessDialog.prototype.launchIntroTour = function ( tourName, tourPreferenceKey ) {
		/**
		 * The following tours are launched using this method:
		 * - ext.guidedTour.tour.homepage_mentor
		 * - ext.guidedTour.tour.helppanel
		 */
		if ( !mw.user.options.get( tourPreferenceKey ) ) {
			mw.loader.using( 'ext.guidedTour.tour.' + tourName, () => {
				mw.guidedTour.launchTour( tourName );
				mw.user.options.set( tourPreferenceKey, '1' );
				new mw.Api().saveOption(
					tourPreferenceKey,
					'1'
				);
			} );
		}
	};

	/**
	 * Set the mode both in local cache and in this.actions because the latter doesn't
	 * have a getter and the current mode is needed in getBodyHeight below.
	 *
	 * @param {string} mode
	 */
	HelpPanelProcessDialog.prototype.setMode = function ( mode ) {
		this.currentMode = mode;
		this.actions.setMode( mode );
	};

	/**
	 * Get a stable body height as panels are switched.
	 *
	 * The idea is that the height should remain uniform across panels. This is important
	 * for the animation effects.
	 *
	 * @override
	 * @return {number}
	 */
	HelpPanelProcessDialog.prototype.getBodyHeight = function () {
		if ( !this.homeHeight ) {
			const homePanelHeight = this.homePanel.$element.outerHeight( true );
			// HACK: If the dialog is used in the context of QuestionPoster, the home panel is never
			// shown but its height is still used to determine the dialog height since the subpanels
			// are absolutely positioned and thus don't have a natural height. Ideally the dialog
			// height would be that of the askHelpPanel or the questioncompletePanel (whichever is
			// higher), but these PanelLayouts are absolutely positioned.
			if ( this.isQuestionPoster ) {
				this.homeHeight = Math.max( homePanelHeight, MIN_DIALOG_HEIGHT );
			} else {
				this.homeHeight = homePanelHeight;
			}
		}

		return this.homeHeight;
	};

	/**
	 * @inheritDoc
	 * @return {Object} Size properties ([min|max|][width|height]).
	 */
	HelpPanelProcessDialog.prototype.getSizeProperties = function () {
		let dim = HelpPanelProcessDialog.super.prototype.getSizeProperties.call( this );
		// Override default size calculation of OO.ui.Window, so that the help panel's size
		// doesn't change depending on the number of buttons / sub-panels in it, and it doesn't
		// overlap the personal toolbar (which has a higher z-index, and that would be difficult
		// to change without messing up the stacking order relationship with other things).
		// But only on desktop, since on mobile we usually want to fill the screen.
		if ( !OO.ui.isMobile() && this.useHelpPanelLayout ) {
			// Do not change the object, it is shared by all OOUI windows
			dim = Object.assign( {}, dim, {
				width: '360px',
				height: '528px',
				maxHeight: 'calc( 100vh - 180px )'
			} );
		}
		return dim;
	};

	/**
	 * Enable / disable auto-advancing the guidance tabs.
	 *
	 * @param {boolean} enable
	 */
	HelpPanelProcessDialog.prototype.setGuidanceAutoAdvance = function ( enable ) {
		const self = this;
		if ( enable && !this.guidanceAutoAdvanceTimer ) {
			this.guidanceAutoAdvanceTimer = window.setInterval( () => {
				// Skip if the panel is not active or not loaded yet.
				if ( self.currentPanel !== 'suggested-edits' || !self.suggestededitsPanel.tipsPanel ) {
					return;
				}
				// This seems to be the least insane method of finding the next tab :/
				const tabIndexLayout = self.suggestededitsPanel.tipsPanel.tabIndexLayout;
				const tabs = tabIndexLayout.getTabs();
				const currentTab = tabs.findItemFromData( tabIndexLayout.getCurrentTabPanelName() );
				const nextTab = tabs.getItems()[
					( tabs.getItemIndex( currentTab ) + 1 ) % tabs.getItemCount()
				];
				if ( nextTab ) {
					tabIndexLayout.setTabPanel( nextTab.data );
				}
			}, 5000 );
		} else if ( !enable && this.guidanceAutoAdvanceTimer ) {
			window.clearInterval( this.guidanceAutoAdvanceTimer );
		}
	};

	/**
	 * Return the default panel (if any) that should be shown for the
	 * suggested edit session based on the task type
	 *
	 * @return {string|undefined}
	 */
	HelpPanelProcessDialog.prototype.getDefaultPanelForSuggestedEditSession = function () {
		function shouldDefaultToSuggestedEdits( suggestedEditSession ) {
			const targetTaskTypes = [ 'link-recommendation' ];
			return targetTaskTypes.includes( suggestedEditSession.taskType );
		}
		if ( shouldDefaultToSuggestedEdits( this.suggestedEditSession ) ) {
			return 'suggested-edits';
		}
	};

	/**
	 * Enable questioncomplete action in the dialog if the user enters a question
	 *
	 * @param {string} question Question the user entered in the Ask Help panel
	 */
	HelpPanelProcessDialog.prototype.onAskHelpTextInputChange = function ( question ) {
		// Enable the "Publish" button on the dialog if there's a question to publish.
		this.getActions().setAbilities( { questioncomplete: question } );
	};

	/**
	 * Publish the question the user entered via Ask Help panel
	 *
	 * @return {jQuery.Promise}
	 */
	HelpPanelProcessDialog.prototype.publishQuestionDeferred = function () {
		// HACK: by default, the pending element is the head, but that results in brief
		// flashes of pending state when switching panels or closing the dialog, which
		// we don't want. Instead, make the head the pending element only while
		// submitting a question.
		this.setPendingElement( this.$backupPendingElement );

		const shouldShowHelpPanelTour = this.askhelpPanel.shouldShowHelpPanelTour(),
			submitAttemptData = {
				/* eslint-disable camelcase */
				question_length: this.askhelpPanel.getQuestion().length,
				include_title: this.askhelpPanel.isTitleIncludedInQuestion(),
				had_email: !!this.userEmail,
				had_email_confirmed: !!this.userEmailConfirmed
				/* eslint-enable camelcase */
			};

		this.logger.log( 'submit-attempt', submitAttemptData );

		// Hide the first edit text, which is shown depending on API response.
		this.questionCompleteFirstEditText.toggle( false );
		// Start pre-loading tour for help panel.
		if ( shouldShowHelpPanelTour && mw.config.get( 'skin' ) !== 'minerva' ) {
			mw.loader.load( 'ext.guidedTour.tour.helppanel' );
		}
		return new mw.Api().postWithToken( 'csrf', this.askhelpPanel.getPostData() )
			.then( ( data ) => {
				this.logger.incrementUserEditCount();
				this.logger.log( 'submit-success', Object.assign(
					submitAttemptData,
					/* eslint-disable camelcase */
					{
						revision_id: data.helppanelquestionposter.revision
					}
					/* eslint-enable camelcase */
				) );

				if ( data.helppanelquestionposter.isfirstedit ) {
					this.questionCompleteFirstEditText.toggle( true );
				}
				this.questionCompleteViewQuestionText.setLabel(
					$( '<p>' ).append(
						$( '<strong>' ).append(
							$( '<a>' ).attr( {
								href: data.helppanelquestionposter.viewquestionurl,
								target: '_blank',
								'data-link-id': 'view-question'
							} ).text( this.askhelpPanel.getViewQuestionText() )
						)
					)
				);
				this.setNotificationLabelText();
				this.swapPanel( 'questioncomplete' );

				if ( shouldShowHelpPanelTour ) {
					this.launchIntroTour(
						'helppanel',
						'growthexperiments-tour-help-panel'
					);
				}

				mw.hook( 'growthExperiments.helpPanelQuestionPosted' ).fire( data );
				this.askhelpPanel.onQuestionPosted();
			}, ( errorCode, errorData ) => {
				// Return a recoverable error. The user can either try again, or they
				// can follow the instructions in the error message for how to post
				// their message manually.
				// Re-enable the submit button once the user is done with modal.
				submitAttemptData.error = errorCode;
				this.logger.log( 'submit-failure', submitAttemptData );
				return $.Deferred().reject(
					new OO.ui.Error( $( '<p>' ).append(
						errorCode === 'hookaborted' ?
							this.askhelpPanel.getSubmitFailureMessage() :
							errorData.error.info
					) )
				).promise();
			} )
			.always( () => {
				this.setPendingElement( $( [] ) );
			} );
	};

	module.exports = HelpPanelProcessDialog;

}() );
