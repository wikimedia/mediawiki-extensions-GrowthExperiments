<?php

namespace GrowthExperiments\Specials;

use FormatJson;
use \FormSpecialPage;
use GrowthExperiments\Html\HTMLMultiSelectFieldAllowArbitrary;
use Html;
use HTMLForm;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Logger\LoggerFactory;
use MWTimestamp;
use Sanitizer;
use Status;
use Title;

class SpecialWelcomeSurvey extends FormSpecialPage {

	const BLOB_SIZE = 65535;

	/**
	 * @var string
	 */
	private $experimentName;

	/**
	 * Bank of questions that can be used on the Welcome survey.
	 * Format is HTMLForm configuration.
	 * @var array
	 */
	private $questions = [
		"reason" => [
			"type" => "select",
			"label-message" => "welcomesurvey-question-reason-label",
			"options-messages" => [
				"welcomesurvey-dropdown-option-select-label" => "select",
				"welcomesurvey-question-reason-option-edit-typo-label" => "edit-typo",
				"welcomesurvey-question-reason-option-edit-info-label" => "edit-info",
				"welcomesurvey-question-reason-option-new-page-label" => "new-page",
				"welcomesurvey-question-reason-option-read-label" => "read",
				"welcomesurvey-question-reason-option-other-label" => "other",
			],
			"name" => "reason",
		],
		"reason-other" => [
			"type" => "text",
			"placeholder-message" => "welcomesurvey-question-reason-other-placeholder",
			"size" => 255,
			"hide-if" => [
				"!==",
				"reason",
				"other",
			],
		],
		"edited" => [
			"type" => "select",
			"label-message" => "welcomesurvey-question-edited-label",
			"options-messages" => [
				"welcomesurvey-dropdown-option-select-label" => "select",
				"welcomesurvey-question-edited-option-yes-many-label" => "yes-many",
				"welcomesurvey-question-edited-option-yes-few-label" => "yes-few",
				"welcomesurvey-question-edited-option-no-dunno-label" => "dunno",
				"welcomesurvey-question-edited-option-no-other-label" => "no-other",
				"welcomesurvey-question-edited-option-dont-remember-label" => "dont-remember",
			],
		],
		"topics" => [
			"type" => "multiselect",
			"label-message" => "welcomesurvey-question-topics-label",
			"flatlist" => true,
			"options-messages" => [
				"welcomesurvey-question-topics-option-arts" => "arts",
				"welcomesurvey-question-topics-option-science" => "science",
				"welcomesurvey-question-topics-option-geography" => "geography",
				"welcomesurvey-question-topics-option-history" => "history",
				"welcomesurvey-question-topics-option-music" => "music",
				"welcomesurvey-question-topics-option-sports" => "sports",
				"welcomesurvey-question-topics-option-literature" => "literature",
			],
		],
		"topics-other-js" => [
			"class" => HTMLMultiSelectFieldAllowArbitrary::class,
			"placeholder-message" => "welcomesurvey-question-topics-other-placeholder",
			"options-messages" => [
				"welcomesurvey-question-topics-option-entertainment" => "entertainment",
				"welcomesurvey-question-topics-option-food-drink" => "food and drink",
				"welcomesurvey-question-topics-option-biography" => "biography",
				"welcomesurvey-question-topics-option-military" => "military",
				"welcomesurvey-question-topics-option-economics" => "economics",
				"welcomesurvey-question-topics-option-technology" => "technology",
				"welcomesurvey-question-topics-option-film" => "film",
				"welcomesurvey-question-topics-option-philosophy" => "philosophy",
				"welcomesurvey-question-topics-option-business" => "business",
				"welcomesurvey-question-topics-option-politics" => "politics",
				"welcomesurvey-question-topics-option-government" => "government",
				"welcomesurvey-question-topics-option-engineering" => "engineering",
				"welcomesurvey-question-topics-option-crafts-hobbies" => "crafts and hobbies",
				"welcomesurvey-question-topics-option-games" => "games",
				"welcomesurvey-question-topics-option-health" => "health",
				"welcomesurvey-question-topics-option-social-science" => "social science",
				"welcomesurvey-question-topics-option-transportation" => "transportation",
				"welcomesurvey-question-topics-option-education" => "education",
			],
			"cssclass" => "custom-dropdown js-only",
		],
		"topics-other-nojs" => [
			"type" => "text",
			"placeholder-message" => "welcomesurvey-question-topics-other-placeholder",
			"cssclass" => "nojs-only",
		],
		"mentor-info" => [
			"type" => "info",
			"label-message" => "welcomesurvey-question-mentor-info",
			"cssclass" => "welcomesurvey-mentor-info",
		],
		"mentor" => [
			"type" => "check",
			"label-message" => "welcomesurvey-question-mentor-label",
			"cssclass" => "welcomesurvey-mentor-check",
		],
		"email" => [
			"type" => "email",
			"label-message" => "welcomesurvey-question-email-label",
			"placeholder-message" => "welcomesurvey-question-email-placeholder",
			"help-message" => "welcomesurvey-question-email-help",
		],
	];

	public function __construct() {
		parent::__construct( 'WelcomeSurvey', '', false );
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	private function getPrivacyPolicyUrl() {
		return $this->getConfig()->get( 'WelcomeSurveyPrivacyPolicyUrl' );
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
		$experiments = $this->getConfig()->get( 'WelcomeSurveyExperiments' );

		$request = $this->getRequest();
		$this->experimentName = $request->getVal( 'experiment' );
		$questionNames = [];

		if ( isset( $experiments[ $this->experimentName ] ) ) {
			$questionNames = $experiments[ $this->experimentName ][ 'questions' ];
		} elseif ( $this->experimentName === 'all' ) {
			$questionNames = array_keys( $this->questions );
		} else {
			$userGroupId = substr( $this->getUser()->getId(), -1 );
			foreach ( $experiments as $name => $experiment ) {
				$groups = explode( '-', $experiment[ 'groups' ] );
				if (
					( count( $groups ) === 1 && $groups[0] === $userGroupId ) ||
					( count( $groups ) === 2 && $groups[0] <= $userGroupId && $groups[1] >= $userGroupId )
				) {
					$this->experimentName = $name;
					$questionNames = $experiment[ 'questions' ];
					break;
				}
			}
		}

		if ( !$questionNames ) {
			// redirect away
			$this->redirect(
				$request->getVal( 'returnto' ),
				$request->getVal( 'returntoquery' )
			);
			return [];
		}

		$fields = [];
		foreach ( $questionNames as $questionName ) {
			$fieldConfig = $this->questions[ $questionName ];

			// skip 'email' if it can't be set for any reason
			if ( $questionName === 'email' && !$this->canSetEmail() ) {
				continue;
			}

			$fields[ $questionName ] = $fieldConfig;
		}

		return $fields;
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
		$form->addHiddenField( '_experiment', $this->experimentName );

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
		$user = $this->getUser();
		$submitDate = MWTimestamp::now();
		$userUpdated = false;

		if ( $request->getVal( 'save' ) ) {
			// set email
			$newEmail = $data[ 'email' ] ?? false;
			if ( $newEmail ) {
				$data[ 'email' ] = '[redacted]';
				if ( $this->canSetEmail( $newEmail ) ) {
					$user->setEmailWithConfirmation( $newEmail );
					$userUpdated = true;
				}
			}

			$results = $data;
		} else {
			$results = [ '_skip' => true ];
		}

		$counter = ( FormatJson::decode(
			$user->getOption( 'welcomesurvey-responses', '' )
		)->_counter ?? 0 ) + 1;

		$results = array_merge(
			$results,
			[
				'_experiment' => $request->getVal( '_experiment' ),
				'_render_date' => $request->getVal( '_render_date' ),
				'_submit_date' => $submitDate,
				'_counter' => $counter,
			]
		);
		$encodedData = FormatJson::encode( $results );
		if ( strlen( $encodedData ) <= self::BLOB_SIZE ) {
			$user->setOption( 'welcomesurvey-responses', $encodedData );
			$userUpdated = true;
		} else {
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				'Unable to save Welcome survey responses for user {userId} because it is too big.',
				[ 'userId' => $user->getId() ]
			);
		}
		if ( $userUpdated ) {
			$user->saveSettings();
		}

		$redirectParams = wfCgiToArray( $request->getVal( 'redirectparams', '' ) );
		$returnTo = $redirectParams[ 'returnto' ] ?? '';
		$returnToQuery = $redirectParams[ 'returntoquery' ] ?? '';

		if ( $request->getVal( 'save' ) ) {
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
				$this->msg(
					'welcomesurvey-save-confirmation-text',
					$this->getPrivacyPolicyUrl()
				)->parseAsBlock() .
				Html::element(
					'div',
					[ 'class' => 'welcomesurvey-confirmation-editing-title' ],
					$this->msg( 'welcomesurvey-sidebar-editing-title' )->text()
				) .
				$this->msg( 'welcomesurvey-sidebar-editing-text' )->parseAsBlock() .
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

	private function canSetEmail( $newEmail = null ) {
		$user = $this->getUser();
		return !$user->getEmail() &&
			$user->isAllowed( 'viewmyprivateinfo' ) &&
			$user->isAllowed( 'editmyprivateinfo' ) &&
			AuthManager::singleton()->allowsPropertyChange( 'emailaddress' ) &&
			( $newEmail ? Sanitizer::validateEmail( $newEmail ) : true );
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
					// todo: convert contained link to "new window" with target and icon
					$this->msg(
						'welcomesurvey-sidebar-privacy-text',
						$this->getPrivacyPolicyUrl()
					)->parseAsBlock()
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
					// todo: convert contained links to "new window" with target and icon
					$this->msg( 'welcomesurvey-sidebar-editing-text' )->parseAsBlock()
				)
			)
		);
	}

}
