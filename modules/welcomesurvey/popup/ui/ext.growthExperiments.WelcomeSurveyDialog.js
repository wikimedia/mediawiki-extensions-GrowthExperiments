( function () {

	var util = {
		groupBy: function ( xs, key ) {
			return xs.reduce( function ( rv, x ) {
				( rv[ x[ key ] ] = rv[ x[ key ] ] || [] ).push( x );
				return rv;
			}, {} );
		}
	};

	/**
	 * Main survey dialog showing the questions one at a time.
	 *
	 * @param {Object} config
	 * @cfg {string} group Name of the current experiment group
	 * @cfg {Object} questionsConfig Detailed configuration of the survey questions to show
	 * @cfg {string} privacyStatementUrl URL of the survey privacy statement
	 * @constructor
	 */
	function WelcomeSurveyDialog( config ) {
		WelcomeSurveyDialog.parent.call( this, {
			classes: [ 'welcome-survey-dialog' ],
			size: 'large'
		} );
		this.group = config.group;
		this.questionsConfig = config.questionsConfig;
		this.privacyStatementUrl = config.privacyStatementUrl;
		this.responses = {};
	}
	OO.inheritClass( WelcomeSurveyDialog, OO.ui.ProcessDialog );

	WelcomeSurveyDialog.static.name = 'WelcomeSurveyDialog';
	WelcomeSurveyDialog.static.title = mw.msg( 'welcomesurvey', mw.user.getName() );
	WelcomeSurveyDialog.static.actions = [
		{
			action: 'save',
			label: mw.msg( 'welcomesurvey-save-btn' ),
			flags: [ 'primary', 'progressive' ],
			modes: 'last-question'
		},
		{
			action: 'skip',
			label: mw.msg( 'welcomesurvey-skip-btn' ),
			classes: [ 'welcome-survey-skip-button' ],
			flags: 'destructive',
			modes: 'default'
		}
	];

	/**
	 * @inheritdoc
	 */
	WelcomeSurveyDialog.prototype.getBodyHeight = function () {
		return 500;
	};

	/**
	 * @inheritdoc
	 */
	WelcomeSurveyDialog.prototype.initialize = function () {
		var positionIndicator,
			nav,
			$sidePanelContent,
			sidePanel,
			menuLayout,
			questionPanels;

		WelcomeSurveyDialog.parent.prototype.initialize.apply( this, arguments );

		questionPanels = this.buildQuestionPanels( this.questionsConfig );
		this.mainPanel = new OO.ui.PanelLayout( { padded: true, scrollable: true, classes: [ 'welcomesurvey-main' ] } );
		this.questionsLayout = new OO.ui.StackLayout( {
			expanded: false,
			scrollable: false,
			items: questionPanels
		} );

		this.questionsLayout.connect( this, { set: 'onQuestionChange' } );

		positionIndicator = new mw.libs.ge.WelcomeSurvey.StackPositionIndicatorWidget(
			this.questionsLayout
		);
		nav = new mw.libs.ge.WelcomeSurvey.StackNavigatorWidget(
			this.questionsLayout,
			{ classes: [ 'welcomesurvey-navigator' ] }
		);

		this.$subtitle = $( '<div>' )
			.addClass( 'welcomesurvey-subtitle' )
			.text( mw.msg( 'welcomesurvey-subtitle' ) );

		this.mainPanel.$element.append(
			this.$subtitle,
			positionIndicator.$element,
			this.questionsLayout.$element
		);

		$sidePanelContent = $( '<div>' ).append(
			$( '<div>' )
				.addClass( 'privacy-section' )
				.append(
					$( '<div>' ).addClass( 'section-title' ).text( mw.msg( 'welcomesurvey-sidebar-privacy-title' ) ),
					new mw.libs.ge.WelcomeSurvey.PrivacyNoticeWidget( {
						url: this.privacyStatementUrl,
						classes: [ 'section-text' ]
					} ).$element
				),
			$( '<div>' )
				.addClass( 'editing-section' )
				.append(
					$( '<div>' ).addClass( 'section-title' ).text( mw.msg( 'welcomesurvey-sidebar-editing-title' ) ),
					$( '<p>' ).addClass( 'section-text' ).text( mw.msg( 'welcomesurvey-sidebar-editing-text' ) ),
					new mw.libs.ge.WelcomeSurvey.GettingStartedLinksWidget( 'survey-popup' ).$element
				)
		);

		if ( OO.ui.isMobile() ) {
			this.mainPanel.$element.append( $sidePanelContent.addClass( 'welcomesurvey-sidebar-mobile' ) );
			this.$body.append( this.mainPanel.$element );
			this.$foot.find( '.oo-ui-processDialog-actions-other' ).prepend(
				nav.$element.addClass( 'welcomesurvey-navigator-mobile' )
			);
		} else {
			this.mainPanel.$element.append( nav.$element );
			sidePanel = new OO.ui.PanelLayout( {
				padded: true,
				scrollable: true,
				classes: [ 'welcomesurvey-sidebar-desktop' ],
				$content: $sidePanelContent
			} );
			menuLayout = new OO.ui.MenuLayout( {
				menuPosition: 'after',
				contentPanel: this.mainPanel,
				menuPanel: sidePanel
			} );
			this.$body.append( menuLayout.$element );
		}

		this.renderTimestamp = Date.now();
	};

	/**
	 * Build all the panels that make up the survey based on the questions configuration.
	 * @param {Object} questionsConfig
	 * @return {OO.ui.PanelLayout[]}
	 */
	WelcomeSurveyDialog.prototype.buildQuestionPanels = function ( questionsConfig ) {
		var groupedConfig = util.groupBy( questionsConfig, 'group' );
		return Object.keys( groupedConfig ).map( function ( groupName ) {
			var groupPanel = new OO.ui.PanelLayout( { expanded: false } );
			groupPanel.$element.append( groupedConfig[ groupName ].map( function ( config ) {
				if ( config.type === 'select' && config[ 'other-message' ] ) {
					return this.buildSelectWithOtherWidget( config );
				}

				if ( config.type === 'select' ) {
					return this.buildSelectWidget( config );
				}

				if ( config.type === 'text' || config.type === 'email' ) {
					return this.buildTextInputWidget( config );
				}

				if ( config.type === 'multiselect' ) {
					return this.buildMultiSelectWidget( config );
				}

				if ( config.type === 'info' ) {
					return this.buildInfoWidget( config );
				}

				if ( config.type === 'check' ) {
					return this.buildCheckWidget( config );
				}

				return { $element: '' };
			}.bind( this ) ).map( function ( widget ) {
				return widget.$element;
			} ) );
			return groupPanel;
		}.bind( this ) );
	};

	/**
	 * Build a field with a mw.libs.ge.WelcomeSurvey.RadioSelectWithInputWidget
	 * based on the question config.
	 *
	 * @param {Object} config
	 * @return {OO.ui.FieldLayout}
	 */
	WelcomeSurveyDialog.prototype.buildSelectWithOtherWidget = function ( config ) {
		var radioSelectWithInput = new mw.libs.ge.WelcomeSurvey.RadioSelectWithInputWidget( {
			select: {
				items: Object.keys( config[ 'options-messages' ] ).map( function ( msg ) {
					return new OO.ui.RadioOptionWidget( {
						data: config[ 'options-messages' ][ msg ],
						label: mw.msg( msg )
					} );
				} )
			},
			otherOptionText: mw.msg( config[ 'other-message' ] ),
			input: {
				placeholder: mw.msg( config[ 'other-placeholder-message' ] ),
				maxLength: config[ 'other-size' ]
			}
		} );
		this.registerResponse( config.name, function () {
			// todo: consider making this backward compatible
			// with the previous version of the survey
			return radioSelectWithInput.getValue();
		} );
		return new OO.ui.FieldLayout(
			radioSelectWithInput, {
				align: 'top',
				label: mw.msg( config[ 'label-message' ] )
			} );
	};

	/**
	 * Build a field with a OO.ui.RadioSelectWidget
	 * based on the question config.
	 *
	 * @param {Object} config
	 * @return {OO.ui.FieldLayout}
	 */
	WelcomeSurveyDialog.prototype.buildSelectWidget = function ( config ) {
		var radioSelect = new OO.ui.RadioSelectWidget( {
			items: Object.keys( config[ 'options-messages' ] ).map( function ( msg ) {
				return new OO.ui.RadioOptionWidget( {
					data: config[ 'options-messages' ][ msg ],
					label: mw.msg( msg )
				} );
			} )
		} );
		this.registerResponse( config.name, function () {
			var item = radioSelect.findSelectedItem();
			return item ? item.getData() : null;
		} );
		return new OO.ui.FieldLayout(
			radioSelect, {
				align: 'top',
				label: mw.msg( config[ 'label-message' ] )
			} );
	};

	/**
	 * Build a text input,
	 * and optionally wrap it in a field layout to support 'label' and 'help',
	 * based on the question config.
	 *
	 * @param {Object} config
	 * @return {OO.ui.TextInputWidget|OO.ui.FieldLayout}
	 */
	WelcomeSurveyDialog.prototype.buildTextInputWidget = function ( config ) {
		var textInput = new OO.ui.TextInputWidget( {
			type: config.type,
			placeholder: mw.msg( config[ 'placeholder-message' ] ),
			maxLength: config.size
		} );
		this.registerResponse( config.name, function () {
			return textInput.getValue();
		} );

		if ( config[ 'label-message' ] || config[ 'help-message' ] ) {
			return new OO.ui.FieldLayout(
				textInput, {
					align: 'top',
					label: mw.msg( config[ 'label-message' ] ),
					helpInline: true,
					help: new OO.ui.HtmlSnippet( mw.message( config[ 'help-message' ] ).parse() )
				} );
		} else {
			return textInput;
		}
	};

	/**
	 * Build a widget to select multiple values,
	 * and optionally wrap it in a field layout to support 'label',
	 * based on the question config.
	 *
	 * @param {Object} config
	 * @return {OO.ui.MenuTagMultiselectWidget|OO.ui.CheckboxMultiselectWidget|OO.ui.FieldLayout}
	 */
	WelcomeSurveyDialog.prototype.buildMultiSelectWidget = function ( config ) {
		var multiselect = null;

		if ( config.allowArbitrary ) {
			multiselect = new OO.ui.MenuTagMultiselectWidget( {
				allowArbitrary: true,
				placeholder: mw.msg( config[ 'placeholder-message' ] ),
				options: Object.keys( config[ 'options-messages' ] ).map( function ( msg ) {
					return {
						data: config[ 'options-messages' ][ msg ],
						label: mw.msg( msg )
					};
				} )
			} );
			multiselect.on( 'change', function () {
				multiselect.toggleValid( true );
			} );
			this.registerResponse( config.name, function () {
				return multiselect.getValue();
			} );
		} else {
			multiselect = new OO.ui.CheckboxMultiselectWidget( {
				items: Object.keys( config[ 'options-messages' ] ).map( function ( msg ) {
					return new OO.ui.CheckboxMultioptionWidget( {
						data: config[ 'options-messages' ][ msg ],
						label: mw.msg( msg )
					} );
				} )
			} );
			this.registerResponse( config.name, function () {
				return multiselect.findSelectedItemsData();
			} );
		}

		if ( config[ 'label-message' ] ) {
			return new OO.ui.FieldLayout(
				multiselect, {
					align: 'top',
					label: mw.msg( config[ 'label-message' ] )
				} );
		} else {
			return multiselect;
		}
	};

	/**
	 * Build a field to display static information based on the question config.
	 *
	 * @param {Object} config
	 * @return {OO.ui.FieldLayout}
	 */
	WelcomeSurveyDialog.prototype.buildInfoWidget = function ( config ) {
		var label = new OO.ui.LabelWidget( {
			label: mw.msg( config[ 'label-message' ] )
		} );

		return new OO.ui.FieldLayout(
			label,
			{ align: 'top' }
		);
	};

	/**
	 * Build a field with a checkbox based on the question config.
	 *
	 * @param {Object} config
	 * @return {OO.ui.FieldLayout}
	 */
	WelcomeSurveyDialog.prototype.buildCheckWidget = function ( config ) {
		var checkboxWidget = new OO.ui.CheckboxInputWidget();
		this.registerResponse( config.name, function () {
			return checkboxWidget.isSelected();
		} );
		return new OO.ui.FieldLayout(
			checkboxWidget,
			{
				label: mw.msg( config[ 'label-message' ] ),
				align: 'inline'
			}
		);
	};

	/**
	 * called when the current panel of the stack layout changes.
	 *
	 * Toggle the visible of the subtitle. It should only be shown for the
	 * first question.
	 *
	 * Change the mode between 'default' and 'last-question' to toggle
	 * the visibility of the dialog actions.
	 */
	WelcomeSurveyDialog.prototype.onQuestionChange = function () {
		var items = this.questionsLayout.getItems(),
			current = this.questionsLayout.getCurrentItem(),
			index = items.indexOf( current ),
			isFirst = index === 0,
			isLast = ( index + 1 ) === items.length;

		this.$subtitle.toggle( isFirst );
		this.getActions().setMode( isLast ? 'last-question' : 'default' );

		this.mainPanel.$element.scrollTop( 0 );
	};

	/**
	 * @inheritdoc
	 */
	WelcomeSurveyDialog.prototype.getSetupProcess = function ( data ) {
		return WelcomeSurveyDialog.parent.prototype.getSetupProcess.call( this, data )
			.next( function () {
				this.actions.setMode( 'default' );
			}, this );
	};

	WelcomeSurveyDialog.prototype.closeWindow = function ( action ) {
		return this.getManager().closeWindow( this, action ).closed.promise();
	};

	/**
	 * @inheritdoc
	 */
	WelcomeSurveyDialog.prototype.getActionProcess = function ( action ) {
		if ( action === 'save' ) {
			return new OO.ui.Process()
				.next( this.handleResponses.bind( this, action ) )
				.next( this.closeWindow.bind( this, action ) )
				.next( this.showConfirmation.bind( this ) );
		}

		if ( action === 'skip' ) {
			return new OO.ui.Process(
				function () {
					// Notify the API about the skip but close the window immediately
					this.handleResponses( action );
					this.closeWindow( action );
				},
				this
			);
		}

		return WelcomeSurveyDialog.parent.prototype.getActionProcess.call( this, action );
	};

	/**
	 * Handle the 'save' and 'skip' actions.
	 * Send the survey responses to the API if appropriate.
	 *
	 * @param {string} action Name of the current action
	 * @return {jQuery.Promise}
	 */
	WelcomeSurveyDialog.prototype.handleResponses = function ( action ) {
		var data,
			responses = '';

		if ( action === 'save' ) {
			data = {};
			Object.keys( this.responses ).forEach( function ( name ) {
				data[ name ] = this.responses[ name ]();
			}.bind( this ) );
			responses = JSON.stringify( data );
		}

		return new mw.Api().postWithToken(
			'csrf',
			{
				action: 'welcomesurveyhandleresponses',
				surveyaction: action,
				group: this.group,
				rendertimestamp: this.renderTimestamp,
				responses: responses
			}
		);
	};

	/**
	 * Show the survey confirmation dialog.
	 */
	WelcomeSurveyDialog.prototype.showConfirmation = function () {
		this.getManager().openWindow( 'WelcomeSurveyConfirmationDialog' );
	};

	/**
	 * Register a survey question and the function that provides the response.
	 *
	 * @param {string} questionName
	 * @param {Function} responseFunction
	 */
	WelcomeSurveyDialog.prototype.registerResponse = function ( questionName, responseFunction ) {
		this.responses[ questionName ] = responseFunction;
	};

	OO.setProp(
		mw, 'libs', 'ge', 'WelcomeSurvey', 'WelcomeSurveyDialog',
		WelcomeSurveyDialog
	);

}() );
