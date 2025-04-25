<?php

namespace GrowthExperiments\LevelingUp;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\JobQueue\Job;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityLookup;

/**
 * Job class for sending a "get started" notification to new users.
 *
 * Business rules for when to send the notification are contained in
 * HomepageHooks::LocalUserCreated and LevelingUpManager.
 */
class NotificationGetStartedJob extends Job {
	private LevelingUpManager $levelingUpManager;
	private UserIdentityLookup $userIdentityLookup;
	private SpecialPageFactory $specialPageFactory;

	public const JOB_NAME = 'notificationGetStartedJob';

	/**
	 * @inheritDoc
	 * Parameters:
	 * - userId: The relevant user ID.
	 */
	public function __construct(
		Title $title,
		$params,
		UserIdentityLookup $userIdentityLookup,
		SpecialPageFactory $specialPageFactory,
		LevelingUpManager $levelingUpManager
	) {
		parent::__construct( self::JOB_NAME, $params );
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
		}
		return true;
	}
}
