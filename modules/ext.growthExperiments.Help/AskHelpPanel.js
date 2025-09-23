/**
 * AskHelp panel for question-asking functionality of the help panel and the mentorship module
 *
 * @class mw.libs.ge.AskHelpPanel
 * @extends OO.ui.PanelLayout
 *
 * @param {Object} config
 * @param {string} config.askSource Logical name of the source to use with the
 * HelpPanelPostQuestion API (mentor-homepage, mentor-helppanel or helpdesk)
 * @param {mw.libs.ge.HelpPanelLogger} config.logger Logger to be used with the diao
 * @param {mw.Title|null} config.relevantTitle
 * @constructor
 */
function AskHelpPanel( config ) {
	AskHelpPanel.super.call( this, {
		padded: true,
		expanded: true,
	} );
	this.askSource = config.askSource ||
		( mw.config.get( 'wgGEHelpPanelAskMentor' ) ? 'mentor-helppanel' : 'helpdesk' );
	this.relevantTitle = config.relevantTitle;
	this.logger = config.logger;
	this.panelTitleMessages = {};
	this.initializePanelProperties();
	this.buildContent();
}

OO.inheritClass( AskHelpPanel, OO.ui.PanelLayout );

/**
 * Build the panel content
 */
AskHelpPanel.prototype.buildContent = function () {
	const content = new OO.ui.FieldsetLayout();

	this.askhelpTextInput = new OO.ui.MultilineTextInputWidget( {
		placeholder: mw.message( 'growthexperiments-help-panel-question-placeholder' )
			.params( [ mw.user ] )
			.text(),
		multiline: true,
		maxLength: 2000,
		rows: 6,
		value: mw.storage.get( this.storageKey ),
		spellcheck: true,
		required: true,
		autofocus: !OO.ui.isMobile(),
	} ).connect( this, { change: 'onTextInputChange' } );

	this.questionIncludeTitleCheckbox = new OO.ui.CheckboxInputWidget( {
		value: 1,
		selected: this.questionPosterAllowIncludingTitle,
	} );

	// Checkbox for whether to include page with question
	this.questionIncludeFieldLayout = new OO.ui.FieldLayout(
		this.questionIncludeTitleCheckbox, {
			label: mw.message(
				'growthexperiments-help-panel-questionreview-include-article-title',
			).text(),
			align: 'inline',
			// The page title is shown via FieldLayout's help text.
			helpInline: true,
			help: this.relevantTitle ? this.relevantTitle.getPrefixedText() : '',
		},
	);

	content.addItems( [
		new OO.ui.LabelWidget( {
			label: this.$askhelpHeader,
		} ),
		new OO.ui.FieldLayout(
			this.askhelpTextInput, {
				label: $( '<strong>' ).text(
					mw.message( 'growthexperiments-help-panel-questionreview-label' ).text(),
				),
				align: 'top',
			} ),
		this.questionIncludeFieldLayout,
	] );

	this.$element
		.addClass( [
			'mw-ge-askHelpPanel',
			OO.ui.isMobile() ?
				'mw-ge-askHelpPanel-mobile' :
				'mw-ge-askHelpPanel-desktop',
		] )
		.append( [ content.$element, this.getCopyrightWarning() ] );
};

/**
 * Initialize properties when the panel's question-asking functionality posts the question
 * to the help desk page (see GEHelpPanelAskMentor)
 */
AskHelpPanel.prototype.initializeHelpDeskProperties = function () {
	const configData = require( './data.json' ),
		linksConfig = configData.GEHelpPanelLinks;

	this.panelTitleMessages[ 'ask-help' ] =
		mw.message( 'growthexperiments-help-panel-questionreview-title' ).text();
	this.$askhelpHeader = $( '<p>' ).append(
		mw.message( 'growthexperiments-help-panel-questionreview-header',
			$( linksConfig.helpDeskLink ), mw.user.getName() ).parse(),
	);
	this.questionCompleteConfirmationText =
		mw.message( 'growthexperiments-help-panel-questioncomplete-confirmation-text' ).text();
	this.viewQuestionText =
		mw.message( 'growthexperiments-help-panel-questioncomplete-view-link-text' ).text();
	this.submitFailureMessage = mw.message(
		'growthexperiments-help-panel-question-post-error', linksConfig.helpDeskLink ).parse();
};

/**
 * Initialize properties when the panel's question-asking functionality posts the question
 * to the mentor's talk page (see GEHelpPanelAskMentor)
 */
AskHelpPanel.prototype.initializeMentorProperties = function () {
	const userName = mw.user.getName();

	let mentorName, mentorGender, primaryMentorName, primaryMentorGender, backAt;
	if ( this.askSource === 'mentor-homepage' ) {
		mentorName = mw.config.get( 'GEHomepageMentorshipEffectiveMentorName' );
		mentorGender = mw.config.get( 'GEHomepageMentorshipEffectiveMentorGender' );
		primaryMentorName = mw.config.get( 'GEHomepageMentorshipMentorName' );
		primaryMentorGender = mw.config.get( 'GEHomepageMentorshipMentorGender' );
		backAt = mw.config.get( 'GEHomepageMentorshipBackAt' );
	} else {
		// mentor-helppanel
		const mentorData = mw.config.get( 'wgGEHelpPanelMentorData' );
		mentorName = mentorData.effectiveName;
		mentorGender = mentorData.effectiveGender;
		primaryMentorName = mentorData.name;
		primaryMentorGender = mentorData.gender;
		backAt = mentorData.backAt;
	}

	this.panelTitleMessages[ 'ask-help' ] =
		mw.message(
			'growthexperiments-homepage-mentorship-dialog-title', mentorName, userName,
		).text();
	this.panelTitleMessages.questioncomplete =
		mw.message( 'growthexperiments-help-panel-questioncomplete-title' ).text();

	const mentorTalkLinkText = mw.message(
		'growthexperiments-homepage-mentorship-questionreview-header-mentor-talk-link-text',
		mentorName, userName ).text();
	const $mentorTalkLink = $( '<a>' )
		.attr( {
			href: mw.Title.newFromText( mentorName, 3 ).getUrl(),
			target: '_blank',
			'data-link-id': 'mentor-talk',
		} )
		.text( mentorTalkLinkText );
	this.$askhelpHeader = $( '<div>' );
	if ( mentorName !== primaryMentorName ) {
		// effective mentor name is not same as primary => primary mentor must be away
		if ( backAt ) {
			this.$askhelpHeader.append(
				$( '<p>' ).append(
					$( '<strong>' ).append(
						mw.message(
							'growthexperiments-homepage-mentorship-questionreview-header-away',
							primaryMentorName, primaryMentorGender, backAt,
						).parse(),
					),
				),
			);
		} else {
			this.$askhelpHeader.append(
				$( '<p>' ).append(
					$( '<strong>' ).append(
						mw.message(
							'growthexperiments-homepage-mentorship-questionreview-header-away-no-timestamp',
							primaryMentorName, primaryMentorGender,
						).parse(),
					),
				),
			);
		}
		this.$askhelpHeader.append(
			$( '<p>' ).append(
				mw.message(
					'growthexperiments-homepage-mentorship-questionreview-header-away-another-mentor',
					mentorName, mentorGender,
				).parse(),
			),
		);
	}
	this.$askhelpHeader.append( $( '<p>' ).append(
		mw.message( 'growthexperiments-homepage-mentorship-questionreview-header',
			mentorName, userName, $mentorTalkLink ).parse(),
	) );
	this.questionCompleteConfirmationText = mw.message(
		'growthexperiments-homepage-mentorship-confirmation-text', mentorName, userName,
	).text();
	this.viewQuestionText = mw.message(
		'growthexperiments-homepage-mentorship-view-question-text', mentorName, userName,
	).text();
	this.submitFailureMessage = mw.message( 'growthexperiments-help-panel-question-post-error',
		$mentorTalkLink ).parse();
};

/**
 * Initialize properties based on this.askSource
 */
AskHelpPanel.prototype.initializePanelProperties = function () {
	const askFromMentor = this.askSource !== 'helpdesk',
		configData = require( './data.json' );
	this.storageKey = askFromMentor ?
		'homepage-questionposter-question-text-mentorship' :
		'help-panel-question-text';
	this.previousQuestionText = mw.storage.get( this.storageKey );
	// Do not post article title when asking from the homepage.
	this.questionPosterAllowIncludingTitle = this.askSource !== 'mentor-homepage';
	this.copyrightWarningHtml = configData.GEAskHelpCopyrightWarning;

	if ( this.askSource === 'helpdesk' ) {
		this.initializeHelpDeskProperties();
	} else {
		this.initializeMentorProperties();
	}
	this.panelTitleMessages.questioncomplete = this.panelTitleMessages[ 'ask-help' ];
};

/**
 * Get the text to be shown in HelpPanelProcessDialog's title.
 * The text is determined by this.askSource.
 *
 * @return {Object}
 */
AskHelpPanel.prototype.getPanelTitleMessages = function () {
	return this.panelTitleMessages;
};

/**
 * Store the entered question and update HelpPanelProcessDialog state when the question changes
 *
 * @fires AskHelpPanel#askHelpTextInputChange
 */
AskHelpPanel.prototype.onTextInputChange = function () {
	const reviewTextInputValue = this.askhelpTextInput.getValue();
	// Log when the user first enters a question
	if ( !this.previousQuestionText && reviewTextInputValue.length ) {
		this.logger.log( 'enter-question-text' );
	}
	this.previousQuestionText = reviewTextInputValue;
	this.emit( 'askHelpTextInputChange', this.askhelpTextInput.getValue() );
	// Save the draft text in local storage in case the user reloads their page.
	mw.storage.set( this.storageKey, reviewTextInputValue );
};

/**
 * Get the text to be shown when the question has been posted
 *
 * @return {string}
 */
AskHelpPanel.prototype.getQuestionCompleteConfirmationLabel = function () {
	return this.questionCompleteConfirmationText;
};

/**
 * Update the panel and HelpPanelProcessDialog states when the panel is switched to
 *
 * @fires AskHelpPanel#askHelpTextInputChange
 */
AskHelpPanel.prototype.prepareToShowPanel = function () {
	this.emit( 'askHelpTextInputChange', this.askhelpTextInput.getValue() );
	this.questionIncludeFieldLayout.toggle( this.questionPosterAllowIncludingTitle );
};

/**
 * Clear the entered question when it's posted.
 * Called from HelpPanelProcessDialog.getActionProcess
 */
AskHelpPanel.prototype.onQuestionPosted = function () {
	this.askhelpTextInput.setValue( '' );
	this.previousQuestionText = '';
	mw.storage.set( this.storageKey, '' );
};

/**
 * Get the data to be submitted to helppanelquestionposter API
 *
 * @return {Object}
 */
AskHelpPanel.prototype.getPostData = function () {
	return {
		source: this.askSource,
		action: 'helppanelquestionposter',
		relevanttitle: this.questionIncludeTitleCheckbox.isSelected() ?
			this.relevantTitle.getPrefixedText() :
			'',
		body: this.askhelpTextInput.getValue(),
	};
};

/**
 * Get the value of the question the user entered
 *
 * @return {string}
 */
AskHelpPanel.prototype.getQuestion = function () {
	return this.askhelpTextInput.getValue();
};

/**
 * Check whether the article title is included with the question when the question is posted
 * (when this.questionPosterAllowIncludingTitle is true).
 *
 * @return {boolean}
 */
AskHelpPanel.prototype.isTitleIncludedInQuestion = function () {
	return this.questionIncludeTitleCheckbox.isSelected();
};

/**
 * Get the text to be shown when the attempt to post the question fails
 *
 * @return {string}
 */
AskHelpPanel.prototype.getSubmitFailureMessage = function () {
	return this.submitFailureMessage;
};

/**
 * Get the text to be shown when the question has been posted
 *
 * @return {string}
 */
AskHelpPanel.prototype.getViewQuestionText = function () {
	return this.viewQuestionText;
};

/**
 * Get the copyright warning element
 *
 * @return {jQuery|undefined}
 */
AskHelpPanel.prototype.getCopyrightWarning = function () {
	if ( !this.copyrightWarningHtml ) {
		return;
	}
	return $( '<div>' )
		.addClass( [ 'mw-ge-askHelpPanel-copyright' ] )
		.append( this.copyrightWarningHtml );
};

/**
 * Check whether helppanel tour should be shown upon submitting question
 *
 * @return {boolean}
 */
AskHelpPanel.prototype.shouldShowHelpPanelTour = function () {
	return this.askSource === 'helpdesk' &&
		!mw.user.options.get( 'growthexperiments-tour-help-panel' );
};

/**
 * Check whether homepage_mentor tour should be shown upon submitting question
 *
 * @return {boolean}
 */
AskHelpPanel.prototype.shouldShowHomepageMentorTour = function () {
	return this.askSource === 'mentor-homepage';
};

module.exports = AskHelpPanel;
