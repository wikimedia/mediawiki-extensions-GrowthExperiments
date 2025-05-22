<?php

namespace GrowthExperiments\EventLogging;

use GrowthExperiments\HomepageModules\Impact;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;

class SpecialHomepageLogger {

	/**
	 * @var string
	 */
	private $pageviewToken;
	/**
	 * @var array Associative array of modules used on the homepage. Keys are module names,
	 *   values are arbitrary.
	 */
	private $modules;
	/**
	 * @var WebRequest
	 */
	private $request;
	/**
	 * @var bool
	 */
	private $isMobile;
	/**
	 * @var User
	 */
	private $user;

	/**
	 * @param string $pageviewToken
	 * @param User $user
	 * @param WebRequest $request
	 * @param bool $isMobile
	 * @param array $modules Associative array of modules used on the homepage. Keys are module names,
	 *   values are arbitrary.
	 */
	public function __construct(
		$pageviewToken,
		User $user,
		WebRequest $request,
		$isMobile,
		array $modules
	) {
		$this->pageviewToken = $pageviewToken;
		$this->user = $user;
		$this->modules = $modules;
		$this->request = $request;
		$this->isMobile = $isMobile;
	}

	private function getEmailState(): string {
		if ( $this->user->isEmailConfirmed() ) {
			return 'confirmed';
		} elseif ( $this->user->getEmail() ) {
			return 'unconfirmed';
		} else {
			return 'noemail';
		}
	}

	/**
	 * Log an event to HomepageVisit.
	 */
	public function log() {
		$services = MediaWikiServices::getInstance();
		$event = [];
		$event['is_mobile'] = $this->isMobile;
		$referer = $this->request->getHeader( 'REFERER' );
		$event['referer_route'] = $this->request->getVal(
			'source',
			// If there is no referer header and no source param, then assume the user went to the
			// page directly from their browser history/bookmark/etc.
			$referer ? 'other' : 'direct'
		);
		$namespace = $this->request->getVal( 'namespace' );
		if ( $namespace !== null ) {
			$event['referer_namespace'] = (int)$namespace;
		}
		$event['referer_action'] = 'view';
		if ( $referer ) {
			$referer = $services->getUrlUtils()->parse( $referer );
			if ( isset( $referer['query'] ) ) {
				$referer_query = wfCgiToArray( $referer['query'] );
				if ( isset( $referer_query['action'] ) ) {
					$event['referer_action'] = $referer_query['action'];
				}
			}
			if ( !isset( $event['referer_action'] ) ) {
				$event['referer_action'] = $this->request->getVal( 'action', 'view' );
			}
		}
		if ( !in_array( $event['referer_action' ], [ 'view', 'edit' ] ) ) {
			// Some other action, like info, was specified. For analysis we don't care about the
			// specific value, just that it's not one of "view" or "edit".
			$event['referer_action'] = 'other';
		}
		$event['user_id'] = $this->user->getId();
		$event['user_editcount'] = $this->user->getEditCount();

		/** @var Impact $impactModule */
		$impactModule = $this->modules['impact'] ?? false;
		if ( $impactModule ) {
			$event['impact_module_state'] = $impactModule->getState();
		} else {
			// Should not happen; it is a required schema field.
			LoggerFactory::getInstance( 'GrowthExperiments' )
				->error( 'Could not set HomepageVisit.impact_module_state schema field' );
		}

		$event['start_email_state'] = $this->getEmailState();
		$event['homepage_pageview_token'] = $this->pageviewToken;

		// This has been migrated to an Event Platform schema; schema revision is no longer used
		// in this call.  Versioned schema URI is set in extension.json.
		EventLogging::logEvent( 'HomepageVisit', -1, $event );
	}

}
