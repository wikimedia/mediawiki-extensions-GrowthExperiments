<?php

namespace GrowthExperiments;

use ExtensionRegistry;
use FormatJson;
use IContextSource;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Logger\LoggerFactory;
use MWTimestamp;
use SpecialPage;

class WelcomeSurvey {

	private const BLOB_SIZE = 65535;

	public const SURVEY_PROP = 'welcomesurvey-responses';

	/**
	 * @var IContextSource
	 */
	private $context;

	/**
	 * @var bool
	 */
	private $allowFreetext;
	/**
	 * @var LanguageNameUtils
	 */
	private $languageNameUtils;

	/**
	 * @param IContextSource $context
	 * @param LanguageNameUtils $languageNameUtils
	 */
	public function __construct( IContextSource $context, LanguageNameUtils $languageNameUtils ) {
		$this->context = $context;
		$this->allowFreetext =
			$this->context->getConfig()->get( 'WelcomeSurveyAllowFreetextResponses' );
		$this->languageNameUtils = $languageNameUtils;
	}

	/**
	 * Get the name of the experimental group for the current user or
	 * false they are not part of any experiment.
	 *
	 * @return bool|string
	 * @throws \ConfigException
	 */
	public function getGroup() {
		$groups = $this->context->getConfig()->get( 'WelcomeSurveyExperimentalGroups' );

		// The group is specified in the URL
		$request = $this->context->getRequest();
		// The parameter name ('_group') must match the name of the hidden form field in
		// SpecialWelcomeSurvey::alterForm()
		$groupParam = $request->getText( '_group' );
		if ( isset( $groups[ $groupParam ] ) ) {
			return $groupParam;
		}

		// The user was already assigned a group
		$groupFromProp = FormatJson::decode(
			$this->context->getUser()->getOption( self::SURVEY_PROP, '' )
		)->_group ?? false;
		if ( isset( $groups[ $groupFromProp ] ) ) {
			return $groupFromProp;
		}

		// Randomly selecting a group
		$js = $this->context->getRequest()->getBool( 'client-runs-javascript' );
		$rand = rand( 0, 9 );
		foreach ( $groups as $name => $groupConfig ) {
			$range = explode( '-', $groupConfig[ 'range' ] );
			if (
				( count( $range ) === 1 && $range[0] === $rand ) ||
				( count( $range ) === 2 && $range[0] <= $rand && $range[1] >= $rand )
			) {
				if ( !$js && isset( $groupConfig[ 'nojs-fallback' ] ) ) {
					return $groupConfig[ 'nojs-fallback' ];
				}
				return $name;
			}
		}

		return false;
	}

	/**
	 * Get the questions' configuration for the specified group
	 *
	 * @param string $group
	 * @param bool $asKeyedArray True to use the question name as key, false to use a numerical index
	 * @return array Questions configuration
	 * @throws \ConfigException
	 */
	public function getQuestions( $group, $asKeyedArray = true ) {
		$groups = $this->context->getConfig()->get( 'WelcomeSurveyExperimentalGroups' );
		if ( !isset( $groups[ $group ] ) ) {
			return [];
		}

		$questionNames = $groups[ $group ][ 'questions' ];
		if ( in_array( 'email', $questionNames ) &&
			!Util::canSetEmail( $this->context->getUser(), null, false )
		) {
			$questionNames = array_diff( $questionNames, [ 'email' ] );
		}

		if ( $this->allowFreetext && in_array( 'reason', $questionNames ) ) {
			// Insert reason-other after reason
			array_splice( $questionNames, array_search( 'reason', $questionNames ) + 1, 0,
				'reason-other' );
		}

		$questions = [];
		$questionBank = $this->getQuestionBank();
		foreach ( $questionNames as $questionName ) {
			if ( $questionBank[$questionName]['disabled'] ?? false ) {
				continue;
			}
			if ( $asKeyedArray ) {
				$questions[ $questionName ] = $questionBank[ $questionName ];
			} else {
				$questions[] = [ 'name' => $questionName ] + $questionBank[ $questionName ];
			}
		}
		return $questions;
	}

	/**
	 * Bank of questions that can be used on the Welcome survey.
	 * Format is HTMLForm configuration, with two special keys 'placeholder-message'
	 * and 'other-message' for type=select (see SpecialWelcomeSurvey::getFormFields()).
	 * @return array
	 */
	protected function getQuestionBank() : array {
		// When free text is enabled, add other-* settings and the reason-other question
		$reasonOtherSettings = $this->allowFreetext ? [
			'other-message' => 'welcomesurvey-question-reason-option-other-label',
		] : [];
		$reasonOtherQuestion = $this->allowFreetext ? [
			'reason-other' => [
				'type' => 'text',
				'placeholder-message' => [ 'welcomesurvey-question-reason-other-placeholder',
					$this->context->getUser()->getName() ],
				'size' => 255,
				'hide-if' => [ '!==', 'reason', 'other' ],
				'group' => 'reason'
			]
		] : [];
		// When free text is disabled, add an "Other" option to the reason question
		$reasonOtherOption = $this->allowFreetext ? [] : [
			"welcomesurvey-question-reason-option-other-no-freetext-label" => "other",
		];
		$questions = [
			"reason" => [
				"type" => "select",
				"label-message" => "welcomesurvey-question-reason-label",
				"options-messages" => [
					"welcomesurvey-question-reason-option-edit-typo-label" => "edit-typo",
					"welcomesurvey-question-reason-option-edit-info-add-change-label" => "edit-info-add-change",
					"welcomesurvey-question-reason-option-add-image-label" => "add-image",
					"welcomesurvey-question-reason-option-new-page-label" => "new-page",
					"welcomesurvey-question-reason-option-program-participant-label" => "program-participant",
					"welcomesurvey-question-reason-option-read-label" => "read",
				] + $reasonOtherOption,
				"placeholder-message" => "welcomesurvey-dropdown-option-select-label",
				"name" => "reason",
				"group" => "reason",
			] + $reasonOtherSettings
		] + $reasonOtherQuestion + [
			"edited" => [
				"type" => "select",
				"label-message" => "welcomesurvey-question-edited-label",
				"options-messages" => [
					"welcomesurvey-question-edited-option-yes-many-label" => "yes-many",
					"welcomesurvey-question-edited-option-yes-few-label" => "yes-few",
					"welcomesurvey-question-edited-option-no-dunno-label" => "dunno",
					"welcomesurvey-question-edited-option-no-other-label" => "no-other",
					"welcomesurvey-question-edited-option-dont-remember-label" => "dont-remember",
				],
				"placeholder-message" => "welcomesurvey-dropdown-option-select-label",
				"group" => "edited",
			],
			"languages" => [
				"type" => "multiselect",
				"options" => array_flip( $this->languageNameUtils->getLanguageNames() ),
				"cssclass" => "welcomesurvey-languages",
				"label-message" => "welcomesurvey-question-languages-label",
				"dependencies" => [
					"modules" => [
						"ext.growthExperiments.welcomeSurveyLanguage",
						"ext.uls.mediawiki"
					]
				],
				"disabled" => false
			],
			"mentor-info" => [
				"type" => "info",
				"label-message" => [ "welcomesurvey-question-mentor-info",
					$this->context->getUser()->getName() ],
				"cssclass" => "welcomesurvey-mentor-info",
				"group" => "email",
			],
			"mentor" => [
				"type" => "check",
				"label-message" => [ "welcomesurvey-question-mentor-label",
					$this->context->getUser()->getName() ],
				"cssclass" => "welcomesurvey-mentor-check",
				"group" => "email",
			],
			"email" => [
				"type" => "email",
				"label-message" => "welcomesurvey-question-email-label",
				"placeholder-message" => "welcomesurvey-question-email-placeholder",
				"help-message" => "welcomesurvey-question-email-help",
				"group" => "email",
			]
		];
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UniversalLanguageSelector' ) ) {
			$questions['languages']['disabled'] = true;
		}
		return $questions;
	}

	/**
	 * Save the responses data and/or metadata as appropriate
	 *
	 * @param array $data Responses of the survey questions, keyed by questions' names
	 * @param bool $save True if the user selected to submit their responses,
	 *  false if they chose to skip
	 * @param string $group Name of the group this form is for
	 * @param string $renderDate Timestamp in MW format of when the form was shown
	 */
	public function handleResponses( $data, $save, $group, $renderDate ) {
		$user = $this->context->getUser()->getInstanceForUpdate();
		$submitDate = MWTimestamp::now();
		$userUpdated = false;

		if ( $save ) {
			// set email
			$newEmail = $data[ 'email' ] ?? false;
			if ( $newEmail ) {
				$data[ 'email' ] = '[redacted]';
				if ( Util::canSetEmail( $user, $newEmail ) ) {
					$user->setEmailWithConfirmation( $newEmail );
					$userUpdated = true;
				}
			}

			$results = $data;
		} else {
			$results = [ '_skip' => true ];
		}

		$counter = ( FormatJson::decode(
			$user->getOption( self::SURVEY_PROP, '' )
		)->_counter ?? 0 ) + 1;

		$results = array_merge(
			$results,
			[
				'_group' => $group,
				'_render_date' => $renderDate,
				'_submit_date' => $submitDate,
				'_counter' => $counter,
			]
		);
		$encodedData = FormatJson::encode( $results );
		if ( strlen( $encodedData ) <= self::BLOB_SIZE ) {
			$user->setOption( self::SURVEY_PROP, $encodedData );
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
	}

	/**
	 * This is called right after user account creation and before the user is redirected
	 * to the welcome survey. It ensures that we keep track of which group a user
	 * is part of if they never submit any responses or don't even get the survey.
	 *
	 * @param string $group
	 */
	public function saveGroup( $group ) {
		$group = $group ?: 'NONE';
		$user = $this->context->getUser();
		$data = [
			'_group' => $group,
			'_render_date' => MWTimestamp::now(),
		];
		$user->setOption(
			self::SURVEY_PROP,
			FormatJson::encode( $data )
		);
		$user->saveSettings();
	}

	/**
	 * Build the redirect URL for a group and its display format
	 *
	 * @param string $group
	 * @return bool|string
	 */
	public function getRedirectUrl( $group ) {
		$questions = $this->getQuestions( $group );
		if ( !$questions ) {
			return false;
		}

		$request = $this->context->getRequest();
		$returnTo = $request->getVal( 'returnto' );
		$returnToQuery = $request->getVal( 'returntoquery' );

		$welcomeSurvey = SpecialPage::getTitleFor( 'WelcomeSurvey' );
		$query = wfArrayToCgi( [
			'returnto' => $returnTo,
			'returntoquery' => $returnToQuery,
			'group' => $group,
		] );
		return $welcomeSurvey->getFullUrlForRedirect( $query );
	}

}
