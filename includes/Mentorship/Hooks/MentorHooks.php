<?php

namespace GrowthExperiments\Mentorship\Hooks;

use Config;
use DeferredUpdates;
use EchoAttributeManager;
use EchoUserLocator;
use GrowthExperiments\Mentorship\EchoMenteeClaimPresentationModel;
use GrowthExperiments\Mentorship\EchoMentorChangePresentationModel;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Specials\SpecialEnrollAsMentor;
use GrowthExperiments\Specials\SpecialManageMentors;
use GrowthExperiments\Specials\SpecialQuitMentorshipStructured;
use GrowthExperiments\Specials\SpecialQuitMentorshipWikitext;
use GrowthExperiments\Util;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Hook\FormatAutocommentsHook;
use MediaWiki\Permissions\Hook\UserGetRightsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Psr\Log\LogLevel;
use Throwable;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class MentorHooks implements
	SpecialPage_initListHook,
	LocalUserCreatedHook,
	PageSaveCompleteHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	FormatAutocommentsHook,
	UserGetRightsHook
{

	/** @var Config */
	private $config;

	/** @var Config */
	private $wikiConfig;

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorProvider */
	private $mentorProvider;

	/**
	 * @param Config $config
	 * @param Config $wikiConfig
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 */
	public function __construct(
		Config $config,
		Config $wikiConfig,
		MentorManager $mentorManager,
		MentorProvider $mentorProvider
	) {
		$this->config = $config;
		$this->wikiConfig = $wikiConfig;
		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
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
				$this->mentorManager->getMentorForUser( $user, MentorStore::ROLE_PRIMARY );
				$this->mentorManager->getMentorForUser( $user, MentorStore::ROLE_BACKUP );
			} catch ( Throwable $throwable ) {
				Util::logException( $throwable, [
					'user' => $user->getId(),
					'impact' => 'Failed to assign mentor for user',
					'origin' => __METHOD__,
				], LogLevel::INFO );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		DeferredUpdates::addCallableUpdate( function () use ( $wikiPage ) {
			$title = $wikiPage->getTitle();

			$sourceTitles = $this->mentorProvider->getSourceTitles();
			foreach ( $sourceTitles as $sourceTitle ) {
				if ( $sourceTitle->equals( $title ) ) {
					$this->mentorProvider->invalidateCache();
					break;
				}
			}
		} );
	}

	/**
	 * @inheritDoc
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->config->get( 'GEMentorProvider' ) === MentorProvider::PROVIDER_STRUCTURED ) {
			// TODO: Move to extension.json once wikitext provider is removed
			$list['ManageMentors'] = [
				'class' => SpecialManageMentors::class,
				'services' => [
					'UserIdentityLookup',
					'UserEditTracker',
					'GrowthExperimentsMentorProvider',
					'GrowthExperimentsMentorWriter',
					'GrowthExperimentsReassignMenteesFactory',
					'GrowthExperimentsMentorStatusManager'
				]
			];
			$list['EnrollAsMentor'] = [
				'class' => SpecialEnrollAsMentor::class,
				'services' => [
					'GrowthExperimentsMentorProvider',
					'GrowthExperimentsMentorWriter',
				]
			];
			$list['QuitMentorship'] = [
				'class' => SpecialQuitMentorshipStructured::class,
				'services' => [
					'GrowthExperimentsReassignMenteesFactory',
					'GrowthExperimentsMentorStore',
					'GrowthExperimentsMentorProvider',
					'GrowthExperimentsMentorWriter',
				]
			];
		} elseif ( $this->config->get( 'GEMentorProvider' ) === MentorProvider::PROVIDER_WIKITEXT ) {
			// TODO: Remove once wikitext provider is removed
			$list['ReassignMentees'] = [
				'class' => SpecialQuitMentorshipWikitext::class,
				'services' => [
					'GrowthExperimentsReassignMenteesFactory',
					'GrowthExperimentsMentorProvider'
				]
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onListDefinedTags( &$tags ) {
		// define the change tag unconditionally, in case a wiki switches back to PROVIDER_WIKITEXT
		$tags[] = StructuredMentorWriter::CHANGE_TAG;
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsListActive( &$tags ) {
		if ( $this->config->get( 'GEMentorProvider' ) === MentorProvider::PROVIDER_STRUCTURED ) {
			$tags[] = StructuredMentorWriter::CHANGE_TAG;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onFormatAutocomments( &$comment, $pre, $auto, $post, $title, $local, $wikiId ) {
		$allowedMessageKeys = [
			'growthexperiments-mentorship-enrollasmentor-summary'
		];
		if ( in_array( $auto, $allowedMessageKeys ) ) {
			$comment = wfMessage( $auto )->text();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetRights( $user, &$rights ) {
		if (
			$this->config->get( 'GEMentorProvider' ) !== MentorProvider::PROVIDER_STRUCTURED ||
			!$this->wikiConfig->get( 'GEMentorshipAutomaticEligibility' )
		) {
			return;
		}

		// ConvertibleTimestamp::time() used so we can fake the current time in tests
		$userAge = ConvertibleTimestamp::time() - (int)wfTimestampOrNull( TS_UNIX, $user->getRegistration() );
		if (
			$userAge >= $this->wikiConfig->get( 'GEMentorshipMinimumAge' ) * ExpirationAwareness::TTL_DAY &&
			$user->getEditCount() >= $this->wikiConfig->get( 'GEMentorshipMinimumEditcount' )
		) {
			$rights[] = 'enrollasmentor';
		}
	}
}
