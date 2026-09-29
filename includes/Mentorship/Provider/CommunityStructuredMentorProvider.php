<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;

class CommunityStructuredMentorProvider extends MentorProvider {
	use CommunityGetMentorDataTrait;

	private UserIdentityLookup $userIdentityLookup;
	private MessageLocalizer $messageLocalizer;
	private ?array $mentorUsers = null;
	private ?array $cachedMentorData = null;
	private ?array $mentors = null;
	private WANObjectCache $cache;
	private const CACHE_TTL = 86400;

	public function __construct(
		LoggerInterface $logger,
		UserIdentityLookup $userIdentityLookup,
		MessageLocalizer $messageLocalizer,
		IConfigurationProvider $provider,
		StatusFormatter $statusFormatter,
		WANObjectCache $cache
	) {
		parent::__construct( $logger );

		$this->userIdentityLookup = $userIdentityLookup;
		$this->messageLocalizer = $messageLocalizer;
		$this->provider = $provider;
		$this->statusFormatter = $statusFormatter;
		$this->cache = $cache;
	}

	/**
	 * @param array|null $mentorData
	 * @param UserIdentity $mentorUser
	 * @param UserIdentity|null $menteeUser
	 * @return Mentor
	 */
	private function newFromMentorDataAndUserIdentity(
		?array $mentorData,
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor {
		$weight = $mentorData['weight'] ?? IMentorWeights::WEIGHT_NORMAL;
		if (
			$mentorData &&
			array_key_exists( 'automaticallyAssigned', $mentorData ) &&
			!$mentorData['automaticallyAssigned']
		) {
			// T347157: To aid with migration; remove once automaticallyAssigned is not set.
			$weight = IMentorWeights::WEIGHT_NONE;
		}
		return new Mentor(
			$mentorUser,
			$mentorData['message'] ?? null,
			fn () => $this->getDefaultMentorIntroText( $mentorUser, $menteeUser ),
			$weight,
			$mentorData['awayTimestamp'] ?? null
		);
	}

	/**
	 * @param UserIdentity $mentor
	 * @param ?UserIdentity $mentee
	 * @return string
	 */
	private function getDefaultMentorIntroText(
		UserIdentity $mentor,
		?UserIdentity $mentee
	): string {
		return $this->messageLocalizer
			->msg( 'growthexperiments-homepage-mentorship-intro' )
			->inContentLanguage()
			->params( $mentor->getName() )
			->params( $mentee ? $mentee->getName() : '' )
			->text();
	}

	/**
	 * Get the mentor list configuration, memoized for the request
	 *
	 * IConfigurationProvider::loadValidConfiguration() copies the whole list through
	 * json_encode()/json_decode() on every call.
	 *
	 * @return array
	 */
	private function getCachedMentorData(): array {
		$this->cachedMentorData ??= $this->getMentorData();
		return $this->cachedMentorData;
	}

	/**
	 * @param UserIdentity $mentor
	 * @return array|null
	 */
	private function getMentorDataForUser( UserIdentity $mentor ): ?array {
		return $this->getCachedMentorData()[$mentor->getId()] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getSignupTitle(): ?Title {
		return SpecialPage::getTitleFor( 'EnrollAsMentor' );
	}

	/**
	 * @inheritDoc
	 */
	public function newMentorFromUserIdentity(
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor {
		return $this->newFromMentorDataAndUserIdentity(
			$this->getMentorDataForUser( $mentorUser ),
			$mentorUser,
			$menteeUser
		);
	}

	/**
	 * @inheritDoc
	 *
	 * This uses the cached list of mentor users. It does not read the configuration, and it
	 * does not construct Mentor objects. Thus a direct edit of the configuration changes the
	 * result only after invalidateMentorsCache(), or after the cache expires.
	 */
	public function isMentor( UserIdentity $user ): bool {
		return $user->isRegistered() && array_reduce(
				$this->getMentorUsers(),
				static function ( bool $carry, UserIdentity $mentorUser ) use ( $user ) {
					if ( $carry ) {
						return true;
					}
					return $user->equals( $mentorUser );
				},
				false
			);
	}

	/**
	 * Gets the cache key for mentor list
	 *
	 * @return string
	 */
	private function getMentorsCacheKey(): string {
		return $this->cache->makeKey( 'growthexperiments-mentors-list' );
	}

	/**
	 * Get the mentor users whose identity is resolved from the cached mentor ID list
	 *
	 * @return UserIdentity[]
	 */
	private function getMentorUsers(): array {
		if ( $this->mentorUsers !== null ) {
			return $this->mentorUsers;
		}

		$cacheKey = $this->getMentorsCacheKey();
		$method = __METHOD__;
		$this->mentorUsers = $this->cache->getWithSetCallback(
			$cacheKey,
			self::CACHE_TTL,
			function () use ( $method ) {
				$mentorIds = array_keys( $this->getCachedMentorData() );
				if ( $mentorIds === [] ) {
					return [];
				}
				return iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
					->whereUserIds( $mentorIds )
					->registered()
					->caller( $method )
					->fetchUserIdentities() );
			}
		);

		return $this->mentorUsers;
	}

	/**
	 * @inheritDoc
	 */
	public function getMentors(): array {
		if ( $this->mentors !== null ) {
			return $this->mentors;
		}

		$mentorData = $this->getCachedMentorData();
		$mentors = [];
		foreach ( $this->getMentorUsers() as $mentorUser ) {
			$userId = $mentorUser->getId();
			if ( !array_key_exists( $userId, $mentorData ) ) {
				continue;
			}
			$mentors[$userId] = $this->newFromMentorDataAndUserIdentity(
				$mentorData[$userId],
				$mentorUser
			);
		}

		$this->mentors = $mentors;
		return $this->mentors;
	}

	/**
	 * Invalidate the mentors cache
	 */
	public function invalidateMentorsCache(): void {
		$this->cache->delete( $this->getMentorsCacheKey() );

		// Reset in-memory cache
		$this->mentorUsers = null;
		$this->cachedMentorData = null;
		$this->mentors = null;
	}
}
