<?php

namespace GrowthExperiments\Mentorship\Hooks;

use Config;
use EchoAttributeManager;
use EchoUserLocator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\EchoMenteeClaimPresentationModel;
use GrowthExperiments\Mentorship\EchoMentorChangePresentationModel;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Util;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use Throwable;

class MentorHooks implements LocalUserCreatedHook {

	/** @var Config */
	private $wikiConfig;

	/** @var MentorManager */
	private $mentorManager;

	/**
	 * @param Config $wikiConfig
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		Config $wikiConfig,
		MentorManager $mentorManager
	) {
		$this->wikiConfig = $wikiConfig;
		$this->mentorManager = $mentorManager;
	}

	/**
	 * Add GrowthExperiments events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['ge-mentorship'] = [
			'tooltip' => 'echo-pref-tooltip-ge-mentorship',
		];

		$notifications['mentor-changed'] = [
			'category' => 'system',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoMentorChangePresentationModel::class,
			EchoAttributeManager::ATTR_LOCATORS => [
				[
					EchoUserLocator::class . '::locateFromEventExtra',
					[ 'mentee' ]
				],
			],
		];
		$notifications['mentee-claimed'] = [
			'category' => 'ge-mentorship',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => EchoMenteeClaimPresentationModel::class,
			EchoAttributeManager::ATTR_LOCATORS => [
				[
					EchoUserLocator::class . '::locateFromEventExtra',
					[ 'mentor' ]
				]
			]
		];

		$icons['growthexperiments-mentor'] = [
			'path' => [
				'ltr' => 'GrowthExperiments/images/mentor-ltr.svg',
				'rtl' => 'GrowthExperiments/images/mentor-rtl.svg'
			]
		];
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $autocreated ) {
			// Excluding autocreated users is necessary, see T276720
			return;
		}
		if ( $this->wikiConfig->get( 'GEMentorshipEnabled' ) ) {
			try {
				// Select a primary & backup mentor. FIXME Not really necessary, but avoids a
				// change in functionality after introducing MentorManager, making debugging easier.
				$mentorManager = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )
					->getMentorManager();

				$mentorManager->getMentorForUser( $user, MentorStore::ROLE_PRIMARY );
				$mentorManager->getMentorForUser( $user, MentorStore::ROLE_BACKUP );
			} catch ( Throwable $throwable ) {
				Util::logException( $throwable, [
					'user' => $user->getId(),
					'impact' => 'Failed to assign mentor for user',
					'origin' => __METHOD__,
				], LogLevel::INFO );
			}
		}
	}
}
