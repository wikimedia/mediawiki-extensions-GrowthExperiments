( function () {

	/**
	 * @class
	 * @extends OO.ui.ProcessDialog
	 *
	 * @param {Object} config
	 * @cfg {mw.libs.ge.HelpPanelLogger} logger
	 * @constructor
	 */
	var HelpPanelProcessDialog = function helpPanelProcessDialog( config ) {
			HelpPanelProcessDialog.super.call( this, config );
			this.logger = config.logger;
		},
		linksConfig = mw.config.get( 'wgGEHelpPanelLinks' );

	OO.inheritClass( HelpPanelProcessDialog, OO.ui.ProcessDialog );

	HelpPanelProcessDialog.static.name = 'HelpPanel';
	HelpPanelProcessDialog.static.title = mw.message( 'growthexperiments-help-panel-home-title' ).text();
	HelpPanelProcessDialog.static.actions = [
		// Allow user to close the panel at any stage.
		{
			icon: 'close',
			flags: 'safe',
			framed: false,
			modes: [ 'home', 'questionreview', 'questioncomplete' ]
		},
		// The "Close" action button duplicates the action provided by the X
		// at the top of the panel, this is part of the design of the panel.
		{
			label: mw.message( 'growthexperiments-help-panel-close' ).text(),
			modes: [ 'questioncomplete' ],
			flags: 'safe',
			framed: true
		}
	];

	HelpPanelProcessDialog.prototype.attachActions = function () {
		HelpPanelProcessDialog.super.prototype.attachActions.call( this );
		// Hack to place the settings cog as a "primary" (top-right) action.
		this.$primaryActions.append( this.settingsCog.$element );
	};

	/**
	 * Swap the state of the help panel dialog.
	 *
	 * Modeled off of VisualEditor's swapPanel().
	 *
	 * @param {string} panel One of 'home', 'questionreview', or 'questioncomplete'
	 * @throws {Error} Unknown panel.
	 */
	HelpPanelProcessDialog.prototype.swapPanel = function ( panel ) {
		var panelObj = this[ panel + 'Panel' ];
		this.title.setLabel(
			mw.message( 'growthexperiments-help-panel-' + panel + '-title' ).text()
		);
		if ( ( [ 'home', 'questionreview', 'questioncomplete' ].indexOf( panel ) ) === -1 ) {
			throw new Error( 'Unknown panel: ' + panel );
		}
		if ( panel === 'home' ) {
			this.homeFooterPanel.toggle( true );
			this.questionReviewFooterPanel.toggle( false );

		}
		if ( panel === 'questionreview' ) {
			this.questionReviewFooterPanel.toggle( true );
			this.homeFooterPanel.toggle( false );
		}
		if ( panel === 'questioncomplete' ) {
			// Use the action defined in static.actions rather than a custom implementation
			// for this panel.
			this.questionReviewFooterPanel.toggle( false );
		}
		this.panels.setItem( panelObj );
		this.actions.setMode( panel );
	};

	/**
	 * Connected to the questionReviewTextInput field.
	 */
	HelpPanelProcessDialog.prototype.onTextInputChange = function () {
		var reviewTextInputValue = this.questionReviewTextInput.getValue();
		// Enable the "Submit" button on the review step if there's text input.
		this.questionReviewSubmitButton.setDisabled( !reviewTextInputValue );
		// Copy the review text input back to the initial question field, in case the
		// user clicks "back".
		this.questionTextInput.setValue( reviewTextInputValue );
		// Save the draft text in local storage in case the user reloads their page.
		mw.storage.set( 'help-panel-question-text', reviewTextInputValue );
	};

	/**
	 * Connected to the change event on this.questionReviewAddEmail.
	 */
	HelpPanelProcessDialog.prototype.onEmailInput = function () {
		var reviewTextInputValue = this.questionReviewTextInput.getValue();
		// If user has typed in the email field, disable the submit button until the
		// email address is valid.
		if ( this.questionReviewAddEmail.getValue() ) {
			this.questionReviewAddEmail.getValidity()
				.then( function () {
					// Enable depending on whether the question text is input.
					this.questionReviewSubmitButton.setDisabled( !reviewTextInputValue );
				}.bind( this ), function () {
					// Always disable if email is invalid.
					this.questionReviewSubmitButton.setDisabled( true );
				}.bind( this ) );
		} else {
			// If no email value, set submit button state based on review text input
			this.questionReviewSubmitButton.setDisabled( !reviewTextInputValue );
		}
	};

	/**
	 * Set the value of the text area on the review step.
	 */
	HelpPanelProcessDialog.prototype.populateReviewText = function () {
		this.questionReviewTextInput.setValue( this.questionTextInput.getValue() );
		// Disable "Continue" button if there is no text.
		this.askQuestionContinueButton.setDisabled( !this.questionTextInput.getValue() );

		if ( !this.previousQuestionText && this.questionTextInput.getValue() ) {
			this.logger.log( 'enter-question-text' );
		}
		this.previousQuestionText = this.questionTextInput.getValue();
	};

	HelpPanelProcessDialog.prototype.setNotificationLabelText = function () {
		var questionCompleteNotificationsLabelKey = this.questionReviewAddEmail.getValue() ?
				'growthexperiments-help-panel-questioncomplete-confirmation-email-unconfirmed' :
				'growthexperiments-help-panel-questioncomplete-confirmation-email-none',
			specialNotificationsLink = mw.html.element( 'a', {
				href: new mw.Title( 'Special:Notifications' ).getUrl(),
				target: '_blank',
				'data-link-id': 'special-notifications'
			}, mw.message( 'growthexperiments-help-panel-notifications-link-text' ).text() ),
			specialConfirmEmailLink = mw.html.element( 'a', {
				href: new mw.Title( 'Special:ConfirmEmail' ).getUrl(),
				target: '_blank',
				'data-link-id': 'special-confirm-email'
			}, mw.message( 'growthexperiments-help-panel-confirm-email-link-text' ).text() ),
			params = [];
		// Set notification text dependent on email status.
		if ( this.userEmail && this.userEmailConfirmed ) {
			questionCompleteNotificationsLabelKey = 'growthexperiments-help-panel-questioncomplete-confirmation-email-confirmed';
		} else {
			// User has no email or has an unconfirmed email. The confirmation message will include
			// a link to Special:Notifications.
			params.push( specialNotificationsLink );
			if ( ( this.questionReviewAddEmail.getValue() || this.userEmail ) &&
				!this.userEmailConfirmed ) {
				// User doesn't have a confirmed email. Confirmation message will include a link to
				// Special:ConfirmEmail.
				params.push( specialConfirmEmailLink );
			}
		}
		this.questionCompleteNotificationsText.setLabel( new OO.ui.HtmlSnippet( $( '<p>' ).append(
			mw.message( questionCompleteNotificationsLabelKey ).params( params ).parse()
		) ) );
	};

	/**
	 * Set relevant email fields on question review step.
	 */
	HelpPanelProcessDialog.prototype.setEmailFields = function () {
		// User doesn't have email or it isn't confirmed, provide an input and help.
		if ( !this.userEmail || !this.userEmailConfirmed ) {
			this.questionReviewContent.addItems( [
				new OO.ui.FieldLayout( this.questionReviewAddEmail, {
					label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-questionreview-email-optional' ).text() ),
					align: 'top',
					help: !this.userEmail ?
						new OO.ui.HtmlSnippet( mw.message( 'growthexperiments-help-panel-questionreview-no-email-note' ).parse() ) :
						new OO.ui.HtmlSnippet( mw.message( 'growthexperiments-help-panel-questionreview-unconfirmed-email-note' ).parse() ),
					helpInline: true
				} )
			] );
		}
		// Output the user's email and help about notifications.
		if ( this.userEmail && this.userEmailConfirmed ) {
			this.questionReviewContent.addItems( [
				new OO.ui.FieldLayout(
					new OO.ui.Widget( {
						content: [
							new OO.ui.Element( {
								$content: $( '<p>' ).text( this.userEmail )
							} )
						]
					} ),
					{
						label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-questionreview-email' ).text() ),
						align: 'top',
						helpInline: true,
						help: new OO.ui.HtmlSnippet( mw.message( 'growthexperiments-help-panel-questionreview-note' ).parse() )
					}
				)
			] );
		}
	};

	HelpPanelProcessDialog.prototype.buildSettingsCog = function () {
		this.settingsCog = new OO.ui.PopupButtonWidget( {
			$overlay: this.$element,
			icon: 'settings',
			// Hack for styling
			classes: [ 'mw-ge-help-panel-settings-cog', 'oo-ui-actionWidget' ],
			framed: false,
			popup: {
				$content: $( '<p>' ).append( mw.html.element( 'a', {
					href: new mw.Title( 'Special:Preferences#mw-prefsection-editing' ).getUrl(),
					target: '_blank',
					'data-link-id': 'special-preferences'
				}, mw.message( 'growthexperiments-help-panel-settings-cog-preferences-link' ).text() ) ),
				padded: true,
				width: 260,
				classes: [ 'mw-ge-help-panel-settings-cog-content' ],
				anchor: false,
				$container: this.$element
			}
		} );
		this.settingsCog.popup.connect( this, { toggle: 'onCogMenuToggle' } );
	};

	HelpPanelProcessDialog.prototype.onCogMenuToggle = function ( show ) {
		this.logger.log( show ? 'cog-open' : 'cog-close' );
		this.settingsCog.$element.toggleClass( 'active', show );
	};

	HelpPanelProcessDialog.prototype.initialize = function () {
		HelpPanelProcessDialog.super.prototype.initialize.call( this );

		this.userEmail = mw.config.get( 'wgGEHelpPanelUserEmail' );
		this.userEmailConfirmed = mw.config.get( 'wgGEHelpPanelUserEmailConfirmed' );

		/**
		 * @type {mw.Title}
		 */
		this.relevantTitle = mw.Title.newFromText( mw.config.get( 'wgRelevantPageName' ) );

		this.panels = new OO.ui.StackLayout();

		this.homePanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );
		this.questionreviewPanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );
		this.questioncompletePanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );

		// Fields
		this.buildSettingsCog();
		this.previousQuestionText = mw.storage.get( 'help-panel-question-text' );
		this.questionTextInput = new OO.ui.MultilineTextInputWidget( {
			placeholder: mw.message( 'growthexperiments-help-panel-question-placeholder' ).text(),
			multiline: true,
			maxLength: 2000,
			rows: 3,
			maxRows: 3,
			autosize: true,
			value: mw.storage.get( 'help-panel-question-text' ),
			spellcheck: true,
			required: true,
			autofocus: false
		} ).connect( this, { change: 'populateReviewText' } );

		this.questionReviewTextInput = new OO.ui.MultilineTextInputWidget( {
			placeholder: mw.message( 'growthexperiments-help-panel-question-placeholder' ).text(),
			multiline: true,
			maxLength: 2000,
			rows: 3,
			maxRows: 3,
			autosize: true,
			value: mw.storage.get( 'help-panel-question-text' ),
			spellcheck: true,
			required: true,
			autofocus: !OO.ui.isMobile()
		} ).connect( this, { change: 'onTextInputChange' } );

		this.questionIncludeTitleCheckbox = new OO.ui.CheckboxInputWidget( {
			value: 1,
			selected: true
		} );

		this.questionReviewAddEmail = new OO.ui.TextInputWidget( {
			placeholder: mw.message( 'growthexperiments-help-panel-questionreview-add-email-placeholder' ).text(),
			value: this.userEmail,
			type: 'email'
		} ).connect( this, { change: 'onEmailInput' } );

		this.askQuestionContinueButton = new OO.ui.ButtonWidget( {
			flags: [ 'progressive', 'primary' ],
			disabled: !mw.storage.get( 'help-panel-question-text' ),
			label: mw.message( 'growthexperiments-help-panel-question-button-text' ).text()
		} ).connect( this, { click: [ 'executeAction', 'questionreview' ] } );

		// Build home content of help panel.
		this.homeContent = new OO.ui.FieldsetLayout();
		this.homeContent.addItems( [
			new OO.ui.FieldLayout(
				new OO.ui.Widget( {
					content: [
						new OO.ui.Element( {
							$content: linksConfig.helpPanelLinks
						} ),
						new OO.ui.Element( {
							$content: $( '<p>' )
								.append( $( '<strong>' ).html( linksConfig.viewMoreLink ) )
						} )
					] } ),
				{
					align: 'top',
					label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-editing-help-links-widget-header' ).text() )
				}
			)
		] );

		// Place the input and button in the footer to mimic the style of other actions.
		this.homeFooterPanel = new OO.ui.PanelLayout( {
			padded: true,
			expanded: false
		} );
		this.homeFooterFieldset = new OO.ui.FieldsetLayout();
		this.homeFooterFieldset.addItems( [
			new OO.ui.FieldLayout(
				new OO.ui.Widget( {
					content: [
						this.questionTextInput
					]
				} ),
				{
					label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-question-widget-header' ).text() ),
					align: 'top'
				}
			),
			new OO.ui.FieldLayout( this.askQuestionContinueButton )
		] );
		this.homeFooterPanel.$element.append( this.homeFooterFieldset.$element );

		this.homePanel.$element.append( this.homeContent.$element );

		// Build step two of ask question process.
		this.questionReviewContent = new OO.ui.FieldsetLayout();

		this.questionReviewContent.addItems( [
			new OO.ui.LabelWidget( {
				label: $( '<p>' )
					.append( mw.message(
						'growthexperiments-help-panel-questionreview-header',
						$( linksConfig.helpDeskLink ),
						mw.user.getName()
					).parse() )
			} )
		] );

		this.setEmailFields();

		this.questionReviewContent.addItems( [
			new OO.ui.FieldLayout(
				this.questionReviewTextInput, {
					label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-questionreview-label' ).text() ),
					align: 'top'
				} ),
			new OO.ui.FieldLayout(
				this.questionIncludeTitleCheckbox, {
					label: mw.message( 'growthexperiments-help-panel-questionreview-include-article-title' ).text(),
					align: 'inline',
					helpInline: true,
					help: this.relevantTitle.getPrefixedText()
				}
			)
		] );
		this.questionreviewPanel.$element.append( this.questionReviewContent.$element );

		this.questionReviewFooterPanel = new OO.ui.PanelLayout( {
			expanded: false,
			padded: true
		} );
		this.questionReviewFooterFieldset = new OO.ui.FieldsetLayout();
		this.questionReviewSubmitButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'growthexperiments-help-panel-submit-question-button-text' ).text(),
			// Inherit classes from primary action, to position the button on the right.
			classes: [ 'oo-ui-processDialog-actions-primary' ],
			flags: [ 'primary', 'progressive' ]
		} ).connect( this, { click: [ 'executeAction', 'questioncomplete' ] } );

		this.questionReviewFooterFieldset.addItems( [
			new OO.ui.ButtonWidget( {
				label: mw.message( 'growthexperiments-help-panel-back-home' ).text()
			} ).connect( this, { click: [ 'executeAction', 'home' ] } ),
			this.questionReviewSubmitButton
		] );
		this.questionReviewFooterPanel.$element.append(
			this.questionReviewFooterFieldset.$element
		);

		this.questionCompleteContent = new OO.ui.FieldsetLayout( {
			label: new OO.ui.HorizontalLayout( {
				items: [
					new OO.ui.IconWidget( { icon: 'check', flags: [ 'progressive' ] } ),
					new OO.ui.LabelWidget( { label: mw.message( 'growthexperiments-help-panel-questioncomplete-header' ).text() } )
				]
			} ).$element
		} );

		this.questionCompleteConfirmationText = new OO.ui.LabelWidget( {
			label: $( '<p>' )
				.text( mw.message( 'growthexperiments-help-panel-questioncomplete-confirmation-text' ).text() )
		} );
		this.questionCompleteFirstEditText = new OO.ui.LabelWidget( {
			label: $( '<p>' )
				.text( mw.message( 'growthexperiments-help-panel-questioncomplete-first-edit' ).text() )
		} );
		this.questionCompleteViewQuestionText = new OO.ui.LabelWidget();
		this.questionCompleteNotificationsText = new OO.ui.LabelWidget();
		this.questionCompleteContent.addItems( [
			this.questionCompleteConfirmationText,
			this.questionCompleteNotificationsText,
			this.questionCompleteViewQuestionText,
			this.questionCompleteFirstEditText

		] );
		this.questioncompletePanel.$element.append( this.questionCompleteContent.$element );

		// Add the footers
		this.$foot.append( this.homeFooterPanel.$element );
		this.$foot.append( this.questionReviewFooterPanel.$element );

		this.panels.addItems( [
			this.homePanel,
			this.questionreviewPanel,
			this.questioncompletePanel
		] );
		this.$body.append( this.panels.$element );

		this.$element.on( 'click', 'a[data-link-id]', this.logLinkClick.bind( this ) );
	};

	HelpPanelProcessDialog.prototype.logLinkClick = function ( e ) {
		var linkId = $( e.target ).data( 'link-id' );
		if ( linkId ) {
			this.logger.log( 'link-click', linkId );
		}
	};

	HelpPanelProcessDialog.prototype.getSetupProcess = function ( data ) {
		return HelpPanelProcessDialog.super.prototype.getSetupProcess
			.call( this, data )
			.next( function () {
				this.actions.setMode( 'home' );
			}, this );
	};

	HelpPanelProcessDialog.prototype.getActionProcess = function ( action ) {
		return HelpPanelProcessDialog.super.prototype.getActionProcess.call( this, action )
			.next( function () {
				var submitAttemptData;
				if ( action === 'reset' ) {
					this.swapPanel( 'home' );
				}
				if ( action === 'home' ) {
					this.logger.log( 'back-home' );
					this.swapPanel( action );
				}
				if ( action === 'questionreview' ) {
					this.logger.log( 'review' );
					this.swapPanel( action );
				}
				if ( action === 'questioncomplete' ) {
					/* eslint-disable camelcase */
					submitAttemptData = {
						question_length: this.questionReviewTextInput.getValue().length,
						include_title: this.questionIncludeTitleCheckbox.isSelected(),
						had_email: !!mw.config.get( 'wgGEHelpPanelUserEmail' ),
						had_email_confirmed: !!mw.config.get( 'wgGEHelpPanelUserEmailConfirmed' ),
						email_form_has_content: !!this.questionReviewAddEmail.getValue(),
						email_form_changed: this.questionReviewAddEmail.getValue() !==
							this.questionReviewAddEmail.defaultValue
					};
					/* eslint-enable camelcase */
					this.logger.log( 'submit-attempt', submitAttemptData );

					// Disable the primary button while executing the API call.
					this.questionReviewSubmitButton.setDisabled( true );
					// Toggle the first edit text, will set depending on API response.
					this.questionCompleteFirstEditText.toggle( false );
					return new mw.Api().postWithToken( 'csrf', {
						action: 'helppanelquestionposter',
						email: this.questionReviewAddEmail.getValue(),
						relevanttitle: this.questionIncludeTitleCheckbox.isSelected() ? this.relevantTitle.getPrefixedText() : '',
						body: this.questionReviewTextInput.getValue()
					} )
						.then( function ( data ) {
							this.logger.incrementUserEditCount();
							this.logger.log( 'submit-success', $.extend(
								submitAttemptData,
								/* eslint-disable camelcase */
								{
									revision_id: data.helppanelquestionposter.revision,
									email_action_status: data.helppanelquestionposter.email
								}
								/* eslint-enable camelcase */
							) );

							if ( data.helppanelquestionposter.isfirstedit ) {
								this.questionCompleteFirstEditText.toggle( true );
							}
							this.questionCompleteViewQuestionText.setLabel( $( '<p>' )
								.append(
									$( '<strong>' ).append( $( '<a>', {
										href: data.helppanelquestionposter.viewquestionurl,
										target: '_blank',
										'data-link-id': 'view-question',
										text: mw.message( 'growthexperiments-help-panel-questioncomplete-view-link-text' ).text()
									} ) ) ) );
							this.setNotificationLabelText();
							this.swapPanel( action );
							// Reset the post a question text inputs.
							this.questionTextInput.setValue( '' );
							mw.storage.set( 'help-panel-question-text', '' );
						}.bind( this ), function () {
							// Return a recoverable error. The user can either try again, or they
							// can follow the instructions in the error message for how to post
							// their message manually.
							// Re-enable the submit button once the user is done with modal.
							this.logger.log( 'submit-failure', submitAttemptData );
							this.questionReviewSubmitButton.setDisabled( false );
							return $.Deferred().reject( new OO.ui.Error( $( '<p>' ).append( mw.message(
								'growthexperiments-help-panel-question-post-error', linksConfig.helpDeskLink
							).parse() ) ) ).promise();
						}.bind( this ) );
				}
			}.bind( this ) );
	};

	HelpPanelProcessDialog.prototype.getBodyHeight = function () {
		return this.panels.getCurrentItem().$element.outerHeight( true );
	};

	OO.setProp( mw, 'libs', 'ge', 'HelpPanelProcessDialog', HelpPanelProcessDialog );

}() );
