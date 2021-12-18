<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use Language;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use ParserOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Title;
use TitleFactory;
use Wikimedia\Rdbms\DBReadOnlyError;
use WikiPage;
use WikitextContent;

class MentorPageMentorManager extends MentorManager implements LoggerAwareInterface {
	use LoggerAwareTrait;

	public const MENTORSHIP_ENABLED_PREF = 'growthexperiments-homepage-mentorship-enabled';

	/** @var int Maximum mentor intro length. */
	private const INTRO_TEXT_LENGTH = 240;

	/** @var MentorStore */
	private $mentorStore;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/** @var MentorWeightManager */
	private $mentorWeightManager;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

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
	 * @param MentorStatusManager $mentorStatusManager
	 * @param MentorWeightManager $mentorWeightManager
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserOptionsLookup $userOptionsLookup
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
		MentorStatusManager $mentorStatusManager,
		MentorWeightManager $mentorWeightManager,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		UserNameUtils $userNameUtils,
		UserIdentityLookup $userIdentityLookup,
		UserOptionsLookup $userOptionsLookup,
		Language $language,
		?string $mentorsPageName,
		?string $manuallyAssignedMentorsPageName,
		$wasPosted
	) {
		$this->mentorStore = $mentorStore;
		$this->mentorStatusManager = $mentorStatusManager;
		$this->mentorWeightManager = $mentorWeightManager;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->language = $language;
		$this->mentorsPageName = $mentorsPageName;
		$this->manuallyAssignedMentorsPageName = $manuallyAssignedMentorsPageName;
		$this->wasPosted = $wasPosted;

		$this->setLogger( new NullLogger() );
	}

	/** @inheritDoc */
	public function getMentorForUserIfExists(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor {
		$mentorUser = $this->mentorStore->loadMentorUser( $user, $role );
		if ( !$mentorUser ) {
			return null;
		}

		return $this->newMentorFromUserIdentity( $mentorUser, $user );
	}

	/** @inheritDoc */
	public function getMentorForUser(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): Mentor {
		$mentorUser = $this->mentorStore->loadMentorUser( $user, $role );

		if (
			$mentorUser !== null &&
			$role === MentorStore::ROLE_BACKUP &&
			$this->mentorStatusManager->getMentorStatus( $mentorUser ) === MentorStatusManager::STATUS_AWAY
		) {
			// Do not let backup mentors to be away. If they are, drop the relationship and set
			// it again. Logic in getRandomAutoAssignedMentorForUserAndRole will prevent us from
			// getting $mentorUser again, so no need to remember it and exclude manually.
			$mentorUser = null;
		}

		if ( !$mentorUser ) {
			$mentorUser = $this->getRandomAutoAssignedMentorForUserAndRole( $user, $role );
			if ( !$mentorUser ) {
				// TODO: Remove this call (T290371)
				throw new WikiConfigException( 'Mentorship: No mentor available' );
			}
			$this->mentorStore->setMentorForUser( $user, $mentorUser, $role );
		}
		return $this->newMentorFromUserIdentity( $mentorUser, $user );
	}

	/**
	 * Wrapper for getRandomAutoAssignedMentor
	 *
	 * In addition to getRandomAutoAssignedMentor, this is mentor role-aware,
	 * and automatically excludes the primary mentor if generating a mentor
	 * for a non-primary role.
	 *
	 * If $role is ROLE_BACKUP, it also makes sure to not generate a mentor that's away.
	 *
	 * @param UserIdentity $mentee
	 * @param string $role One of MentorStore::ROLE_* roles
	 * @return UserIdentity|null Mentor that can be assigned to the mentee
	 * @throws WikiConfigException if mentor list configuration is invalid
	 */
	private function getRandomAutoAssignedMentorForUserAndRole(
		UserIdentity $mentee,
		string $role
	): ?UserIdentity {
		$excludedUsers = [];
		if ( $role !== MentorStore::ROLE_PRIMARY ) {
			$primaryMentor = $this->mentorStore->loadMentorUser(
				$mentee,
				MentorStore::ROLE_PRIMARY
			);
			if ( $primaryMentor ) {
				$excludedUsers[] = $primaryMentor;
			}
		}
		if ( $role === MentorStore::ROLE_BACKUP ) {
			$excludedUsers = array_merge(
				$excludedUsers,
				$this->mentorStatusManager->getAwayMentors()
			);
		}

		return $this->getRandomAutoAssignedMentor( $mentee, $excludedUsers );
	}

	/** @inheritDoc */
	public function getMentorForUserSafe(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor {
		try {
			return $this->getMentorForUser( $user, $role );
		} catch ( WikiConfigException $e ) {
			// WikiConfigException is thrown when no mentor is available
			// Log as info level, as not-yet-developed wikis may have
			// zero mentors for long period of time (T274035)
			$this->logger->info( 'No {role} mentor available for {user}', [
				'user' => $user->getName(),
				'role' => $role,
				'exception' => $e
			] );
		} catch ( DBReadOnlyError $e ) {
			// Just pretend the user doesn't have a mentor. It will be set later, and often
			// this call is made in the context of something not specifically mentorship-
			// related, such as the homepage, so it's better than erroring out.
		}
		return null;
	}

	/** @inheritDoc */
	public function getEffectiveMentorForUser( UserIdentity $menteeUser ): Mentor {
		$primaryMentor = $this->getMentorForUser( $menteeUser, MentorStore::ROLE_PRIMARY );
		if (
			$this->mentorStatusManager
				->getMentorStatus( $primaryMentor->getMentorUser() ) === MentorStatusManager::STATUS_ACTIVE
		) {
			return $primaryMentor;
		} else {
			return $this->getMentorForUser( $menteeUser, MentorStore::ROLE_BACKUP );
		}
	}

	/** @inheritDoc */
	public function getEffectiveMentorForUserSafe( UserIdentity $menteeUser ): ?Mentor {
		$primaryMentor = $this->getMentorForUserSafe( $menteeUser, MentorStore::ROLE_PRIMARY );
		if ( $primaryMentor === null ) {
			// If primary mentor cannot be assigned, there's zero chance to successfully assign any
			// mentor.
			return null;
		}

		if (
			$this->mentorStatusManager
				->getMentorStatus( $primaryMentor->getMentorUser() ) === MentorStatusManager::STATUS_ACTIVE
		) {
			return $primaryMentor;
		} else {
			return $this->getMentorForUserSafe( $menteeUser, MentorStore::ROLE_BACKUP );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function newMentorFromUserIdentity(
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor {
		return new Mentor(
			$mentorUser,
			$this->getMentorIntroText( $mentorUser, $menteeUser ?? $mentorUser )
		);
	}

	/**
	 * Helper method returning a list of mentors listed at a specified page
	 *
	 * @param WikiPage|null $page Page to work with or null if no page is provided
	 * @return string[]
	 */
	private function getMentorsForPage( ?WikiPage $page ): array {
		if ( $page === null ) {
			return [];
		}

		$links = $page->getParserOutput( ParserOptions::newFromAnon() )->getLinks();
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
			->whereUserNames( $mentorsRaw )
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

	/** @inheritDoc */
	public function invalidateCache(): void {
		$autoMentorsList = $this->getAutoMentorsListTitle();
		if ( $autoMentorsList && $autoMentorsList->exists() ) {
			// only invalidate cache if the list exists, otherwise,
			// makeCacheKeyWeightedAutoAssignedMentors would fail.
			$this->cache->delete(
				$this->makeCacheKeyWeightedAutoAssignedMentors()
			);
		}
	}

	/**
	 * @return string
	 * @throws WikiConfigException
	 */
	private function makeCacheKeyWeightedAutoAssignedMentors(): string {
		return $this->cache->makeKey(
			'GrowthExperiments',
			__CLASS__,
			'WeightedMentors',
			$this->getMentorsPage()->getId()
		);
	}

	/**
	 * @return array
	 * @throws WikiConfigException
	 */
	private function getWeightedAutoAssignedMentors(): array {
		if ( $this->getMentorsPage() === null ) {
			return [];
		}

		return $this->cache->getWithSetCallback(
			$this->makeCacheKeyWeightedAutoAssignedMentors(),
			$this->cacheTtl,
			function () {
				$mentors = $this->getAutoAssignedMentors();
				if ( $mentors === [] ) {
					return [];
				}
				return array_merge( ...array_map(
					function ( string $mentorText ) {
						$mentor = $this->userIdentityLookup->getUserIdentityByName(
							$mentorText
						);
						if ( !$mentor ) {
							// return empty array, mentor is not valid
							return [];
						}
						return array_fill(
							0,
							$this->mentorWeightManager->getWeightForMentor( $mentor ),
							$mentorText
						);
					},
					$mentors
				) );
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getRandomAutoAssignedMentor(
		UserIdentity $mentee, array $excluded = []
	): ?UserIdentity {
		$autoAssignedMentors = $this->getWeightedAutoAssignedMentors();
		if ( count( $autoAssignedMentors ) === 0 ) {
			$this->logger->debug(
				'Mentorship: No mentor available for user {user}',
				[
					'user' => $mentee->getName()
				]
			);
			return null;
		}
		$autoAssignedMentors = array_values( array_diff( $autoAssignedMentors,
			array_map( static function ( UserIdentity $excludedUser ) {
				return $excludedUser->getName();
			}, $excluded )
		) );
		if ( count( $autoAssignedMentors ) === 0 ) {
			$this->logger->debug(
				'Mentorship: No mentor available for {user} but excluded users',
				[
					'user' => $mentee->getName()
				]
			);
			return null;
		}
		$autoAssignedMentors = array_values( array_diff( $autoAssignedMentors, [ $mentee->getName() ] ) );
		if ( count( $autoAssignedMentors ) === 0 ) {
			$this->logger->debug(
				'Mentorship: No mentor available for {user} but themselves',
				[
					'user' => $mentee->getName()
				]
			);
			return null;
		}

		$selectedMentorName = $autoAssignedMentors[ rand( 0, count( $autoAssignedMentors ) - 1 ) ];
		$result = $this->userIdentityLookup->getUserIdentityByName( $selectedMentorName );
		if ( $result === null ) {
			throw new WikiConfigException(
				'Mentorship: Mentor {user} does not have a valid username',
				[ 'user' => $selectedMentorName ]
			);
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function getAutoMentorsListTitle(): ?Title {
		if ( $this->mentorsPageName === null ) {
			return null;
		}

		$title = $this->titleFactory->newFromText( $this->mentorsPageName );
		if ( !$title ) {
			// TitleFactory failed to construct a title object -- the configured list must be invalid
			throw new WikiConfigException( 'wgGEHomepageMentorsList is invalid: {page}',
				[ 'page' => $this->mentorsPageName ] );
		}
		return $title;
	}

	/**
	 * @inheritDoc
	 */
	public function getManualMentorsListTitle(): ?Title {
		if ( $this->manuallyAssignedMentorsPageName === null ) {
			return null;
		}

		$title = $this->titleFactory->newFromText( $this->manuallyAssignedMentorsPageName );
		if ( !$title ) {
			throw new WikiConfigException(
				'wgGEHomepageManualAssignmentMentorsList is invalid: {page}',
				[ 'page' => $this->manuallyAssignedMentorsPageName ]
			);
		}
		return $title;
	}

	/**
	 * Get the WikiPage object for the mentor page.
	 * @return WikiPage|null A page that's guaranteed to exist or null when no mentors page available
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 */
	private function getMentorsPage(): ?WikiPage {
		$title = $this->getAutoMentorsListTitle();
		if ( !$title ) {
			// page was not configured -- configuration is valid, do not throw
			return null;
		}
		if ( !$title->exists() ) {
			// page does not exist, throw WikiConfigException
			throw new WikiConfigException(
				'Page defined by wgGEHomepageMentorsList does not exist: {page}',
				[ 'page' => $this->mentorsPageName ]
			);
		}

		return $this->wikiPageFactory->newFromTitle( $title );
	}

	/**
	 * Get the WikiPage object for the manually assigned mentor page.
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 * @return WikiPage|null A page that's guaranteed to exist, or null if impossible to get.
	 */
	public function getManuallyAssignedMentorsPage(): ?WikiPage {
		$title = $this->getManualMentorsListTitle();

		if ( !$title ) {
			// page was not configured -- configuration is valid, do not throw
			return null;
		}
		if ( !$title->exists() ) {
			throw new WikiConfigException(
				'wgGEHomepageManualAssignmentMentorsList is invalid: {page}',
				[ 'page' => $this->manuallyAssignedMentorsPageName ]
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
		return wfMessage( 'growthexperiments-homepage-mentorship-intro' )
			->inContentLanguage()
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

		return wfMessage( 'quotation-marks' )
			->inContentLanguage()
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

	/**
	 * @inheritDoc
	 */
	public function getMentorshipStateForUser( UserIdentity $user ): int {
		$state = $this->userOptionsLookup->getIntOption( $user, self::MENTORSHIP_ENABLED_PREF );
		if ( !in_array( $state, self::MENTORSHIP_STATES ) ) {
			// default to MENTORSHIP_DISABLED and log an error
			$this->logger->error(
				'User {user} has invalid value of {property} user property',
				[
					'user' => $user->getName(),
					'property' => self::MENTORSHIP_ENABLED_PREF,
					'impact' => 'defaulting to MENTORSHIP_DISABLED'
				]
			);
			return self::MENTORSHIP_DISABLED;
		}

		return $state;
	}
}
