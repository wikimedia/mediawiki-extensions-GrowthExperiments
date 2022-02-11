<?php

namespace GrowthExperiments\Mentorship\Provider;

use BagOStuff;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\WikiConfigException;
use Language;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use ParserOptions;
use Title;
use TitleFactory;
use WANObjectCache;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use WikiPage;
use WikitextContent;

class WikitextMentorProvider extends MentorProvider implements ExpirationAwareness {

	/** @var WANObjectCache */
	private $wanCache;

	/** @var BagOStuff */
	private $localServerCache;

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

	/** @var Language */
	private $language;

	/** @var string|null */
	private $mentorsPageName;

	/** @var string|null */
	private $manuallyAssignedMentorsPageName;

	/**
	 * @param WANObjectCache $wanCache
	 * @param BagOStuff $localServerCache
	 * @param MentorWeightManager $mentorWeightManager
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param Language $language
	 * @param string|null $mentorsPageName Title of the page which contains the list of available mentors.
	 *   See the documentation of the GEHomepageMentorsList config variable for format. May be null if no
	 *   such page exists.
	 * @param string|null $manuallyAssignedMentorsPageName Title of the page which contains the list of automatically
	 *   assigned mentors. May be null if no such page exists.
	 *   See the documentation for GEHomepageManualAssignmentMentorsList for format.
	 */
	public function __construct(
		WANObjectCache $wanCache,
		BagOStuff $localServerCache,
		MentorWeightManager $mentorWeightManager,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		UserNameUtils $userNameUtils,
		UserIdentityLookup $userIdentityLookup,
		Language $language,
		?string $mentorsPageName,
		?string $manuallyAssignedMentorsPageName
	) {
		parent::__construct();

		$this->wanCache = $wanCache;
		$this->localServerCache = $localServerCache;
		$this->mentorWeightManager = $mentorWeightManager;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->language = $language;
		$this->mentorsPageName = $mentorsPageName;
		$this->manuallyAssignedMentorsPageName = $manuallyAssignedMentorsPageName;
	}

	/**
	 * @inheritDoc
	 */
	public function getSignupTitle(): ?Title {
		return $this->getAutoMentorsListTitle();
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
	 * @inheritDoc
	 */
	public function getSourceTitles(): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn
		return array_values(
			array_filter( [
				$this->getAutoMentorsListTitle(),
				$this->getManualMentorsListTitle()
			], static function ( ?Title $el ) {
				return $el !== null;
			} )
		);
	}

	/**
	 * Invalidate the WAN cache
	 */
	public function invalidateCache(): void {
		$autoMentorsList = $this->getAutoMentorsListTitle();
		if ( $autoMentorsList && $autoMentorsList->exists() ) {
			$this->wanCache->delete(
				$this->makeCacheKeyMentorsForPage( $autoMentorsList )
			);
			$this->wanCache->delete(
				$this->makeCacheKeyWeightedAutoAssignedMentors()
			);
		}

		$manualMentorsList = $this->getManualMentorsListTitle();
		if ( $manualMentorsList && $manualMentorsList->exists() ) {
			$this->wanCache->delete(
				$this->makeCacheKeyMentorsForPage( $manualMentorsList )
			);
		}
	}

	/**
	 * Get page with a list of automatically assigned mentors
	 * @return Title|null
	 * @throws WikiConfigException if mentor list cannot be found or is misconfigured
	 */
	private function getAutoMentorsListTitle(): ?Title {
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
	 * Get page with a list of manually assigned mentors
	 * @return Title|null
	 * @throws WikiConfigException if mentor list cannot be found or is misconfigured
	 */
	private function getManualMentorsListTitle(): ?Title {
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
			throw new WikiConfigException( 'Page defined by wgGEHomepageMentorsList does not exist: {page}',
				[ 'page' => $this->mentorsPageName ] );
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
	 * @param Title $title
	 * @return string
	 */
	private function makeCacheKeyMentorsForPage( Title $title ): string {
		return $this->localServerCache->makeKey(
			'GrowthExperiments',
			__CLASS__,
			'MentorForPage',
			$title->getId()
		);
	}

	/**
	 * Helper method returning a list of mentors listed at a specified page
	 *
	 * This has two layers of caching: short-lived local server cache (5 minutes TTL) and
	 * a longer-living WAN cache (24 hours). This is because the local server cache cannot be
	 * invalidated, but is still needed because this method is executed on every page view made
	 * by a logged-in user.
	 *
	 * GrowthExperiments will take at most five minutes (TTL for the local server cache) to
	 * notice a change made in the list of mentors; lookups in those five minutes will be very
	 * fast thanks to APCu caching.
	 *
	 * @param WikiPage|null $page Page to work with or null if no page is provided
	 * @return string[]
	 */
	private function getMentorsForPage( ?WikiPage $page ): array {
		if ( $page === null ) {
			return [];
		}

		$cacheKey = $this->makeCacheKeyMentorsForPage( $page->getTitle() );
		return $this->localServerCache->getWithSetCallback(
			$cacheKey,
			5 * self::TTL_MINUTE,
			function () use ( $page, $cacheKey ) {
				return $this->wanCache->getWithSetCallback(
					$cacheKey,
					self::TTL_DAY,
					function () use ( $page ) {
						return $this->doGetMentorsForPage( $page );
					}
				);
			}
		);
	}

	/**
	 * Get a list of mentors listed at a specified page
	 *
	 * This implements no caching and is only intended for use by
	 * WikitextMentorProvider::getMentorsForPage.
	 *
	 * @param WikiPage|null $page
	 * @return array
	 */
	private function doGetMentorsForPage( ?WikiPage $page ): array {
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

	/**
	 * @inheritDoc
	 */
	public function getAutoAssignedMentors(): array {
		return $this->getMentorsForPage( $this->getMentorsPage() );
	}

	/**
	 * @return string
	 */
	private function makeCacheKeyWeightedAutoAssignedMentors(): string {
		return $this->wanCache->makeKey(
			'GrowthExperiments',
			__CLASS__,
			'WeightedMentors',
			$this->getAutoMentorsListTitle()->getId()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getWeightedAutoAssignedMentors(): array {
		$mentorListTitle = $this->getAutoMentorsListTitle();
		if ( !$mentorListTitle ) {
			// makeCacheKeyWeightedAutoAssignedMentors does not work when title is not defined
			return [];
		}

		return $this->wanCache->getWithSetCallback(
			$this->makeCacheKeyWeightedAutoAssignedMentors(),
			self::TTL_HOUR,
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
	public function getManuallyAssignedMentors(): array {
		return $this->getMentorsForPage( $this->getManuallyAssignedMentorsPage() );
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

		// all uses of getCustomMentorIntroText() are escaped but phab seems to get that wrong
		// @phan-suppress-next-line SecurityCheck-XSS
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
}
