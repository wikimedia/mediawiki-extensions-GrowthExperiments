<?php

namespace GrowthExperiments\LevelingUp;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Stats\StatsFactory;

/**
 * Job class for sending a "get started" notification to new users.
 *
 * Business rules for when to send the notification are contained in
 * HomepageHooks::LocalUserCreated and LevelingUpManager.
 */
class NotificationGetStartedJob extends AbstractDelayedNotificationJob {

	private UserIdentityLookup $userIdentityLookup;
	private SpecialPageFactory $specialPageFactory;
	private LevelingUpManager $levelingUpManager;

	public const JOB_NAME = 'notificationGetStartedJob';

	/**
	 * @inheritDoc
	 * Parameters:
	 * - userId: The relevant user ID.
	 */
	public function __construct(
		Title $title,
		$params,
		StatsFactory $statsFactory,
		UserIdentityLookup $userIdentityLookup,
		SpecialPageFactory $specialPageFactory,
		LevelingUpManager $levelingUpManager
	) {
		parent::__construct(
			self::JOB_NAME, $params,
			$statsFactory
		);

		$this->userIdentityLookup = $userIdentityLookup;
		$this->specialPageFactory = $specialPageFactory;
		$this->levelingUpManager = $levelingUpManager;
		$this->params = $params;
	}

	/** @inheritDoc */
	public function run() {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['userId'] );
		if ( $userIdentity && $this->levelingUpManager->shouldSendGetStartedNotification( $userIdentity ) ) {
			Event::create( [
				'type' => 'get-started',
				'title' => $this->specialPageFactory->getTitleForAlias( 'Homepage' ),
				'agent' => $userIdentity
			] );
			$this->measureNotificationDelay();
		}
		return true;
	}
}
