( function () {

	/**
	 * @class
	 * @extends OO.ui.ProcessDialog
	 *
	 * @constructor
	 */
	var HelpPanelProcessDialog = function helpPanelProcessDialog() {
			HelpPanelProcessDialog.super.apply( this, arguments );
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
			flags: 'safe'
		},
		{
			framed: false,
			icon: 'settings',
			title: mw.message( 'growthexperiments-help-panel-settings-cog-tooltip' ).text(),
			modes: [ 'home', 'questionreview', 'questioncomplete' ],
			flags: [ 'primary', 'safe' ],
			action: 'settings'
		}
	];

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
		this.setSize( 'small' );
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
	 * Set the value of the text area on the review step.
	 */
	HelpPanelProcessDialog.prototype.populateReviewText = function () {
		this.questionReviewTextInput.setValue( this.questionTextInput.getValue() );
	};

	HelpPanelProcessDialog.prototype.initialize = function () {
		HelpPanelProcessDialog.super.prototype.initialize.call( this );

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
		this.questionTextInput = new OO.ui.MultilineTextInputWidget( {
			placeholder: mw.message( 'growthexperiments-help-panel-question-placeholder' ).text(),
			multiline: true,
			maxLength: 2000,
			minRows: 3,
			maxRows: 3,
			autosize: true,
			value: mw.storage.get( 'help-panel-question-text' ),
			spellcheck: true,
			autofocus: true
		} ).connect( this, { change: 'populateReviewText' } );

		this.questionReviewTextInput = new OO.ui.MultilineTextInputWidget( {
			placeholder: mw.message( 'growthexperiments-help-panel-question-placeholder' ).text(),
			multiline: true,
			maxLength: 2000,
			minRows: 3,
			maxRows: 3,
			autosize: true,
			spellcheck: true,
			required: true,
			autofocus: true
		} ).connect( this, { change: 'onTextInputChange' } );

		this.questionIncludeTitleCheckbox = new OO.ui.CheckboxInputWidget( {
			value: 1,
			selected: true
		} );

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
			new OO.ui.FieldLayout(
				new OO.ui.ButtonWidget( {
					flags: [ 'progressive', 'primary' ],
					label: mw.message( 'growthexperiments-help-panel-question-button-text' ).text()
				} )
					.connect( this, { click: [ 'executeAction', 'questionreview' ] } )
			)
		] );
		this.homeFooterPanel.$element.append( this.homeFooterFieldset.$element );

		this.homePanel.$element.append( this.homeContent.$element );

		// Build step two of ask question process.
		this.questionReviewContent = new OO.ui.FieldsetLayout();

		this.questionReviewContent.addItems( [
			new OO.ui.LabelWidget( {
				label: $( '<p>' )
					.append( mw.message( 'growthexperiments-help-panel-questionreview-header', $( linksConfig.helpDeskLink ) ).parse() )
			} ),
			new OO.ui.Element( {
				$content: $( '<p>' )
					.append( $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-questionreview-username' ).text() ) )
					.append( $( '<p>' ).text( mw.user.getName() ) )
			} )
		] );
		if ( mw.config.get( 'wgGEHelpPanelEmail' ) ) {
			this.questionReviewContent.addItems( [
				new OO.ui.Element( {
					$content: $( '<p>' )
						.append( $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-questionreview-email' ).text() ) )
						.append( $( '<p>' ).text( mw.config.get( 'wgGEHelpPanelEmail' ) ) )
				} ),
				new OO.ui.LabelWidget( {
					label: $( '<small>' )
						.html( mw.message( 'growthexperiments-help-panel-questionreview-note' ).parse() )
				} )
			] );
		}
		this.questionReviewContent.addItems( [
			new OO.ui.FieldLayout(
				new OO.ui.Widget( {
					content: [
						this.questionReviewTextInput
					]
				} ),
				{
					label: $( '<strong>' ).text( mw.message( 'growthexperiments-help-panel-questionreview-label' ).text() ),
					align: 'top'
				}
			),
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
			disabled: true,
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

		this.questionCompleteContent.addItems( [
			new OO.ui.LabelWidget( {
				label: $( '<p>' )
					.text( mw.message( 'growthexperiments-help-panel-questioncomplete-confirmation-text' ).text() )
			} )
		] );
		if ( mw.config.get( 'wgGEHelpPanelEmail' ) ) {
			this.questionCompleteContent.addItems( [
				new OO.ui.LabelWidget( {
					label: $( '<p>' ).text( mw.message( 'growthexperiments-help-panel-questioncomplete-confirmation-email' ).text() )
				} )
			] );
		}
		this.questionCompleteContent.addItems( [
			// For now this links to the talk page, not the actual heading that was added by
			// the user, since MessagePoster does not return that for us.
			new OO.ui.Element( {
				$content: $( '<p>' ).append( $( linksConfig.helpDeskLink )
					.text( mw.message( 'growthexperiments-help-panel-questioncomplete-view-link-text' ).text() ) )
			} )
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
				var messagePosterPromise;
				if ( action === 'settings' ) {
					window.open( new mw.Title( 'Special:Preferences#mw-prefsection-editing' ).getUrl() );
				}
				if ( action === 'questionreview' || action === 'home' ) {
					this.swapPanel( action );
				}
				if ( action === 'questioncomplete' ) {
					// Needed to disable the primary button.
					this.questionReviewSubmitButton.setDisabled( true );
					messagePosterPromise = mw.messagePoster.factory.create(
						mw.Title.newFromText( mw.config.get( 'wgGEHelpPanelHelpDeskTitle' ) )
					);
					return messagePosterPromise.then( function ( messagePoster ) {
						var templateArgs = [
							'growthexperiments-help-panel-question-subject-template',
							mw.user.getName()
						];
						if ( this.questionIncludeTitleCheckbox.isSelected() ) {
							templateArgs.push( ': ' + this.relevantTitle );
						}
						return messagePoster.post(
							mw.message.apply( this, templateArgs ).parse(),
							this.questionReviewTextInput.getValue()
						);
					}.bind( this ), function () {
						// Return a recoverable error. The user can either try again, or they
						// can follow the instructions in the error message for how to post
						// their message manually.
						// Re-enable the submit button once the user is done with modal.
						this.questionReviewSubmitButton.setDisabled( false );
						return $.Deferred().reject( new OO.ui.Error( $( '<p>' ).append( mw.message(
							'growthexperiments-help-panel-question-post-error', linksConfig.helpDeskLink
						).parse() ) ) ).promise();
					}.bind( this ) ).then( function () {
						// Avoid making extra API requests by using the wgUserEditCount. The
						// count might not be 100% accurate since the user could make edits in
						// a separate tab, or post a second question through the help panel, etc.
						// @todo redo this along with T211370.
						if ( mw.config.get( 'wgUserEditCount' ) === 0 ) {
							this.questionCompleteContent.addItems( [
								new OO.ui.LabelWidget( {
									label: mw.message( 'growthexperiments-help-panel-questioncomplete-first-edit' ).text()
								} )
							] );
						}
						this.swapPanel( action );
						this.questionTextInput.setValue( '' );
					}.bind( this ) );
				}
			}, this );
	};

	HelpPanelProcessDialog.prototype.getBodyHeight = function () {
		return this.panels.getCurrentItem().$element.outerHeight( true );
	};

	OO.setProp( mw, 'libs', 'ge', 'HelpPanelProcessDialog', HelpPanelProcessDialog );

}() );
