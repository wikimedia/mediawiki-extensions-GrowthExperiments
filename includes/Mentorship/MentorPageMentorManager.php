<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\WikiConfigException;
use JobQueueGroup;
use Language;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsManager;
use MessageLocalizer;
use ParserOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use TitleFactory;
use User;
use UserArray;
use UserOptionsUpdateJob;
use WikiPage;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use WikitextContent;

class MentorPageMentorManager implements MentorManager, LoggerAwareInterface {
	use LoggerAwareTrait;

	/** @var string User preference for storing the mentor. */
	public const MENTOR_PREF = 'growthexperiments-mentor-id';

	/** @var int Maximum mentor intro length. */
	private const INTRO_TEXT_LENGTH = 240;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var Language */
	private $language;

	/** @var string */
	private $mentorsPageName;

	/** @var string */
	private $manuallyAssignedMentorsPageName;

	/**
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UserFactory $userFactory
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserNameUtils $userNameUtils
	 * @param MessageLocalizer $messageLocalizer
	 * @param Language $language
	 * @param string $mentorsPageName Title of the page which contains the list of available mentors.
	 *   See the documentation of the GEHomepageMentorsList config variable for format.
	 * @param string $manuallyAssignedMentorsPageName Title of the page which contains the list of automatically
	 *   assigned mentors (may be an empty string if no such page exists).
	 *   See the documentation for GEHomepageManualAssignmentMentorsList for format.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		UserFactory $userFactory,
		UserOptionsManager $userOptionsManager,
		UserNameUtils $userNameUtils,
		MessageLocalizer $messageLocalizer,
		Language $language,
		string $mentorsPageName,
		string $manuallyAssignedMentorsPageName
	) {
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->userFactory = $userFactory;
		$this->userOptionsManager = $userOptionsManager;
		$this->userNameUtils = $userNameUtils;
		$this->messageLocalizer = $messageLocalizer;
		$this->language = $language;
		$this->mentorsPageName = $mentorsPageName;
		$this->manuallyAssignedMentorsPageName = $manuallyAssignedMentorsPageName;
		$this->setLogger( new NullLogger() );
	}

	/** @inheritDoc */
	public function getMentorForUser( UserIdentity $user ): Mentor {
		$mentorUser = $this->loadMentorUser( $user );
		if ( !$mentorUser ) {
			$mentorUser = $this->getRandomAvailableMentor( $user );
			$this->setMentorForUser( $user, $mentorUser );
		}
		return new Mentor( $this->userFactory->newFromUserIdentity( $mentorUser ),
			$this->getMentorIntroText( $mentorUser, $user ) );
	}

	/** @inheritDoc */
	public function getMentorForUserSafe( UserIdentity $user ): ?Mentor {
		try {
			return $this->getMentorForUser( $user );
		} catch ( WikiConfigException $e ) {
			$this->logger->warning( $e->getMessage() );
		}
		return null;
	}

	/** @inheritDoc */
	public function setMentorForUser( UserIdentity $user, UserIdentity $mentor ): void {
		// We cannot use a master connection on what is possibly a GET request, so save via the
		// job queue.
		// But set the option immediately in UserOptionsManager's in-process cache to avoid
		// race conditions.
		$this->userOptionsManager->setOption( $user, static::MENTOR_PREF, $mentor->getId() );
		JobQueueGroup::singleton()->lazyPush( new UserOptionsUpdateJob( [
			'userId' => $user->getId(),
			'options' => [ static::MENTOR_PREF => $mentor->getId() ]
		] ) );
	}

	/**
	 * Helper method returning a list of mentors listed at a specified page
	 *
	 * @param WikiPage $page Page to work with
	 * @return array
	 */
	private function getMentorsForPage( WikiPage $page ): array {
		$links = $page->getParserOutput( ParserOptions::newCanonical( 'canonical' ) )->getLinks();
		if ( !isset( $links[ NS_USER ] ) ) {
			$this->logger->info( __METHOD__ . ' found zero mentors, no links at {mentorsList}', [
				'mentorsList' => $this->mentorsPageName
			] );
			return [];
		}

		$mentorsRaw = array_keys( $links[ NS_USER ] );
		foreach ( $mentorsRaw as &$username ) {
			$canonical = $this->userNameUtils->getCanonical( $username );
			if ( $canonical === false ) {
				continue;
			}
			$username = $canonical;
		}
		unset( $username );

		// FIXME should be a service
		$userArr = UserArray::newFromNames( $mentorsRaw );
		$mentors = [];
		foreach ( $userArr as $user ) {
			if ( $user->getId() ) {
				$mentors[] = $user->getName();
			}
		}

		return $mentors;
	}

	/** @inheritDoc */
	public function getMentors(): array {
		return array_unique(
			array_merge(
				$this->getAvailableMentors(),
				$this->getManuallyAssignedMentors()
			)
		);
	}

	/** @inheritDoc */
	public function getAvailableMentors(): array {
		return $this->getMentorsForPage( $this->getMentorsPage() );
	}

	/** @inheritDoc */
	public function getManuallyAssignedMentors(): array {
		$page = $this->getManuallyAssignedMentorsPage();
		if ( $page === null ) {
			return [];
		}

		return $this->getMentorsForPage( $page );
	}

	/**
	 * Try to load the current mentor of the user.
	 * @param UserIdentity $mentee
	 * @return UserIdentity|null The current user's mentor or null if they don't have one
	 */
	private function loadMentorUser( UserIdentity $mentee ): ?UserIdentity {
		$mentorId = $this->userOptionsManager->getIntOption( $mentee, static::MENTOR_PREF );
		$user = $this->userFactory->newFromId( $mentorId );
		$user->load();
		return $user->isRegistered() ? $user : null;
	}

	/**
	 * Randomly selects a mentor from the available mentors.
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity[] $excluded A list of users who should not be selected.
	 * @return User The selected mentor.
	 * @throws WikiConfigException When no mentors are available.
	 */
	private function getRandomAvailableMentor(
		UserIdentity $mentee, array $excluded = []
	): UserIdentity {
		$availableMentors = $this->getAvailableMentors();
		if ( count( $availableMentors ) === 0 ) {
			throw new WikiConfigException(
				'Mentorship: no mentor available for user ' . $mentee->getName()
			);
		}
		$availableMentors = array_values( array_diff( $availableMentors,
			array_map( function ( UserIdentity $excludedUser ) {
				return $excludedUser->getName();
			}, $excluded )
		) );
		if ( count( $availableMentors ) === 0 ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available for ' .
				$mentee->getName() .
				' but excluded users'
			);
		}
		$availableMentors = array_values( array_diff( $availableMentors, [ $mentee->getName() ] ) );
		if ( count( $availableMentors ) === 0 ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available for ' .
				$mentee->getName() .
				' but themselves'
			);
		}

		$selectedMentorName = $availableMentors[ rand( 0, count( $availableMentors ) - 1 ) ];
		$result = $this->userFactory->newFromName( $selectedMentorName );
		if ( $result === null ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available for ' .
				$mentee->getName()
			);
		}

		return $result;
	}

	/**
	 * Get the Title object for the mentor page.
	 * @return WikiPage A page that's guaranteed to exist.
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 */
	private function getMentorsPage(): WikiPage {
		$title = $this->titleFactory->newFromText( $this->mentorsPageName );
		if ( !$title || !$title->exists() ) {
			throw new WikiConfigException( 'wgGEHomepageMentorsList is invalid: ' . $this->mentorsPageName );
		}
		return $this->wikiPageFactory->newFromTitle( $title );
	}

	/**
	 * Get the Title object for the manually assigned mentor page.
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 * @return WikiPage|null A page that's guaranteed to exist, or null if impossible to get.
	 */
	private function getManuallyAssignedMentorsPage(): ?WikiPage {
		if ( $this->manuallyAssignedMentorsPageName === '' ) {
			return null;
		}

		$title = $this->titleFactory->newFromText( $this->manuallyAssignedMentorsPageName );

		if ( !$title || !$title->exists() ) {
			throw new WikiConfigException(
				'wgGEHomepageMentorsList is invalid: ' . $this->manuallyAssignedMentorsPageName
			);
		}

		return $this->wikiPageFactory->newFromTitle( $title );
	}

	/**
	 * Get the description used for presenting the mentor to the mentee.
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 * @return string
	 * @throws WikiConfigException If the mentor intro text cannot be fetched due to misconfiguration.
	 */
	private function getMentorIntroText( UserIdentity $mentor, UserIdentity $mentee ) {
		return $this->getCustomMentorIntroText( $mentor )
			   ?? $this->getDefaultMentorIntroText( $mentor, $mentee );
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 * @return string
	 */
	private function getDefaultMentorIntroText( UserIdentity $mentor, UserIdentity $mentee ) {
		return $this->messageLocalizer
			->msg( 'growthexperiments-homepage-mentorship-intro' )
			->params( $mentor->getName() )
			->params( $mentee->getName() )
			->text();
	}

	/**
	 * Custom mentor intro text which mentors can set on the mentor page.
	 * @param UserIdentity $mentor
	 * @return string|null Null when no custom text has been set for this mentor.
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 */
	private function getCustomMentorIntroText( UserIdentity $mentor ) {
		// Use \h (horizontal whitespace) instead of \s (whitespace) to avoid matching newlines (T227535)
		preg_match(
			sprintf( '/:%s]]\h*\|\h*(.*)/', preg_quote( $mentor->getName(), '/' ) ),
			$this->getMentorsPageContent(),
			$matches
		);
		$introText = $matches[1] ?? '';
		if ( $introText === '' ) {
			return null;
		}

		return $this->messageLocalizer->msg( 'quotation-marks' )
			->rawParams( $this->language->truncateForVisual( $introText, self::INTRO_TEXT_LENGTH ) )
			->text();
	}

	/**
	 * Get the text of the mentor page.
	 * @return string
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 */
	private function getMentorsPageContent() {
		$page = $this->getMentorsPage();
		/** @var $content WikitextContent */
		$content = $page->getContent();
		// @phan-suppress-next-line PhanUndeclaredMethod
		return $content->getText();
	}

}
