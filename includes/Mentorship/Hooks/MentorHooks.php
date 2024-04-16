<?php

namespace GrowthExperiments\Mentorship\Hooks;

use EchoAttributeManager;
use EchoUserLocator;
use GenderCache;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\EchoNewPraiseworthyMenteesPresentationModel;
use GrowthExperiments\Mentorship\EchoMenteeClaimPresentationModel;
use GrowthExperiments\Mentorship\EchoMentorChangePresentationModel;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Util;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\FormatAutocommentsHook;
use MediaWiki\Permissions\Hook\UserGetRightsHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LogLevel;
use RequestContext;
use Throwable;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class MentorHooks implements
	LocalUserCreatedHook,
	AuthChangeFormFieldsHook,
	PageSaveCompleteHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	FormatAutocommentsHook,
	UserGetRightsHook,
	BeforePageDisplayHook
{

	private Config $config;
	private Config $wikiConfig;
	private UserIdentityLookup $userIdentityLookup;
	private GenderCache $genderCache;
	private MentorManager $mentorManager;
	private MentorProvider $mentorProvider;
	private MentorStore $mentorStore;

	/**
	 * @param Config $config
	 * @param Config $wikiConfig
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param GenderCache $genderCache
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param MentorStore $mentorStore
	 */
	public function __construct(
		Config $config,
		Config $wikiConfig,
		UserIdentityLookup $userIdentityLookup,
		GenderCache $genderCache,
		MentorManager $mentorManager,
		MentorProvider $mentorProvider,
		MentorStore $mentorStore
	) {
		$this->config = $config;
		$this->wikiConfig = $wikiConfig;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->genderCache = $genderCache;
		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->mentorStore = $mentorStore;
	}

	/**
	 * Add Mentorship events to Echo
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
					[ EchoUserLocator::class, 'locateFromEventExtra' ],
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
					[ EchoUserLocator::class, 'locateFromEventExtra' ],
					[ 'mentor' ]
				]
			]
		];
		$notifications['new-praiseworthy-mentees'] = [
			'category' => 'ge-mentorship',
			'group' => 'positive',
			'section' => 'message',
			'canNotifyAgent' => true,
			'presentation-model' => EchoNewPraiseworthyMenteesPresentationModel::class,
			EchoAttributeManager::ATTR_LOCATORS => [
				EchoUserLocator::class . '::locateEventAgent'
			]
		];

		$icons['growthexperiments-mentor'] = [
			'path' => [
				'ltr' => 'GrowthExperiments/images/mentor-ltr.svg',
				'rtl' => 'GrowthExperiments/images/mentor-rtl.svg'
			]
		];
		// T332732: In he, the mentor icon should be displayed in LTR
		$icons['growthexperiments-mentor-ltr'] = [
			'path' => 'GrowthExperiments/images/mentor-ltr.svg'
		];
	}

	/**
	 * Handles `forceMentor` parameter, if present
	 *
	 * This method checks forceMentor query parameter. If it is present, it:
	 *
	 *     1) gets one or more username from it (| is used as the delimiter)
	 *     2) remove all non-mentors from the lists (determined via MentorProvider::isMentor)
	 *     3) assigns a random mentor from the list to $user
	 *     4) generates a random backup mentor (who may or may not be in the list)
	 *
	 * If no forceMentor parameter is provided (or if it does not contain mentors' usernames),
	 * the method short-circuits and returns false.
	 *
	 * @param UserIdentity $user Newly created user
	 * @return bool returns true if a mentor was assigned to the user (if false is returned,
	 * the caller is responsible for assigning a mentor to the user)
	 */
	private function handleForceMentor( UserIdentity $user ): bool {
		$forceMentorRaw = RequestContext::getMain()->getRequest()
			->getVal( 'forceMentor', '' );
		if ( $forceMentorRaw === '' ) {
			return false;
		}

		$forceMentorNames = explode( '|', $forceMentorRaw );
		$forceMentors = array_filter( array_map(
			function ( $username ) {
				$user = $this->userIdentityLookup->getUserIdentityByName( $username );
				if ( !$user ) {
					return null;
				}
				if ( !$this->mentorProvider->isMentor( $user ) ) {
					return null;
				}
				return $user;
			},
			$forceMentorNames
		) );

		if ( $forceMentors ) {
			$forcedPrimaryMentor = $forceMentors[ array_rand( $forceMentors ) ];

			$this->mentorStore->setMentorForUser(
				$user,
				$forcedPrimaryMentor,
				MentorStore::ROLE_PRIMARY
			);
			// Select a random backup mentor
			$this->mentorManager->getMentorForUser( $user, MentorStore::ROLE_BACKUP );
			return true;
		}
		return false;
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $autocreated || $user->isTemp() ) {
			// Excluding autocreated users is necessary, see T276720
			return;
		}
		if ( $this->wikiConfig->get( 'GEMentorshipEnabled' ) ) {
			try {
				if ( $this->handleForceMentor( $user ) ) {
					return;
				}

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
	 * Pass through the query parameter used by LocalUserCreated.
	 * @inheritDoc
	 */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$forceMentor = RequestContext::getMain()->getRequest()
			->getVal( 'forceMentor', '' );
		if ( $forceMentor !== null ) {
			$formDescriptor['forceMentor'] = [
				'type' => 'hidden',
				'name' => 'forceMentor',
				'default' => $forceMentor,
			];
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
		DeferredUpdates::addCallableUpdate( function () use ( $user ) {
			if ( $this->mentorStore->isMentee( $user ) ) {
				$this->mentorStore->markMenteeAsActive( $user );
			}
		} );
	}

	/**
	 * @inheritDoc
	 */
	public function onListDefinedTags( &$tags ) {
		$tags[] = StructuredMentorWriter::CHANGE_TAG;
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsListActive( &$tags ) {
		$tags[] = StructuredMentorWriter::CHANGE_TAG;
	}

	/**
	 * @inheritDoc
	 */
	public function onFormatAutocomments( &$comment, $pre, $auto, $post, $title, $local, $wikiId ) {
		// NOTE: this message is no longer used, but parsing support needs to be kept to support
		// older revisions.
		$noParamMessageKeys = [
			'growthexperiments-mentorship-enrollasmentor-summary',
		];
		if ( in_array( $auto, $noParamMessageKeys ) ) {
			$comment = wfMessage( $auto )->text();
		}

		$mentorChangeMessageKeys = [
			'growthexperiments-manage-mentors-summary-add-admin-no-reason',
			'growthexperiments-manage-mentors-summary-add-admin-with-reason',
			'growthexperiments-manage-mentors-summary-add-self-no-reason',
			'growthexperiments-manage-mentors-summary-add-self-with-reason',
			'growthexperiments-manage-mentors-summary-change-admin-no-reason',
			'growthexperiments-manage-mentors-summary-change-admin-with-reason',
			'growthexperiments-manage-mentors-summary-change-self-no-reason',
			'growthexperiments-manage-mentors-summary-change-self-with-reason',
			'growthexperiments-manage-mentors-summary-remove-admin-no-reason',
			'growthexperiments-manage-mentors-summary-remove-admin-with-reason',
			'growthexperiments-manage-mentors-summary-remove-self-no-reason',
			'growthexperiments-manage-mentors-summary-remove-self-with-reason',
		];

		$messageParts = explode( ':', $auto, 2 );
		$messageKey = $messageParts[0];
		if ( in_array( $messageKey, $mentorChangeMessageKeys ) ) {
			$comment = wfMessage( $messageKey )
				->params( ...explode( '|', $messageParts[1] ) )
				->inContentLanguage()
				->parse();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetRights( $user, &$rights ) {
		if ( !$this->wikiConfig->get( 'GEMentorshipAutomaticEligibility' ) ) {
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

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getRequest()->getBool( 'gepersonalizedpraise' ) ) {
			$out->addModules( 'ext.growthExperiments.MentorDashboard.PostEdit' );

			$jsConfigVars = [
				'wgPostEditConfirmationDisabled' => true,
				'wgGEMentorDashboardPersonalizedPraisePostEdit' => true,
			];

			// NOTE: gepersonalizedpraise query parameter should be only passed in NS_USER_TALK,
			// but verify that just in case
			$title = $skin->getTitle();
			if ( $title->getNamespace() === NS_USER_TALK ) {
				$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $skin->getTitle()->getText() );
				if ( $userIdentity ) {
					$jsConfigVars['wgGEMentorDashboardPersonalizedPraiseMenteeGender'] = $this->genderCache
						->getGenderOf( $userIdentity );
				}
			}
			$out->addJsConfigVars( $jsConfigVars );
		}
	}
}
