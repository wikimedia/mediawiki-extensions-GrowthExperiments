<?php

namespace GrowthExperiments\Specials;

use \FormSpecialPage;
use GrowthExperiments\Html\HTMLMultiSelectFieldAllowArbitrary;
use GrowthExperiments\WelcomeSurvey;
use Html;
use HTMLForm;
use MWTimestamp;
use Status;
use Title;

class SpecialWelcomeSurvey extends FormSpecialPage {

	/**
	 * @var string
	 */
	private $groupName;

	public function __construct() {
		parent::__construct( 'WelcomeSurvey', '', false );
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
		$this->requireLogin();
		$this->getOutput()->addModuleStyles( 'ext.growthExperiments.welcomeSurvey.styles' );
		$this->getOutput()->addModules( 'ext.growthExperiments.welcomeSurvey.scripts' );
		parent::execute( $par );
	}

	/**
	 * Overridden in order to inject the current user's name as message parameter
	 * and to show a different title for the confirmation page.
	 *
	 * @return string
	 */
	public function getDescription() {
		if ( $this->getRequest()->wasPosted() ) {
			return $this->msg( 'welcomesurvey-save-confirmation-title' )->text();
		} else {
			return $this->msg( strtolower( $this->mName ) )
				->params( $this->getUser()->getName() )
				->text();
		}
	}

	/**
	 * Get an HTMLForm descriptor array
	 * @return array
	 */
	protected function getFormFields() {
		$welcomeSurvey = new WelcomeSurvey( $this->getContext() );
		$this->groupName = $welcomeSurvey->getGroup();
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
		// The only transformation is multiselect with allowArbitrary
		foreach ( $questions as &$question ) {
			if (
				$question[ 'type' ] === 'multiselect' &&
				isset( $question[ 'allowArbitrary' ] ) &&
				$question[ 'allowArbitrary' ] === true
			) {
				unset( $question[ 'type' ] );
				$question[ 'class' ] = HTMLMultiSelectFieldAllowArbitrary::class;
			}
		}
		return $questions;
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
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

		// save button
		$form->setSubmitTextMsg( 'welcomesurvey-save-btn' );
		$form->setSubmitName( 'save' );

		// skip button
		$form->addButton( [
			'name' => 'skip',
			'value' => 'skip',
			'attribs' => [ 'class' => 'welcomesurvey-skip-btn' ],
			'label-message' => 'welcomesurvey-skip-btn',
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
		$request = $this->getRequest();
		$save = $request->getVal( 'save' );
		$redirectParams = wfCgiToArray( $request->getVal( 'redirectparams', '' ) );
		$returnTo = $redirectParams[ 'returnto' ] ?? '';
		$returnToQuery = $redirectParams[ 'returntoquery' ] ?? '';

		$welcomeSurvey = new WelcomeSurvey( $this->getContext() );
		$welcomeSurvey->handleResponses( $data, $save );

		if ( $save ) {
			// show confirmation page
			$this->showConfirmationPage( $returnTo, $returnToQuery );
		} else {
			// redirect to pre-createaccount page with query
			$this->redirect( $returnTo, $returnToQuery );
		}

		return true;
	}

	private function showConfirmationPage( $to, $query ) {
		$title = Title::newFromText( $to ) ?: Title::newMainPage();
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
				$this->msg( 'welcomesurvey-sidebar-editing-text' )->parseAsBlock() .
				$this->buildGettingStartedLinks( 'confirmation' ) .
				Html::rawElement(
					'div',
					[ 'class' => 'welcomesurvey-confirmation-buttons' ],
					Html::linkButton(
						$this->msg( 'welcomesurvey-close-btn', $title->getPrefixedText() )->text(),
						[
							'href' => $title->getLinkURL( $query ),
							'class' => 'mw-ui-button mw-ui-progressive'
						]
					)
				)
			)
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
					$this->msg( 'welcomesurvey-sidebar-editing-text' )->parseAsBlock()
				) .
				$this->buildGettingStartedLinks( 'survey' )
			)
		);
	}

}
