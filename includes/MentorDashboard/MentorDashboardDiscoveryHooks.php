<?php

namespace GrowthExperiments\MentorDashboard;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;

class MentorDashboardDiscoveryHooks implements SkinTemplateNavigation__UniversalHook, BeforePageDisplayHook {

	public const MENTOR_DASHBOARD_SEEN_PREF = 'growthexperiments-mentor-dashboard-seen';

	private MentorProvider $mentorProvider;
	private UserOptionsLookup $userOptionsLookup;

	public function __construct(
		MentorProvider $mentorProvider,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->mentorProvider = $mentorProvider;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Are mentor dashboard discovery features enabled?
	 *
	 * @param User $user
	 * @return bool
	 */
	private function isDiscoveryEnabled( User $user ): bool {
		return $user->isNamed() && $this->mentorProvider->isMentor( $user );
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $skin, &$links ): void {
		if ( !$this->isDiscoveryEnabled( $skin->getUser() ) ) {
			return;
		}
		$personalUrls = $links['user-menu'] ?? [];

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

		$links['user-menu'] = $newPersonalUrls;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$user = $skin->getUser();

		if (
			!$this->isDiscoveryEnabled( $user ) ||
			// do not show the blue dot if the user ever visited their mentor dashboard
			$this->userOptionsLookup->getBoolOption( $user, self::MENTOR_DASHBOARD_SEEN_PREF ) ||
			// do not show the blue dot if the user is currently at their dashboard
			$skin->getTitle()->equals( SpecialPage::getTitleFor( 'MentorDashboard' ) )
		) {
			return;
		}

		$out->addModules( 'ext.growthExperiments.MentorDashboard.Discovery' );
	}
}
