<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use MediaWiki\Config\Config;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Json\FormatJson;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;

/**
 * Accessor for mentor's Personalized praise settings
 *
 * The settings are modified on the frontend (via action=options); this is why there are no
 * setters available.
 */
class PersonalizedPraiseSettings {

	public const NOTIFY_NEVER = -1;
	public const NOTIFY_IMMEDIATELY = 0;

	/** Note: This is hardcoded on the client side as well */
	public const PREF_NAME = 'growthexperiments-personalized-praise-settings';
	public const USER_MESSAGE_PRELOAD_SUBPAGE_NAME = 'Personalized praise message';

	private const SETTING_MESSAGE_SUBJECT = 'messageSubject';
	private const SETTING_MESSAGE_TEXT = 'messageText';
	private const SETTING_NOTIFICATION_FREQUENCY = 'notificationFrequency';

	private Config $wikiConfig;
	private MessageLocalizer $messageLocalizer;
	private UserOptionsLookup $userOptionsLookup;
	private UserFactory $userFactory;
	private TitleFactory $titleFactory;
	private RevisionLookup $revisionLookup;

	public function __construct(
		Config $wikiConfig,
		MessageLocalizer $messageLocalizer,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup
	) {
		$this->wikiConfig = $wikiConfig;
		$this->messageLocalizer = $messageLocalizer;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
	}

	private function loadSettings( UserIdentity $user ): array {
		return FormatJson::decode( $this->userOptionsLookup->getOption(
			$user, self::PREF_NAME
		), true ) ?? [];
	}

	public function toArray( UserIdentity $user ): array {
		$conditions = $this->getPraiseworthyConditions( $user );
		return array_merge( [
			self::SETTING_MESSAGE_SUBJECT => $this->getPraisingMessageDefaultSubject( $user ),
			self::SETTING_MESSAGE_TEXT => $this->getPraisingMessageContent( $user ),
			self::SETTING_NOTIFICATION_FREQUENCY => $this->getNotificationsFrequency( $user ),
		], $conditions->jsonSerialize() );
	}

	/**
	 * Simulation of (?int)$value
	 *
	 * @note Could be replaced with https://wiki.php.net/rfc/nullable-casting, if it ever becomes
	 * a part of PHP.
	 * @param mixed $value
	 * @return int|null Null if $value is null, otherwise (int)$value.
	 */
	private function castToNullableInt( $value ): ?int {
		if ( $value === null ) {
			return null;
		}

		return (int)$value;
	}

	/**
	 * Get praiseworthy conditions for a mentor
	 *
	 * Defaults are provided by Community configuration as:
	 *
	 *  1) GEPersonalizedPraiseMaxEdits: the maximum number of edits a mentee must have to be
	 *     praiseworthy
	 *  2) GEPersonalizedPraiseMinEdits: the minimum number of edits a mentee must have to be
	 *     praiseworthy
	 *  3) GEPersonalizedPraiseDays: to be considered praiseworthy, a mentee needs to make a
	 *     certain number of edits (see above) in this amount of days to be praiseworthy.
	 *
	 * @param UserIdentity $user
	 * @return PraiseworthyConditions
	 */
	public function getPraiseworthyConditions( UserIdentity $user ): PraiseworthyConditions {
		$settings = $this->loadSettings( $user );

		return new PraiseworthyConditions(
			(int)( $settings[PraiseworthyConditions::SETTING_MAX_EDITS] ??
				$this->wikiConfig->get( 'GEPersonalizedPraiseMaxEdits' ) ),
			(int)( $settings[PraiseworthyConditions::SETTING_MIN_EDITS] ??
				$this->wikiConfig->get( 'GEPersonalizedPraiseMinEdits' ) ),
			$this->castToNullableInt( $settings[PraiseworthyConditions::SETTING_MAX_REVERTS] ??
				$this->wikiConfig->get( 'GEPersonalizedPraiseMaxReverts' ) ),
			(int)( $settings[PraiseworthyConditions::SETTING_DAYS] ??
				$this->wikiConfig->get( 'GEPersonalizedPraiseDays' ) ),
		);
	}

	/**
	 * Get default subject for the praising message
	 *
	 * @param UserIdentity $user
	 * @return string
	 */
	public function getPraisingMessageDefaultSubject( UserIdentity $user ): string {
		return $this->loadSettings( $user )[ self::SETTING_MESSAGE_SUBJECT ] ?? $this->messageLocalizer->msg(
			'growthexperiments-mentor-dashboard-personalized-praise-praise-message-title'
		)->inContentLanguage()->text();
	}

	/**
	 * Get user subpage title where the user-specific praising message is stored
	 *
	 * Unlike all other options in PersonalizedPraiseSettings, the praising message is stored on
	 * a user subpage, to make use of native MediaWiki preloading.
	 *
	 * Title returned by this method is not guaranteed to be known.
	 *
	 * @param UserIdentity $user
	 * @return Title
	 */
	public function getPraisingMessageUserTitle( UserIdentity $user ): Title {
		return $this->userFactory->newFromUserIdentity( $user )
			->getUserPage()
			->getSubpage( self::USER_MESSAGE_PRELOAD_SUBPAGE_NAME );
	}

	/**
	 * Get title that currently defines where the praising message is defined
	 *
	 * If it exists, a subpage of the $user is returned. Otherwise, a page in NS_MEDIAWIKI is
	 * returned instead.
	 *
	 * @param UserIdentity $user
	 * @return Title
	 */
	public function getPraisingMessageTitle( UserIdentity $user ): Title {
		$userSubpage = $this->getPraisingMessageUserTitle( $user );
		return $userSubpage->exists() ? $userSubpage : $this->titleFactory->newFromTextThrow(
			'growthexperiments-mentor-dashboard-personalized-praise-praise-message-message',
			NS_MEDIAWIKI
		);
	}

	public function getPraisingMessageContent( UserIdentity $user ): string {
		$revision = $this->revisionLookup->getRevisionByTitle( $this->getPraisingMessageTitle( $user ) );
		if ( !$revision ) {
			return '';
		}
		$content = $revision->getContent( SlotRecord::MAIN );
		if ( !$content instanceof WikitextContent ) {
			return '';
		}

		return $content->getText();
	}

	/**
	 * How frequently should the user be notified about new praiseworthy mentees?
	 *
	 * @param UserIdentity $user
	 * @return int Minimum number of hours that needs to pass since the last notification
	 * (values specified by PersonalizedPraiseSettings::NOTIFY_* constants have special meaning)
	 */
	public function getNotificationsFrequency( UserIdentity $user ): int {
		return $this->loadSettings( $user )[self::SETTING_NOTIFICATION_FREQUENCY]
			?? (int)$this->wikiConfig->get( 'GEPersonalizedPraiseDefaultNotificationsFrequency' );
	}
}
