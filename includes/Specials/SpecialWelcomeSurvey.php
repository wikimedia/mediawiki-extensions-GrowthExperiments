<?php

namespace GrowthExperiments\Specials;

use FormSpecialPage;
use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Util;
use GrowthExperiments\WelcomeSurveyFactory;
use Html;
use HTMLForm;
use MWTimestamp;
use Status;
use Title;

class SpecialWelcomeSurvey extends FormSpecialPage {

	public const ACTION_VIEW = 'view';
	public const ACTION_SUBMIT_ATTEMPT = 'submit_attempt';
	public const ACTION_SAVE = 'save';
	public const ACTION_SKIP = 'skip';
	public const ACTION_SUBMIT_SUCCESS = 'submit_success';
	public const ACTION_SHOW_CONFIRMATION_PAGE = 'show_confirmation_page';

	/** @var string */
	private $groupName;

	/** @var WelcomeSurveyFactory */
	private $welcomeSurveyFactory;

	/** @var WelcomeSurveyLogger */
	private $welcomeSurveyLogger;

	/**
	 * @param WelcomeSurveyFactory $welcomeSurveyFactory
	 * @param WelcomeSurveyLogger $welcomeSurveyLogger
	 */
	public function __construct(
		WelcomeSurveyFactory $welcomeSurveyFactory, WelcomeSurveyLogger $welcomeSurveyLogger
	) {
		parent::__construct( 'WelcomeSurvey', '', false );
		$this->welcomeSurveyFactory = $welcomeSurveyFactory;
		$this->welcomeSurveyLogger = $welcomeSurveyLogger;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	private function buildPrivacyPolicyLink() {
		$text = $this->msg( 'welcomesurvey-privacy-policy-link-text' )->text();
		$url = $this->getConfig()->get( 'WelcomeSurveyPrivacyPolicyUrl' );
		return Html::rawElement(
			'span',
			[ 'class' => 'mw-parser-output' ],
			Html::element(
				'a',
				[
					'href' => $url,
					'target' => '_blank',
					'class' => 'external',
				],
				$text
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->initializeWelcomeSurveyLogger();
		if ( !$par && !$this->getRequest()->wasPosted() ) {
			$this->welcomeSurveyLogger->logInteraction( self::ACTION_VIEW );
		}
		if ( !$par && $this->getRequest()->wasPosted() ) {
			$this->welcomeSurveyLogger->logInteraction( self::ACTION_SUBMIT_ATTEMPT );
		}
		$this->requireLogin();
		$this->getOutput()->addModuleStyles( 'ext.growthExperiments.WelcomeSurvey.styles' );
		parent::execute( $par );
	}

	/**
	 * Overridden in order to inject the current user's name as message parameter
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( strtolower( $this->mName ) )
			->params( $this->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * Get an HTMLForm descriptor array
	 * @return array
	 */
	protected function getFormFields() {
		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $this->getContext() );
		$this->groupName = $welcomeSurvey->getGroup( true );
		$questions = $welcomeSurvey->getQuestions( $this->groupName );

		if ( !$questions ) {
			// redirect away
			$request = $this->getRequest();
			$this->redirect(
				$request->getVal( 'returnto' ),
				$request->getVal( 'returntoquery' )
			);
			return [];
		}

		// Transform questions
		foreach ( $questions as &$question ) {
			// Add select options for 'placeholder' and 'other'
			if ( $question[ 'type' ] === 'select' ) {
				if ( isset( $question[ 'placeholder-message' ] ) ) {
					// Add 'placeholder' as the first options
					$question['options-messages'] = [ $question['placeholder-message'] => 'placeholder' ] +
						$question['options-messages'];
				}
				if ( isset( $question[ 'other-message' ] ) ) {
					// Add 'other' as the last options
					$question['options-messages'] += [ $question[ 'other-message' ] => 'other' ];
				}
			}
		}
		$this->loadDependencies( $questions );
		return $questions;
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setId( 'welcome-survey-form' );

		// subtitle
		$form->addHeaderText(
			Html::rawElement(
				'div',
				[ 'class' => 'welcomesurvey-subtitle' ],
				$this->msg( 'welcomesurvey-subtitle' )->parse()
			)
		);

		$form->addHiddenField( '_render_date', MWTimestamp::now() );
		$form->addHiddenField( '_group', $this->groupName );
		$form->addHiddenField( '_returnto', $this->getRequest()->getVal( 'returnto' ) );
		$form->addHiddenField( '_welcomesurveytoken', $this->getRequest()->getVal( '_welcomesurveytoken' ) );

		// save button
		$form->setSubmitTextMsg( 'welcomesurvey-save-btn' );
		$form->setSubmitName( 'save' );

		// skip button
		$form->addButton( [
			'name' => 'skip',
			'value' => 'skip',
			'framed' => false,
			'flags' => 'destructive',
			'attribs' => [ 'class' => 'welcomesurvey-skip-btn' ],
			'label-message' => [ 'welcomesurvey-skip-btn', $this->getUser()->getName() ],
		] );

		// sidebar
		$form->setPostText( $this->buildSidebar() );
	}

	/**
	 * Process the form on POST submission.
	 * @param array $data
	 * @return bool|string|array|Status As documented for HTMLForm::trySubmit.
	 */
	public function onSubmit( array $data ) {
		$this->initializeWelcomeSurveyLogger();

		$request = $this->getRequest();
		$save = $request->getBool( 'save' );
		$group = $request->getVal( '_group' );
		$token = $request->getVal( '_welcomesurveytoken' );
		$renderDate = $request->getVal( '_render_date' );
		$redirectParams = wfCgiToArray( $request->getVal( 'redirectparams', '' ) );
		$returnTo = $redirectParams[ 'returnto' ] ?? $request->getVal( '_returnto', '' );
		$returnToQuery = $redirectParams[ 'returntoquery' ] ?? '';

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $this->getContext() );
		$welcomeSurvey->handleResponses(
			$data,
			$save,
			$group,
			$renderDate
		);

		$this->welcomeSurveyLogger->logInteraction( self::ACTION_SUBMIT_SUCCESS );

		if ( $save ) {
			// show confirmation page
			$returnToQueryArray = wfCgiToArray( $returnToQuery );
			$returnToQueryArray['_welcomesurveytoken'] = $token;
			$returnToQuery = wfArrayToCgi( $returnToQueryArray );
			$this->welcomeSurveyLogger->logInteraction( self::ACTION_SAVE );
			$this->showConfirmationPage( $returnTo, $returnToQuery );
		} else {
			// redirect to pre-createaccount page with query
			$returnToQueryArray = wfCgiToArray( $returnToQuery );
			$returnToQueryArray['_welcomesurveytoken'] = $token;
			if ( HomepageHooks::isHomepageEnabled( $this->getUser() ) ) {
				$returnToQueryArray['source'] = 'welcomesurvey-originalcontext';
			}
			$returnToQuery = wfArrayToCgi( $returnToQueryArray );
			$this->welcomeSurveyLogger->logInteraction( self::ACTION_SKIP );
			$this->redirect( $returnTo, $returnToQuery );
		}

		return true;
	}

	private function showConfirmationPage( $to, $query ) {
		$this->getOutput()->setPageTitle( $this->msg( 'welcomesurvey-save-confirmation-title' ) );
		HomepageHooks::isHomepageEnabled( $this->getUser() ) ?
			$this->showHomepageAwareConfirmationPage( $to, $query ) :
			$this->showDefaultConfirmationPage( $to, $query );
		$this->welcomeSurveyLogger->logInteraction( self::ACTION_SHOW_CONFIRMATION_PAGE );
	}

	private function showHomepageAwareConfirmationPage( $to, $query ) {
		$title = Title::newFromText( $to ) ?: \SpecialPage::getTitleFor( 'Homepage' );
		if ( $title->isMainPage() ) {
			$title = \SpecialPage::getTitleFor( 'Homepage' );
		}

		$this->getOutput()->addHTML(
			Html::rawElement(
				'div',
				[ 'class' => 'welcomesurvey-confirmation' ],
				$this->msg( 'welcomesurvey-save-confirmation-text' )
					->rawParams( $this->buildPrivacyPolicyLink() )
					->parseAsBlock() .
				$this->getHomepageAwareActionButtons( $title, $query )
			)
		);
	}

	private function showDefaultConfirmationPage( $to, $query ) {
		$this->getOutput()->addHTML(
			Html::rawElement(
				'div',
				[ 'class' => 'welcomesurvey-confirmation' ],
				$this->msg( 'welcomesurvey-save-confirmation-text' )
					->rawParams( $this->buildPrivacyPolicyLink() )
					->parseAsBlock() .
				Html::element(
					'div',
					[ 'class' => 'welcomesurvey-confirmation-editing-title' ],
					$this->msg( 'welcomesurvey-sidebar-editing-title' )->text()
				) .
				$this->msg( 'welcomesurvey-sidebar-editing-text' )
					->params( $this->getUser()->getName() )
					->parseAsBlock() .
				$this->buildGettingStartedLinks( 'confirmation' ) .
				$this->getCloseButtonHtml( Title::newFromText( $to ) ?: Title::newMainPage(), $query )
			)
		);
	}

	/**
	 * @param Title $title
	 * @param string $query
	 * @return string
	 * @throws \ConfigException
	 */
	private function getCloseButtonHtml( Title $title, $query ) {
		return $this->getConfirmationButtonsWrapper(
			Html::linkButton(
				$this->msg( 'welcomesurvey-close-btn', $title->getPrefixedText() )->text(),
				[
					'href' => $title->getLinkURL( $query ),
					'class' => 'mw-ui-button mw-ui-progressive'
				]
			)
		);
	}

	private function getConfirmationButtonsWrapper( $rawHtml ) {
		return Html::rawElement(
			'div',
			[ 'class' => 'welcomesurvey-confirmation-buttons' ],
			$rawHtml
		);
	}

	private function getHomepageAwareActionButtons( Title $title, $query ) {
		if ( $title->isSpecial( 'Homepage' ) ) {
			return $this->getConfirmationButtonsWrapper( $this->getHomepageButton() );
		}
		$queryArray = wfCgiToArray( $query );
		$queryArray['source'] = 'welcomesurvey-originalcontext';
		return $this->getConfirmationButtonsWrapper(
			Html::linkButton( $this->msg( 'welcomesurvey-close-btn', $title )->text(), [
				'href' => $title->getLinkURL( wfArrayToCgi( $queryArray ) ),
				'class' => 'mw-ui-button mw-ui-safe'
			] ) .
			$this->getHomepageButton()
		);
	}

	private function getHomepageButton() {
		return Html::linkButton(
			$this->msg( 'growthexperiments-homepage-welcomesurvey-default-close',
				$this->getUser()->getName()
			)->text(),
			[
				'href' => \SpecialPage::getTitleFor( 'Homepage' )->getLinkURL(
					[ 'source' => 'specialwelcomesurvey' ]
				),
				'class' => 'mw-ui-button mw-ui-progressive mw-ge-welcomesurvey-homepage-button'
			]
		);
	}

	private function redirect( $to, $query ) {
		$title = Title::newFromText( $to ) ?: Title::newMainPage();
		$this->getOutput()->redirect( $title->getFullUrlForRedirect( $query ) );
	}

	private function buildGettingStartedLinks( $source ) {
		$html = '<ul class="welcomesurvey-gettingstarted-links">';
		for ( $i = 1; $i <= 4; $i++ ) {
			$text = $this->msg( "welcomesurvey-sidebar-editing-link$i-text" );
			$title = $this->msg( "welcomesurvey-sidebar-editing-link$i-title" );
			if ( $text->isDisabled() || $title->isDisabled() ) {
				continue;
			}

			$url = Title::newFromText( $title->text() )->getLinkURL( [ 'source' => $source ] );
			$html .= Html::rawElement(
				'li',
				[ 'class' => 'mw-parser-output' ],
				Html::element(
					'a',
					[
						'href' => $url,
						'target' => '_blank',
						'class' => 'external',
					],
					$text->text()
				)
			);
		}
		$html .= '</ul>';
		return $html;
	}

	private function buildSidebar() {
		return Html::rawElement(
			'div',
			[ 'class' => 'welcomesurvey-sidebar' ],
			Html::rawElement(
				'div',
				[ 'class' => 'welcomesurvey-sidebar-section' ],
				Html::element(
					'div',
					[ 'class' => 'welcomesurvey-sidebar-section-title' ],
					$this->msg( 'welcomesurvey-sidebar-privacy-title' )->text()
				) .
				Html::rawElement(
					'div',
					[ 'class' => 'welcomesurvey-sidebar-section-text' ],
					$this->msg( 'welcomesurvey-sidebar-privacy-text' )
						->rawParams( $this->buildPrivacyPolicyLink() )
						->params( $this->getUser()->getName() )
						->parseAsBlock()
				)
			) .
			Html::rawElement(
				'div',
				[ 'class' => 'welcomesurvey-sidebar-section' ],
				Html::element(
					'div',
					[ 'class' => 'welcomesurvey-sidebar-section-title' ],
					$this->msg( 'welcomesurvey-sidebar-editing-title' )->text()
				) .
				Html::rawElement(
					'div',
					[ 'class' => 'welcomesurvey-sidebar-section-text' ],
					$this->msg( 'welcomesurvey-sidebar-editing-text' )
						->params( $this->getUser()->getName() )
						->parseAsBlock()
				) .
				$this->buildGettingStartedLinks( 'survey' )
			)
		);
	}

	/**
	 * Load ResourceLoader module dependencies defined by questions.
	 * @param array $questions
	 */
	private function loadDependencies( array $questions ) {
		array_walk( $questions, function ( $question ) {
			$this->getOutput()->addModules( $question['dependencies']['modules'] ?? '' );
		} );
	}

	private function initializeWelcomeSurveyLogger(): void {
		$this->welcomeSurveyLogger->initialize(
			$this->getRequest(),
			$this->getUser(),
			Util::isMobile( $this->getSkin() )
		);
	}

}
