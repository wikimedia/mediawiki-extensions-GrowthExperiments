<?php

namespace GrowthExperiments\EventLogging;

use EventLogging;
use GrowthExperiments\HomepageModules\Email;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\HomepageModules\Userpage;
use MWException;
use User;
use WebRequest;

class SpecialHomepageLogger {

	/**
	 * @var string
	 */
	private $pageviewToken;
	/**
	 * @var array
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
	 * SpecialHomepageLogger constructor.
	 * @param string $pageviewToken
	 * @param User $user
	 * @param WebRequest $request
	 * @param bool $isMobile
	 * @param array $modules
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

	/**
	 * Log an event to HomepageVisit.
	 * @throws MWException
	 */
	public function log() {
		$event = [];
		$event['is_mobile'] = $this->isMobile;
		$referer = $this->request->getHeader( 'REFERER' );
		$event['referer_route'] = $this->request->getVal(
			'source',
			// If there is no referer header and no source param, then assume the user went to the
			// page directly from their browser history/bookmark/etc.
			$referer ? 'other' : 'direct'
		);
		// todo: If we want to know namespace by attempting to load the MediaWiki title from the
		// referer (i.e. someone made a link to Special:Homepage on some arbitrary title, we can
		// override the below.
		$event['referer_namespace'] = $this->request->getVal( 'namespace' );
		// Default.
		$event['referer_action'] = 'view';
		if ( $referer ) {
			$referer = wfParseUrl( $referer );
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
			// specific value.
			$event['referer_action'] = 'other';
		}
		$event['user_id'] = $this->user->getId();
		$event['user_editcount'] = $this->user->getEditCount();
		/** @var Impact $impactModule */
		$impactModule = $this->modules['impact'];
		$event['impact_module_activated'] = $impactModule->getArticleContributions() !== null;
		$startTasks = $this->modules['start']->getTasks();
		/** @var Tutorial $tutorialTask */
		$tutorialTask = $startTasks['tutorial'];
		$event['start_tutorial_state'] = $tutorialTask->getState();
		/** @var Userpage $userpageTask */
		$userpageTask = $startTasks['userpage'];
		$event['start_userpage_state'] = $userpageTask->getState();
		/** @var Email $emailTask */
		$emailTask = $startTasks['email'];
		$event['start_email_state'] = $emailTask->getState();
		$event['homepage_pageview_token'] = $this->pageviewToken;

		EventLogging::logEvent( 'HomepageVisit', 18999420, $event );
	}

}
