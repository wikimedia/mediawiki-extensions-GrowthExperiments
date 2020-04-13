( function () {

	/**
	 * @class mw.libs.ge.HelpPanelProcessDialog
	 * @extends OO.ui.ProcessDialog
	 *
	 * @param {Object} config
	 * @cfg {mw.libs.ge.HelpPanelLogger} logger
	 * @cfg {bool} [showCogMenu=true] Whether the cog menu show be shown
	 * @cfg {string} source Logical name of the source to use with the HelpPanelPostQuestion API
	 * @cfg {string} storageKey Name of the key to use to persist question draft in storage
	 * @cfg {object} panelTitleMessages Object like { panelName: title } to configure panel titles
	 * @cfg {string} askhelpHeader Header message on the question review panel
	 * @cfg {string} questionCompleteConfirmationText Confirmation text for question complete panel
	 * @cfg {string} viewQuestionText Text of the link to view the question that was just posted
	 * @cfg {string} submitFailureMessage Text of the error message when failing to post a question
	 * @cfg {bool} guidanceEnabled Whether guidance feature is enabled.
	 * @cfg {string} taskTypeId The ID of the suggested edit task type.
	 * @constructor
	 */
	var configData = require( './data.json' ),
		linksConfig = configData.GEHelpPanelLinks,
		HelpPanelProcessDialog = function helpPanelProcessDialog( config ) {
			HelpPanelProcessDialog.super.call( this, config );
			this.logger = config.logger;
			this.guidanceEnabled = config.guidanceEnabled;
			this.taskTypeId = config.taskTypeId;
			this.showCogMenu = config.showCogMenu !== undefined ? config.showCogMenu : true;
			this.source = config.source || 'helppanel';
			this.storageKey = config.storageKey || 'help-panel-question-text';
			this.panelTitleMessages = $.extend( {
				home: mw.message( 'growthexperiments-help-panel-home-title' ).text(),
				'ask-help': mw.message( 'growthexperiments-help-panel-questionreview-title' ).text(),
				questioncomplete: mw.message( 'growthexperiments-help-panel-questioncomplete-title' ).text()
			}, config.panelTitleMessages );
			this.askhelpHeader = config.askhelpHeader ||
				mw.message(
					'growthexperiments-help-panel-questionreview-header',
					$( linksConfig.helpDeskLink ),
					mw.user.getName()
				).parse();
			this.questionCompleteConfirmationText = config.questionCompleteConfirmationText ||
				mw.message(
					'growthexperiments-help-panel-questioncomplete-confirmation-text'
				).text();
			this.viewQuestionText = config.viewQuestionText ||
				mw.message(
					'growthexperiments-help-panel-questioncomplete-view-link-text'
				).text();
			this.submitFailureMessage = config.submitFailureMessage || mw.message(
				'growthexperiments-help-panel-question-post-error', linksConfig.helpDeskLink
			).parse();
		},
		HelpPanelSearchWidget = require( './ext.growthExperiments.HelpPanelSearchWidget.js' ),
		HelpPanelHomeButtonWidget = require( './ext.growthExperiments.HelpPanelHomeButtonWidget.js' );

	OO.inheritClass( HelpPanelProcessDialog, OO.ui.ProcessDialog );

	HelpPanelProcessDialog.static.name = 'HelpPanel';
	HelpPanelProcessDialog.static.actions = [
		// On desktop, allow user to close the panel at any stage except
		// questioncomplete.
		{
			icon: 'close',
			flags: 'safe',
			action: 'close',
			modes: OO.ui.isMobile() ? [ 'home' ] : [ 'home', 'ask-help', 'general-help', 'search' ]
		},
		// On mobile, use a back icon for all non-home panels.
		{
			icon: 'previous',
			flags: 'safe',
			action: 'home',
			modes: OO.ui.isMobile() ? [ 'ask-help', 'general-help', 'search' ] : []
		},
		// Add a button to the bottom of the panel that replaces the close button in the
		// questioncomplete stage (T225669)
		{
			label: mw.message( 'growthexperiments-help-panel-close' ).text(),
			modes: [ 'questioncomplete' ],
			flags: 'primary',
			framed: false,
			classes: [ 'mw-ge-help-panel-done' ],
			action: 'close'
		},
		{
			label: mw.message( 'growthexperiments-help-panel-back-home' ).text(),
			modes: [ 'search', 'general-help' ],
			action: 'home'
		}
	];

	HelpPanelProcessDialog.prototype.attachActions = function () {
		HelpPanelProcessDialog.super.prototype.attachActions.call( this );
		if ( this.showCogMenu ) {
			// Hack to place the settings cog as a "primary" (top-right) action.
			this.$primaryActions.append( this.settingsCog.$element );
		}
	};

	/**
	 * Swap the state of the help panel dialog.
	 *
	 * Modeled off of VisualEditor's swapPanel().
	 *
	 * @param {string} panel One of 'home', 'ask-help', 'general-help' or 'questioncomplete'
	 * @throws {Error} Unknown panel.
	 */
	HelpPanelProcessDialog.prototype.swapPanel = function ( panel ) {
		var panelObj = this[ panel.replace( '-', '' ) + 'Panel' ],
			titleMsg = this.panelTitleMessages[ panel ] || this.panelTitleMessages.home;

		this.title.setLabel( titleMsg );

		if ( ( [ 'home', 'general-help', 'ask-help', 'questioncomplete' ].indexOf( panel ) ) === -1 ) {
			throw new Error( 'Unknown panel: ' + panel );
		}
		if ( panel === 'home' ) {
			this.askhelpFooterPanel.toggle( false );
			this.toggleSearchResults( false );
			this.settingsCog.toggle( true );
		}
		if ( panel === 'general-help' ) {
			this.askhelpFooterPanel.toggle( false );
			this.toggleSearchResults( false );
			this.settingsCog.toggle( true );
		}
		if ( panel === 'ask-help' ) {
			this.askhelpFooterPanel.toggle( true );
			this.settingsCog.toggle( true );
		}
		if ( panel === 'questioncomplete' ) {
			// Use the action defined in static.actions rather than a custom implementation
			// for this panel.
			this.askhelpFooterPanel.toggle( false );
			// Hide the cog, it interferes with the primary 'close' action
			this.settingsCog.toggle( false );
		}
		this.panels.setItem( panelObj );
		this.setMode( panel );
	};

	/**
	 * Connected to the askhelpTextInput field.
	 */
	HelpPanelProcessDialog.prototype.onTextInputChange = function () {
		var reviewTextInputValue = this.askhelpTextInput.getValue();
		// Enable the "Submit" button on the review step if there's text input.
		this.askhelpSubmitButton.setDisabled( !reviewTextInputValue );
		// Copy the review text input back to the initial question field, in case the
		// user clicks "back".
		this.questionTextInput.setValue( reviewTextInputValue );
		// Save the draft text in local storage in case the user reloads their page.
		mw.storage.set( this.storageKey, reviewTextInputValue );
	};

	/**
	 * Set the value of the text area on the review step.
	 */
	HelpPanelProcessDialog.prototype.populateReviewText = function () {
		this.askhelpTextInput.setValue( this.questionTextInput.getValue() );

		if ( !this.previousQuestionText && this.questionTextInput.getValue() ) {
			this.logger.log( 'enter-question-text' );
		}
		this.previousQuestionText = this.questionTextInput.getValue();
	};

	HelpPanelProcessDialog.prototype.setNotificationLabelText = function () {
		var $messageList,
			$link,
			emailMessage,
			button;

		$messageList = $( '<dl>' ).addClass( 'mw-ge-help-panel-notifications' );

		if ( this.userEmail ) {
			if ( this.userEmailConfirmed ) {
				$link = $( '<a>' )
					.attr( {
						href: mw.util.getUrl( 'Special:ChangeEmail' ),
						target: '_blank',
						'data-link-id': 'special-change-email'
					} )
					.text( mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-email-change' ).text() );
				emailMessage = mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-email' )
					.params( [ this.userEmail, $link ] )
					.parse();
			} else {
				emailMessage = mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-email-unconfirmed' )
					.params( [ this.userEmail, mw.user ] )
					.escaped();
				button = new OO.ui.ButtonWidget( {
					label: mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-email-unconfirmed-confirm' ).text(),
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
				label: mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-email-missing-add' ).text(),
				href: mw.util.getUrl( 'Special:ChangeEmail' ),
				target: '_blank'
			} );
			button.$button.attr( 'data-link-id', 'special-change-email' );
		}

		$messageList.append( $( '<dt>' ).append( new OO.ui.IconWidget( { icon: 'bell' } ).$element ) );
		$messageList.append( $( '<dd>' ).text( mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-wiki' ).text() ) );
		$messageList.append( $( '<dt>' ).append( new OO.ui.IconWidget( { icon: 'message' } ).$element ) );
		$messageList.append( $( '<dd>' ).html( emailMessage ) );
		if ( button ) {
			$messageList.append( $( '<dd>' ).append( button.$element ) );
		}

		this.questionCompleteNotificationsText.setLabel( new OO.ui.HtmlSnippet(
			$( '<p>' )
				.append(
					$( '<h2>' )
						.addClass( 'mw-ge-help-panel-questioncomplete-notifications-section' )
						.text( mw.message( 'growthexperiments-help-panel-questioncomplete-notifications-section-header' ).text() ),
					$messageList
				)
		) );
	};

	HelpPanelProcessDialog.prototype.buildSettingsCog = function () {
		this.settingsCog = new OO.ui.PopupButtonWidget( {
			$overlay: this.$element,
			icon: 'ellipsis',
			// Hack for styling
			classes: [ 'mw-ge-help-panel-settings-cog', 'oo-ui-actionWidget' ],
			popup: {
				$content: $( '<p>' ).append(
					mw.html.element( 'a', {
						href: new mw.Title( 'Special:Preferences#mw-prefsection-editing' ).getUrl(),
						target: '_blank',
						'data-link-id': 'special-preferences'
					}, mw.message( 'growthexperiments-help-panel-settings-cog-preferences-link' ).text() ),
					mw.html.element( 'a', {
						href: 'https://www.mediawiki.org/wiki/Special:MyLanguage/Growth/Focus_on_help_desk/Help_panel',
						target: '_blank',
						'data-link-id': 'more-about-this-feature'
					}, mw.message( 'growthexperiments-help-panel-questioncomplete-more-about-this-feature-text' ).text() )
				),
				padded: true,
				width: 260,
				classes: [ 'mw-ge-help-panel-settings-cog-content' ],
				anchor: false,
				align: 'backwards',
				$container: this.$element
			}
		} );
		this.settingsCog.popup.connect( this, { toggle: 'onCogMenuToggle' } );
	};

	HelpPanelProcessDialog.prototype.onCogMenuToggle = function ( show ) {
		this.logger.log( show ? 'cog-open' : 'cog-close' );
		this.settingsCog.$element.toggleClass( 'active', show );
	};

	HelpPanelProcessDialog.prototype.buildHomePanelButtons = function () {
		var buttonIds = [ 'ask-help', 'general-help' ];
		if ( this.guidanceEnabled && this.taskTypeId ) {
			buttonIds.unshift( 'suggested-edits' );
		}
		buttonIds.forEach( function ( id ) {
			this.homePanel.$element.append(
				new HelpPanelHomeButtonWidget( {
					id: id,
					taskTypeId: this.taskTypeId
				} ).$element
					.data( 'link-id', id )
					.on( 'click', function ( event ) {
						this.logLinkClick( event );
						this.swapPanel( id );
					}.bind( this ) )
			);
		}.bind( this ) );
	};

	HelpPanelProcessDialog.prototype.initialize = function () {
		HelpPanelProcessDialog.super.prototype.initialize.call( this );

		this.buildSettingsCog();
		this.$content
			.addClass( 'mw-ge-help-panel-processdialog' );

		this.panels = new OO.ui.StackLayout();
		this.homePanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );

		this.userEmail = mw.config.get( 'wgGEHelpPanelUserEmail' );
		this.userEmailConfirmed = mw.config.get( 'wgGEHelpPanelUserEmailConfirmed' );

		/**
		 * @type {mw.Title}
		 */
		this.relevantTitle = mw.Title.newFromText( mw.config.get( 'wgRelevantPageName' ) );

		this.searchWidget = new HelpPanelSearchWidget( this.logger, {
			searchNamespaces: configData.GEHelpPanelSearchNamespaces,
			foreignApi: configData.GEHelpPanelSearchForeignAPI
		} ).connect( this, {
			enterSearch: [ 'executeAction', 'entersearch' ],
			leaveSearch: [ 'executeAction', 'leavesearch' ]
		} );

		this.askhelpPanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );

		this.generalhelpPanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );

		this.questioncompletePanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );

		// Fields
		this.previousQuestionText = mw.storage.get( this.storageKey );

		this.questionTextInput = new OO.ui.MultilineTextInputWidget( {
			placeholder: mw.message( 'growthexperiments-help-panel-question-placeholder' )
				.params( [ mw.user ] )
				.text(),
			multiline: true,
			maxLength: 2000,
			rows: 3,
			maxRows: 3,
			autosize: true,
			value: mw.storage.get( this.storageKey ),
			spellcheck: true,
			required: true,
			autofocus: false
		} ).connect( this, { change: 'populateReviewText' } );

		this.askhelpTextInput = new OO.ui.MultilineTextInputWidget( {
			placeholder: mw.message( 'growthexperiments-help-panel-question-placeholder' )
				.params( [ mw.user ] )
				.text(),
			multiline: true,
			maxLength: 2000,
			rows: 3,
			maxRows: 3,
			autosize: true,
			value: mw.storage.get( this.storageKey ),
			spellcheck: true,
			required: true,
			autofocus: !OO.ui.isMobile()
		} ).connect( this, { change: 'onTextInputChange' } );

		this.questionIncludeTitleCheckbox = new OO.ui.CheckboxInputWidget( {
			value: 1,
			selected: true
		} );

		// Build home content of help panel.
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

		this.buildHomePanelButtons();

		// Build step two of ask question process.
		this.askhelpContent = new OO.ui.FieldsetLayout();

		this.askhelpContent.addItems( [
			new OO.ui.LabelWidget( {
				label: $( '<p>' ).append( this.askhelpHeader )
			} )
		] );

		this.questionIncludeFieldLayout = new OO.ui.FieldLayout(
			this.questionIncludeTitleCheckbox, {
				label: mw.message( 'growthexperiments-help-panel-questionreview-include-article-title' ).text(),
				align: 'inline',
				helpInline: true,
				help: this.relevantTitle.getPrefixedText()
			}
		);

		this.askhelpContent.addItems( [
			new OO.ui.FieldLayout(
				this.askhelpTextInput, {
					label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-questionreview-label' ).text() ),
					align: 'top'
				} ),
			this.questionIncludeFieldLayout
		] );
		this.askhelpPanel.$element.append( this.askhelpContent.$element );

		this.askhelpFooterPanel = new OO.ui.PanelLayout( {
			expanded: false,
			padded: true
		} );
		this.askhelpFooterFieldset = new OO.ui.FieldsetLayout();
		this.askhelpSubmitButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'growthexperiments-help-panel-submit-question-button-text' ).text(),
			// Inherit classes from primary action, to position the button on the right.
			classes: [ 'oo-ui-processDialog-actions-primary' ],
			flags: [ 'primary', 'progressive' ]
		} ).connect( this, { click: [ 'executeAction', 'questioncomplete' ] } )
			.setDisabled( true );

		this.askhelpFooterFieldset.addItems( [
			new OO.ui.ButtonWidget( {
				label: mw.message( 'growthexperiments-help-panel-back-home' ).text()
			} ).connect( this, { click: [ 'executeAction', 'home' ] } ),
			this.askhelpSubmitButton
		] );
		this.askhelpFooterPanel.$element.append(
			this.askhelpFooterFieldset.$element
		);

		this.questionCompleteContent = new OO.ui.FieldsetLayout( {
			label: new OO.ui.HorizontalLayout( {
				items: [
					new OO.ui.IconWidget( { icon: 'check', flags: [ 'progressive' ] } ),
					new OO.ui.LabelWidget( { label: mw.message( 'growthexperiments-help-panel-questioncomplete-header' ).text() } )
				],
				classes: [ 'mw-ge-help-panel-question-complete' ]
			} ).$element
		} );

		this.questionCompleteConfirmationLabel = new OO.ui.LabelWidget( {
			label: $( '<p>' ).text( this.questionCompleteConfirmationText )
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

		// Add the footers
		this.$foot.append( this.askhelpFooterPanel.$element );

		this.panels.addItems( [
			this.homePanel,
			this.askhelpPanel,
			this.generalhelpPanel,
			this.questioncompletePanel
		] );

		this.$body.append( this.panels.$element );
		this.$element.on( 'click', 'a[data-link-id]', this.logLinkClick.bind( this ) );
	};

	/**
	 * @param {Object} event
	 */
	HelpPanelProcessDialog.prototype.logLinkClick = function ( event ) {
		var linkId = $( event.currentTarget ).data( 'link-id' );
		if ( linkId ) {
			this.logger.log( 'link-click', linkId );
		}
	};

	HelpPanelProcessDialog.prototype.getSetupProcess = function ( data ) {
		return HelpPanelProcessDialog.super.prototype.getSetupProcess
			.call( this, data )
			.next( function () {
				this.setMode( 'home' );
			}, this );
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

	HelpPanelProcessDialog.prototype.getActionProcess = function ( action ) {
		return HelpPanelProcessDialog.super.prototype.getActionProcess.call( this, action )
			.next( function () {
				var submitAttemptData,
					postData;
				if ( action === 'close' ) {
					this.logger.log( 'close' );
					this.close();
				}
				if ( action === 'reset' ) {
					this.swapPanel( 'home' );
				}
				if ( action === 'home' ) {
					if ( this.currentMode === 'search' ) {
						action = 'general-help';
					}
					// One of: back-home, back-general-help
					this.logger.log( 'back-' + action, { from: this.currentMode } );
					this.swapPanel( action );
				}
				if ( action === 'ask-help' ) {
					this.logger.log( 'review' );
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
					/* eslint-disable camelcase */
					submitAttemptData = {
						question_length: this.askhelpTextInput.getValue().length,
						include_title: this.questionIncludeTitleCheckbox.isSelected(),
						had_email: !!this.userEmail,
						had_email_confirmed: !!this.userEmailConfirmed
					};
					/* eslint-enable camelcase */
					this.logger.log( 'submit-attempt', submitAttemptData );

					// Disable the primary button while executing the API call.
					this.askhelpSubmitButton.setDisabled( true );
					// Toggle the first edit text, will set depending on API response.
					this.questionCompleteFirstEditText.toggle( false );
					postData = {
						source: this.source,
						action: 'helppanelquestionposter',
						relevanttitle: this.questionIncludeTitleCheckbox.isSelected() ? this.relevantTitle.getPrefixedText() : '',
						body: this.askhelpTextInput.getValue()
					};
					// Start pre-loading tour for help panel.
					if ( this.source === 'helppanel' &&
						!mw.user.options.get( 'growthexperiments-tour-help-panel' ) ) {
						mw.loader.load( 'ext.guidedTour.tour.helppanel' );
					}
					return new mw.Api().postWithToken( 'csrf', postData )
						.then( function ( data ) {
							this.logger.incrementUserEditCount();
							this.logger.log( 'submit-success', $.extend(
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
										} ).text( this.viewQuestionText )
									)
								)
							);
							this.setNotificationLabelText();
							this.swapPanel( action );

							if ( this.source === 'helppanel' ) {
								this.launchIntroTour(
									'helppanel',
									'growthexperiments-tour-help-panel'
								);
							}

							mw.hook( 'growthExperiments.helpPanelQuestionPosted' ).fire( data );
							// Reset the post a question text inputs.
							this.questionTextInput.setValue( '' );
							mw.storage.set( this.storageKey, '' );
						}.bind( this ), function ( errorCode ) {
							// Return a recoverable error. The user can either try again, or they
							// can follow the instructions in the error message for how to post
							// their message manually.
							// Re-enable the submit button once the user is done with modal.
							submitAttemptData.error = errorCode;
							this.logger.log( 'submit-failure', submitAttemptData );
							this.askhelpSubmitButton.setDisabled( false );
							return $.Deferred().reject(
								new OO.ui.Error( $( '<p>' ).append( this.submitFailureMessage ) )
							).promise();
						}.bind( this ) );
				}
				if ( action === 'helppanelclose' ) {
					this.close();
					// Show mentorship tour if that was the homepage module that was used
					if ( this.source === 'homepage-mentorship' ) {
						this.launchIntroTour(
							'homepage_mentor',
							'growthexperiments-tour-homepage-mentorship'
						);
					}
				}
			}.bind( this ) );
	};

	/**
	 * Launches a tour if the tour has never been shown before and marks the tour as viewed
	 * @param {string} tourName
	 * @param {string} tourPreferenceKey
	 */
	HelpPanelProcessDialog.prototype.launchIntroTour = function ( tourName, tourPreferenceKey ) {
		if ( !mw.user.options.get( tourPreferenceKey ) ) {
			mw.loader.using( 'ext.guidedTour.tour.' + tourName, function () {
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
	 * for the animation effects (not yet implemented).
	 *
	 * @return {number}
	 */
	HelpPanelProcessDialog.prototype.getBodyHeight = function () {
		if ( !this.homeHeight && this.currentMode === 'home' ) {
			// 60.0167 is the height of the footer contents on the "ask-help" screen which
			// has back and post buttons. We need to set the number here since we want the
			// home panel to have the same height as the ask-help panel, but don't yet
			// know the height of this.$foot
			this.homeHeight = this.panels.getCurrentItem().$element.outerHeight( true ) + 60.0167;
		}

		// Use home height minus dynamically calculated footer (not all panels have a footer)
		return this.homeHeight - this.$foot.outerHeight( true );
	};

	module.exports = HelpPanelProcessDialog;

}() );
