<?php

namespace GrowthExperiments;

use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use LogicException;
use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\HtmlHelper;
use MediaWiki\Json\FormatJson;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\Utils\MWTimestamp;
use stdClass;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\RemexHtml\HTMLData;
use Wikimedia\RemexHtml\Serializer\SerializerNode;

class WelcomeSurvey {

	private const BLOB_SIZE = 65535;

	public const SURVEY_PROP = 'welcomesurvey-responses';

	public const DEFAULT_SURVEY_GROUP = 'control';

	private IContextSource $context;
	private bool $allowFreetext;
	private LanguageNameUtils $languageNameUtils;
	private UserOptionsManager $userOptionsManager;
	private bool $ulsInstalled;

	public function __construct(
		IContextSource $context,
		LanguageNameUtils $languageNameUtils,
		UserOptionsManager $userOptionsManager,
		bool $ulsInstalled
	) {
		$this->context = $context;
		$this->allowFreetext =
			(bool)$this->context->getConfig()->get( 'WelcomeSurveyAllowFreetextResponses' );
		$this->languageNameUtils = $languageNameUtils;
		$this->userOptionsManager = $userOptionsManager;
		$this->ulsInstalled = $ulsInstalled;
	}

	/**
	 * Get the name of the experimental group for the current user or
	 * false they are not part of any experiment.
	 *
	 * @param bool $useDefault Use default group from WelcomeSurveyDefaultGroup, if it is defined
	 * @return bool|string
	 * @throws ConfigException
	 */
	public function getGroup( $useDefault = false ) {
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
		$groupFromProp = $this->loadSurveyData()->_group ?? false;
		if ( isset( $groups[ $groupFromProp ] ) ) {
			return $groupFromProp;
		}

		if ( $useDefault ) {
			// Fallback to default group if directly visiting Special:WelcomeSurvey
			return self::DEFAULT_SURVEY_GROUP;
		}

		// Randomly selecting a group
		$js = $this->context->getRequest()->getBool( 'client-runs-javascript' );
		$rand = rand( 0, 99 );
		foreach ( $groups as $name => $groupConfig ) {
			$percentage = 0;
			if ( isset( $groupConfig[ 'percentage' ] ) ) {
				$percentage = intval( $groupConfig[ 'percentage' ] );
			}

			if ( $rand < $percentage ) {
				if ( !$js && isset( $groupConfig[ 'nojs-fallback' ] ) ) {
					return $groupConfig[ 'nojs-fallback' ];
				}
				return $name;
			}
			$rand -= $percentage;
		}

		return false;
	}

	/**
	 * True if the context user has not filled out the welcome survey and should
	 * be reminded to do so.
	 * More precisely, true if none of the conditions below are true:
	 * - The user has submitted the survey.
	 * - At least $wgWelcomeSurveyReminderExpiry days have passed since the user has registered.
	 * - The user is past the survey retention period; they might or might not have
	 *   filled out the survey, but if they did, the data was already discarded.
	 * - The user has pressed the "Skip" button on the survey.
	 * - The user is in an A/B test group which is not supposed to get the survey.
	 * @return bool
	 */
	public function isUnfinished(): bool {
		$registrationDate = $this->context->getUser()->getRegistration() ?: null;
		if ( !$registrationDate ) {
			// User is anon or has registered a long, long time ago when MediaWiki had no logging for it.
			return false;
		}
		$registrationTimestamp = (int)wfTimestamp( TS_UNIX, $registrationDate );
		$expiryDays = $this->context->getConfig()->get( 'WelcomeSurveyReminderExpiry' );
		$expirySeconds = $expiryDays * ExpirationAwareness::TTL_DAY;
		if ( $registrationTimestamp + $expirySeconds < MWTimestamp::now( TS_UNIX ) ) {
			// The configured reminder expiry has passed.
			return false;
		}

		// Survey data can be written by the user via options API so don't assume any structure.
		$data = $this->loadSurveyData();
		$group = $data->_group ?? null;
		$skipped = ( $data->_skip ?? null ) === true;
		$submitted = ( $data->_submit_date ?? null ) !== null;

		if ( !$data ) {
			// Either the user is past the data retention period and all data was discarded (in
			// which case we don't know if they ever filled out the survey but months have
			// passed since they registered so it's fine to consider the survey as intentionally
			// not filled out), or WelcomeSurveyHooks::on[CentralAuth]PostLoginRedirect() did
			// not run for some reason (maybe the feature wasn't enabled when they registered).
			return false;
		} elseif ( $skipped ) {
			// User chose not to fill out the survey. Somewhat arbitrarily, we consider that
			// as finishing it.
			return false;
		} elseif ( $submitted ) {
			// User did fill out the survey.
			return false;
		}

		$groups = $this->context->getConfig()->get( 'WelcomeSurveyExperimentalGroups' );
		$questions = $groups[ $group ][ 'questions' ] ?? null;
		if ( !$questions ) {
			// This is an A/B test control group, the user should never see the survey.
			// Or maybe the configuration changed, and this group does not exist anymore,
			// in which case we'll just ignore it.
			return false;
		}

		return true;
	}

	/**
	 * Get the questions' configuration for the specified group
	 *
	 * @param string $group
	 * @param bool $asKeyedArray True to use the question name as key, false to use a numerical index
	 * @return array Questions configuration
	 * @throws ConfigException
	 */
	public function getQuestions( $group, $asKeyedArray = true ) {
		$groups = $this->context->getConfig()->get( 'WelcomeSurveyExperimentalGroups' );
		if ( !isset( $groups[ $group ] ) ) {
			return [];
		}

		$questionNames = $groups[ $group ][ 'questions' ] ?? [];
		if ( in_array( 'email', $questionNames ) &&
			!Util::canSetEmail( $this->context->getUser(), '', false )
		) {
			$questionNames = array_diff( $questionNames, [ 'email' ] );
		}
		if ( $questionNames !== [] && !in_array( 'privacy-info', $questionNames ) ) {
			$questionNames[] = 'privacy-info';
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
	 *
	 * @see \HTMLForm::$typeMappings for the values to use for "type"
	 * @return array
	 */
	protected function getQuestionBank(): array {
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
				'group' => 'reason',
			],
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
			] + $reasonOtherSettings,
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
			"mailinglist" => [
				"type" => "check",
				"group" => "email",
				"label-message" => "welcomesurvey-question-mailinglist-label",
				"help" => $this->addLinkTarget(
					$this->context->msg( 'welcomesurvey-question-mailinglist-help' )->parse()
				),
			],
			"languages" => [
				"type" => "multiselect",
				"options" => array_flip( $this->languageNameUtils->getLanguageNames() ),
				"cssclass" => "welcomesurvey-languages",
				"label-message" => "welcomesurvey-question-languages-label",
				"dependencies" => [
					"modules" => [
						"ext.growthExperiments.Account",
						"ext.uls.mediawiki",
					],
				],
				"disabled" => false,
			],
			"privacy-info" => [
				"type" => "info",
				"help" => $this->addLinkTarget( $this->context->msg(
					'welcomesurvey-privacy-footer-text',
					$this->context->getUser()->getName(),
					$this->context->getConfig()->get( 'WelcomeSurveyPrivacyStatementUrl' )
				)->parse() ),
				"cssclass" => "welcomesurvey-privacy-info",
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
			],
		];
		if ( !$this->ulsInstalled ) {
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
		$user = $this->context->getUser()->getInstanceFromPrimary( IDBAccessObject::READ_EXCLUSIVE )
			?? throw new LogicException( 'User not found in the database' );
		$submitDate = MWTimestamp::now();

		if ( $save ) {
			// set email
			$newEmail = $data[ 'email' ] ?? '';
			if ( $newEmail ) {
				$data[ 'email' ] = '[redacted]';
				if ( Util::canSetEmail( $user, $newEmail ) ) {
					$user->setEmailWithConfirmation( $newEmail );
					$user->saveSettings();
				}
			}

			$results = $data;
		} else {
			$results = [ '_skip' => true ];
		}

		$counter = ( $this->loadSurveyData()->_counter ?? 0 ) + 1;

		$results = array_merge(
			$results,
			[
				'_group' => $group,
				'_render_date' => $renderDate,
				'_submit_date' => $submitDate,
				'_counter' => $counter,
			]
		);
		$this->saveSurveyData( $results );
	}

	/**
	 * Save the survey as skipped.
	 * @return void
	 */
	public function dismiss() {
		$group = $this->loadSurveyData()->_group ?? self::DEFAULT_SURVEY_GROUP;
		$this->handleResponses( [], true, $group, wfTimestampNow() );
	}

	/**
	 * Store the given survey data for the context user.
	 * @param array $data
	 * @return void
	 */
	private function saveSurveyData( array $data ): void {
		$user = $this->context->getUser();
		$encodedData = FormatJson::encode( $data );
		if ( strlen( $encodedData ) > self::BLOB_SIZE ) {
			Util::logText(
				'Unable to save Welcome survey responses for user {userId} because it is too big.',
				[ 'userId' => $user->getId() ]
			);
			return;
		}
		$this->userOptionsManager->setOption( $user, self::SURVEY_PROP, $encodedData );
		$this->userOptionsManager->saveOptions( $user );
	}

	/**
	 * Return the survey data stored for the context user.
	 * @return stdClass|null A JSON object with survey data, or null if no data is stored.
	 */
	private function loadSurveyData(): ?stdClass {
		$user = $this->context->getUser();
		$data = FormatJson::decode(
			$this->userOptionsManager->getOption(
				$user,
				self::SURVEY_PROP,
				''
			)
		);
		if ( $data !== null && !( $data instanceof stdClass ) ) {
			// user options can always contain unsanitized user data
			Util::logText(
				'Invalid welcome survey data for {user}',
				[ 'userId' => $user->getId() ]
			);
			$data = null;
		}
		return $data;
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
		$data = [
			'_group' => $group,
			'_render_date' => MWTimestamp::now(),
		];
		$this->saveSurveyData( $data );
	}

	/**
	 * Build the redirect URL for a group and its display format
	 *
	 * @param string $group
	 * @param string $returnTo
	 * @param string $returnToQuery
	 * @return bool|array
	 */
	public function getRedirectUrlQuery( string $group, string $returnTo, string $returnToQuery ) {
		$questions = $this->getQuestions( $group );
		if ( !$questions ) {
			return false;
		}

		$request = $this->context->getRequest();

		$welcomeSurveyToken = Util::generateRandomToken();
		$request->response()->setCookie( WelcomeSurveyLogger::WELCOME_SURVEY_TOKEN,
			$welcomeSurveyToken, time() + 3600 );
		return [
			'returnto' => $returnTo,
			'returntoquery' => $returnToQuery,
			'group' => $group,
			'_welcomesurveytoken' => $welcomeSurveyToken,
		];
	}

	/**
	 * Add target=_blank to links in a HTML snippet.
	 * @param string $html
	 * @return string
	 */
	private function addLinkTarget( string $html ) {
		return HtmlHelper::modifyElements( $html, static function ( SerializerNode $node ) {
			return $node->namespace === HTMLData::NS_HTML
				&& $node->name === 'a'
				&& isset( $node->attrs['href'] )
				&& !isset( $node->attrs['target'] );
		}, static function ( SerializerNode $node ) {
			$node->attrs['target'] = '_blank';
			return $node;
		} );
	}

}
