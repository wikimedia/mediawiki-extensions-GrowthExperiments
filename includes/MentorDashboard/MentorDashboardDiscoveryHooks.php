<?php

namespace GrowthExperiments\MentorDashboard;

use Config;
use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\Hook\PersonalUrlsHook;
use SpecialPage;

class MentorDashboardDiscoveryHooks implements PersonalUrlsHook {

	/** @var Config */
	private $config;

	/** @var MentorManager */
	private $mentorManager;

	/**
	 * @param Config $config
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		Config $config,
		MentorManager $mentorManager
	) {
		$this->config = $config;
		$this->mentorManager = $mentorManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onPersonalUrls( &$personalUrls, &$title, $skin ): void {
		if (
			!$this->config->get( 'GEMentorDashboardEnabled' ) ||
			!$this->config->get( 'GEMentorDashboardDiscoveryEnabled' ) ||
			$skin->getUser()->isAnon() ||
			!$this->mentorManager->isMentor( $skin->getUser() )
		) {
			// disable the link when:
			//   a) the dashboard is disabled
			//   b) the dashboard's discovery features are disabled
			//	 c) user is not logged in
			//   d) user is not a mentor
			return;
		}

		$newPersonalUrls = [];
		foreach ( $personalUrls as $key => $link ) {
			if ( $key == 'logout' ) {
				$newPersonalUrls['mentordashboard'] = [
					'id' => 'pt-mentordashboard',
					'text' => $skin->msg( 'growthexperiments-mentor-dashboard-pt-link' )->text(),
					'href' => SpecialPage::getTitleFor( 'MentorDashboard' )->getLocalURL(),
					'icon' => 'userGroup',
				];
			}
			$newPersonalUrls[$key] = $link;
		}

		$personalUrls = $newPersonalUrls;
	}
}
