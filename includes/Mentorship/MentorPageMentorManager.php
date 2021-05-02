<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Mentorship\Store\PreferenceMentorStore;
use GrowthExperiments\WikiConfigException;
use Language;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use ParserOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use TitleFactory;
use User;
use Wikimedia\Rdbms\DBReadOnlyError;
use WikiPage;
use WikitextContent;

class MentorPageMentorManager extends MentorManager implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * @var string User preference for storing the mentor.
	 * @deprecated since 1.36, use PreferenceMentorStore::MENTOR_PREF instead
	 */
	public const MENTOR_PREF = PreferenceMentorStore::MENTOR_PREF;

	/** @var int Maximum mentor intro length. */
	private const INTRO_TEXT_LENGTH = 240;

	private $mentorStore;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var bool */
	private $wasPosted;

	/** @var Language */
	private $language;

	/** @var string|null */
	private $mentorsPageName;

	/** @var string|null */
	private $manuallyAssignedMentorsPageName;

	/**
	 * @param MentorStore $mentorStore
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UserFactory $userFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param MessageLocalizer $messageLocalizer
	 * @param Language $language
	 * @param string|null $mentorsPageName Title of the page which contains the list of available mentors.
	 *   See the documentation of the GEHomepageMentorsList config variable for format. May be null if no
	 *   such page exists.
	 * @param string|null $manuallyAssignedMentorsPageName Title of the page which contains the list of automatically
	 *   assigned mentors. May be null if no such page exists.
	 *   See the documentation for GEHomepageManualAssignmentMentorsList for format.
	 * @param bool $wasPosted Is this a POST request?
	 */
	public function __construct(
		MentorStore $mentorStore,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		UserFactory $userFactory,
		UserNameUtils $userNameUtils,
		UserIdentityLookup $userIdentityLookup,
		MessageLocalizer $messageLocalizer,
		Language $language,
		?string $mentorsPageName,
		?string $manuallyAssignedMentorsPageName,
		$wasPosted
	) {
		$this->mentorStore = $mentorStore;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->messageLocalizer = $messageLocalizer;
		$this->language = $language;
		$this->mentorsPageName = $mentorsPageName;
		$this->manuallyAssignedMentorsPageName = $manuallyAssignedMentorsPageName;
		$this->wasPosted = $wasPosted;

		$this->setLogger( new NullLogger() );
	}

	/** @inheritDoc */
	public function getMentorForUserIfExists( UserIdentity $user ): ?Mentor {
		$mentorUser = $this->mentorStore->loadMentorUser( $user );
		if ( !$mentorUser ) {
			return null;
		}

		return new Mentor(
			$this->userFactory->newFromUserIdentity( $mentorUser ),
			$this->getMentorIntroText( $mentorUser, $user )
		);
	}

	/** @inheritDoc */
	public function getMentorForUser( UserIdentity $user ): Mentor {
		$mentorUser = $this->mentorStore->loadMentorUser( $user );
		if ( !$mentorUser ) {
			$mentorUser = $this->getRandomAutoAssignedMentor( $user );
			$this->mentorStore->setMentorForUser( $user, $mentorUser );
		}
		return new Mentor( $this->userFactory->newFromUserIdentity( $mentorUser ),
			$this->getMentorIntroText( $mentorUser, $user ) );
	}

	/** @inheritDoc */
	public function getMentorForUserSafe( UserIdentity $user ): ?Mentor {
		try {
			return $this->getMentorForUser( $user );
		} catch ( WikiConfigException $e ) {
			// WikiConfigException is thrown when no mentor is available
			// Log as info level, as not-yet-developed wikis may have
			// zero mentors for long period of time (T274035)
			$this->logger->info( 'No mentor available for {user}', [
				'user' => $user->getName(),
				'exception' => $e
			] );
		} catch ( DBReadOnlyError $e ) {
			// Just pretend the user doesn't have a mentor. It will be set later, and often
			// this call is made in the context of something not specifically mentorship-
			// related, such as the homepage, so it's better than erroring out.
		}
		return null;
	}

	/**
	 * Helper method returning a list of mentors listed at a specified page
	 *
	 * @param WikiPage|null $page Page to work with or null if no page is provided
	 * @return array
	 */
	private function getMentorsForPage( ?WikiPage $page ): array {
		if ( $page === null ) {
			return [];
		}

		$links = $page->getParserOutput( ParserOptions::newCanonical( 'canonical' ) )->getLinks();
		if ( !isset( $links[ NS_USER ] ) ) {
			$this->logger->info( __METHOD__ . ' found zero mentors, no links at {mentorsList}', [
				'mentorsList' => $page->getTitle()->getPrefixedText()
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

		return $this->userIdentityLookup
			->newSelectQueryBuilder()
			->userNames( $mentorsRaw )
			->registered()
			->fetchUserNames();
	}

	/** @inheritDoc */
	public function getAutoAssignedMentors(): array {
		return $this->getMentorsForPage( $this->getMentorsPage() );
	}

	/** @inheritDoc */
	public function getManuallyAssignedMentors(): array {
		return $this->getMentorsForPage( $this->getManuallyAssignedMentorsPage() );
	}

	/**
	 * Randomly selects a mentor from the available mentors.
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity[] $excluded A list of users who should not be selected.
	 * @return User The selected mentor.
	 * @throws WikiConfigException When no mentors are available.
	 */
	private function getRandomAutoAssignedMentor(
		UserIdentity $mentee, array $excluded = []
	): UserIdentity {
		$autoAssignedMentors = $this->getAutoAssignedMentors();
		if ( count( $autoAssignedMentors ) === 0 ) {
			throw new WikiConfigException(
				'Mentorship: no mentor available for user ' . $mentee->getName()
			);
		}
		$autoAssignedMentors = array_values( array_diff( $autoAssignedMentors,
			array_map( function ( UserIdentity $excludedUser ) {
				return $excludedUser->getName();
			}, $excluded )
		) );
		if ( count( $autoAssignedMentors ) === 0 ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available' .
				' but excluded users'
			);
		}
		$autoAssignedMentors = array_values( array_diff( $autoAssignedMentors, [ $mentee->getName() ] ) );
		if ( count( $autoAssignedMentors ) === 0 ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available for a user' .
				' but themselves'
			);
		}

		$selectedMentorName = $autoAssignedMentors[ rand( 0, count( $autoAssignedMentors ) - 1 ) ];
		$result = $this->userFactory->newFromName( $selectedMentorName );
		if ( $result === null ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available'
			);
		}

		return $result;
	}

	/**
	 * Get the WikiPage object for the mentor page.
	 * @return WikiPage|null A page that's guaranteed to exist or null when no mentors page available
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 */
	private function getMentorsPage(): ?WikiPage {
		if ( $this->mentorsPageName === null ) {
			return null;
		}

		$title = $this->titleFactory->newFromText( $this->mentorsPageName );
		if ( !$title || !$title->exists() ) {
			throw new WikiConfigException( 'wgGEHomepageMentorsList is invalid: ' . $this->mentorsPageName );
		}
		return $this->wikiPageFactory->newFromTitle( $title );
	}

	/**
	 * Get the WikiPage object for the manually assigned mentor page.
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 * @return WikiPage|null A page that's guaranteed to exist, or null if impossible to get.
	 */
	public function getManuallyAssignedMentorsPage(): ?WikiPage {
		if ( $this->manuallyAssignedMentorsPageName === null ) {
			return null;
		}

		$title = $this->titleFactory->newFromText( $this->manuallyAssignedMentorsPageName );

		if ( !$title || !$title->exists() ) {
			throw new WikiConfigException(
				'wgGEHomepageManualAssignmentMentorsList is invalid: ' . $this->manuallyAssignedMentorsPageName
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
		if ( $page === null ) {
			return "";
		}

		/** @var $content WikitextContent */
		$content = $page->getContent();
		// @phan-suppress-next-line PhanUndeclaredMethod
		return $content->getText();
	}

}
