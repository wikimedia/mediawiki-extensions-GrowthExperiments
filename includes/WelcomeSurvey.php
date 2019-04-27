<?php

namespace GrowthExperiments;

use FormatJson;
use IContextSource;
use MediaWiki\Logger\LoggerFactory;
use MWTimestamp;
use SpecialPage;
use Title;

class WelcomeSurvey {

	const BLOB_SIZE = 65535;

	const SURVEY_PROP = 'welcomesurvey-responses';

	/**
	 * @var IContextSource
	 */
	private $context;

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
				"welcomesurvey-question-reason-option-edit-typo-label" => "edit-typo",
				"welcomesurvey-question-reason-option-edit-info-label" => "edit-info",
				"welcomesurvey-question-reason-option-new-page-label" => "new-page",
				"welcomesurvey-question-reason-option-read-label" => "read",
			],
			"placeholder-message" => "welcomesurvey-dropdown-option-select-label",
			"other-message" => "welcomesurvey-question-reason-option-other-label",
			"other-placeholder-message" => "welcomesurvey-question-reason-other-placeholder",
			"other-size" => 255,
			"name" => "reason",
			"group" => "reason",
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
			"group" => "reason",
		],
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
				"welcomesurvey-question-topics-option-religion" => "religion",
				"welcomesurvey-question-topics-option-popular-culture" => "popular culture",
			],
			"group" => "topics",
		],
		"topics-other-js" => [
			"type" => "multiselect",
			"allowArbitrary" => true,
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
			"group" => "topics",
		],
		"topics-other-nojs" => [
			"type" => "text",
			"placeholder-message" => "welcomesurvey-question-topics-other-placeholder",
			"cssclass" => "nojs-only",
			"group" => "topics",
		],
		"mentor-info" => [
			"type" => "info",
			"label-message" => "welcomesurvey-question-mentor-info",
			"cssclass" => "welcomesurvey-mentor-info",
			"group" => "email",
		],
		"mentor" => [
			"type" => "check",
			"label-message" => "welcomesurvey-question-mentor-label",
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

	/**
	 * WelcomeSurvey constructor.
	 * @param IContextSource $context
	 */
	public function __construct( IContextSource $context ) {
		$this->context = $context;
	}

	/**
	 * Get the name of the experimental group for the current user or
	 * false they are not part of any experiment.
	 *
	 * @return bool|string
	 */
	public function getGroup() {
		$groups = $this->context->getConfig()->get( 'WelcomeSurveyExperimentalGroups' );

		// The group is specified in the URL
		$request = $this->context->getRequest();
		$groupParam = $request->getText( 'group' );
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
		$questions = [];
		foreach ( $questionNames as $questionName ) {
			if ( $asKeyedArray ) {
				$questions[ $questionName ] = $this->questions[ $questionName ];
			} else {
				$questions[] = [ 'name' => $questionName ] + $this->questions[ $questionName ];
			}
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

	private function getSurveyFormat( $group ) {
		$groups = $this->context->getConfig()->get( 'WelcomeSurveyExperimentalGroups' );
		return $groups[ $group ][ 'format' ] ?? null;
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
		$format = $this->getSurveyFormat( $group );
		$returnTo = $request->getVal( 'returnto' );
		$returnToQuery = $request->getVal( 'returntoquery' );

		if ( $format === 'specialpage' ) {
			$welcomeSurvey = SpecialPage::getTitleFor( 'WelcomeSurvey' );
			$query = wfArrayToCgi( [
				'returnto' => $returnTo,
				'returntoquery' => $returnToQuery,
				'group' => $group,
			] );
			return $welcomeSurvey->getFullUrlForRedirect( $query );
		}

		if ( $format === 'popup' ) {
			$title = Title::newFromText( $returnTo ) ?: Title::newMainPage();
			$query = wfArrayToCgi( array_merge(
				wfCgiToArray( $returnToQuery ),
				[
					'showwelcomesurvey' => 1,
					'group' => $group,
				]
			) );
			return $title->getFullUrlForRedirect( $query );
		}
	}

}
