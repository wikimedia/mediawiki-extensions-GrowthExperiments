<?php

namespace GrowthExperiments\EventLogging;

use EventLogging;
use ExtensionRegistry;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use WebRequest;

class WelcomeSurveyLogger {

	public const SCHEMA = '/analytics/mediawiki/welcomesurvey/interaction/1.0.1';
	private const STREAM_NAME = 'mediawiki.welcomesurvey.interaction';
	public const INTERACTION_PHASE_COOKIE = 'growth.welcomesurvey.phase';
	public const WELCOME_SURVEY_TOKEN = 'growth.welcomesurvey.token';
	public const WELCOME_SURVEY_LOGGED_OUT = 'logged_out';

	/** @var LoggerInterface */
	private $logger;
	/** @var WebRequest */
	private $webRequest;
	/** @var bool */
	private $isMobile;
	/** @var UserIdentity */
	private $userIdentity;
	/** @var array */
	private $loggedActions = [];

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param WebRequest $webRequest
	 * @param UserIdentity $userIdentity
	 * @param bool $isMobile
	 */
	public function initialize( WebRequest $webRequest, UserIdentity $userIdentity, bool $isMobile ): void {
		$this->webRequest = $webRequest;
		$this->userIdentity = $userIdentity;
		$this->isMobile = $isMobile;
	}

	/**
	 * Log user interactions with the WelcomeSurvey form.
	 *
	 * @param string $action
	 */
	public function logInteraction( string $action ): void {
		$event = [
			'$schema' => self::SCHEMA,
			'action' => $action,
			'is_mobile' => $this->isMobile,
			'was_posted' => $this->webRequest->wasPosted(),
			'user_id' => $this->userIdentity->getId(),
			'token' => $this->webRequest->getVal( '_welcomesurveytoken' ),
			'returnto_param_is_present' =>
				$this->webRequest->getFuzzyBool( '_returnto' ) ??
				$this->webRequest->getFuzzyBool( 'returnto' )
		];
		// Used for integration testing, see SpecialWelcomeSurveyTest.php.
		$this->loggedActions[] = $event;
		// Used to keep track of whether the user is logged out when submitting the form. See
		// WelcomeSurveyHooks::onSpecialPageBeforeExecute
		$this->webRequest->response()->setCookie( self::INTERACTION_PHASE_COOKIE, $action );
		// Except that if the user reached handleResponses() and is logged in, we'll assume submission worked,
		// and we can delete the cookie.
		if ( $this->userIdentity->isRegistered() && $action === SpecialWelcomeSurvey::ACTION_SUBMIT_SUCCESS ) {
			$this->webRequest->response()->clearCookie( self::INTERACTION_PHASE_COOKIE );
		}

		// Suspicious events:
		// * no token in the event
		// * user is logged out
		// * the stored token cookie doesn't match what we got from the web request
		if ( !$event['token'] ||
			$event['user_id'] === 0 ||
			$this->webRequest->getCookie( self::WELCOME_SURVEY_TOKEN ) !== $event['token'] ||
			$action === self::WELCOME_SURVEY_LOGGED_OUT
		) {
			$this->logger->error( 'Suspicious {schema} event for action {action}', [
				'schema' => self::SCHEMA,
				'action' => $action,
				'user_id' => $event['user_id'],
				'was_posted' => $event['was_posted'],
				'token' => $event['token'],
				'token_from_cookie' => $this->webRequest->getCookie( self::WELCOME_SURVEY_TOKEN ),
				'is_mobile' => $event['is_mobile'],
				'returnto_param_is_present' => $event['returnto_param_is_present'],
				'interaction_phase_from_cookie' => $this->webRequest->getCookie( self::INTERACTION_PHASE_COOKIE ),
			] );
		}
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) ) {
			return;
		}

		// Make sure that the token is a string value for event logging validation.
		$event['token'] = $event['token'] ??
			$this->webRequest->getCookie( self::WELCOME_SURVEY_TOKEN, null, '' );

		EventLogging::submit( self::STREAM_NAME, $event, $this->logger );
	}

	/**
	 * @internal Used for integration testing.
	 * @return array
	 */
	public function getLoggedActions(): array {
		return $this->loggedActions;
	}

}
