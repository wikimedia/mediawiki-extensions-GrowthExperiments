<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorshipSummaryCreator;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Extension\CommunityConfiguration\Store\WikiPageStore;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * This class writes to the structured mentor list and allows to add/remove
 * mentors from the structured mentor list.
 *
 * Use CommunityStructuredMentorProvider to read the mentor list.
 *
 * This class uses WikiPageStore under the hood.
 */
class CommunityStructuredMentorWriter implements IMentorWriter {
	use CommunityGetMentorDataTrait;

	/**
	 * Change tag to tag structured mentor list edits with
	 *
	 * @note Keep in sync with extension.json (GrowthMentorList provider of
	 * CommunityConfiguration).
	 */
	public const CHANGE_TAG = 'mentor list change';
	public const CONFIG_KEY = 'Mentors';

	protected LoggerInterface $logger;
	protected MentorProvider $mentorProvider;
	protected UserIdentityLookup $userIdentityLookup;
	protected UserFactory $userFactory;

	public function __construct(
		LoggerInterface $logger,
		MentorProvider $mentorProvider,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		StatusFormatter $statusFormatter,
		IConfigurationProvider $provider,
		private readonly MentorStatusManager $mentorStatusManager,
	) {
		$this->logger = $logger;
		$this->mentorProvider = $mentorProvider;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userFactory = $userFactory;
		$this->statusFormatter = $statusFormatter;
		$this->provider = $provider;
	}

	protected function doSaveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer,
		bool $bypassWarnings
	): StatusValue {
		return $this->provider->alwaysStoreValidConfiguration(
			[ self::CONFIG_KEY => $mentorData ],
			$this->userFactory->newFromUserIdentity( $performer ),
			$summary
		);
	}

	/**
	 * @inheritDoc
	 */
	public function isBlocked(
		UserIdentity $performer, int $freshness = IDBAccessObject::READ_NORMAL
	): bool {
		$store = $this->provider->getStore();
		$block = $this->userFactory->newFromUserIdentity( $performer )->getBlock( $freshness );
		if ( $store instanceof WikiPageStore ) {
			return $block && $block->appliesToTitle( $store->getConfigurationTitle() );
		}
		return (bool)$block;
	}

	/**
	 * Serialize a Mentor object to an array
	 *
	 * @param Mentor $mentor
	 * @return array
	 */
	public static function serializeMentor( Mentor $mentor ): array {
		$awayTimestamp = [];
		if ( $mentor->getStatusAwayTimestamp() ) {
			$awayTimestamp[ 'awayTimestamp' ] = ConvertibleTimestamp::convert(
				TS_ISO_8601,
				$mentor->getStatusAwayTimestamp()
			);
		}
		return [
			'message' => $mentor->hasCustomIntroText() ? $mentor->getIntroText() : null,
			'weight' => $mentor->getWeight(),
		] + $awayTimestamp;
	}

	/**
	 * Save mentor data and invalidate mentor's cache and mentor status manager cache
	 */
	protected function saveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer,
		bool $bypassWarnings,
		?UserIdentity $mentorUserIdentity = null
	): StatusValue {
		// check if $performer is not blocked from the mentor list page
		if ( $this->isBlocked( $performer, IDBAccessObject::READ_LATEST ) ) {
			return StatusValue::newFatal( 'growthexperiments-mentor-writer-error-blocked' );
		}

		// add 'username' key for readability (T331444)
		foreach ( $mentorData as $mentorId => $_ ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByUserId( $mentorId );
			if ( !$mentorUser ) {
				$this->logger->warning( 'Mentor list contains an invalid user for ID {userId}', [
					'userId' => $mentorId,
				] );
				continue;
			}

			$mentorData[$mentorId]['username'] = $mentorUser->getName();
		}
		$result = $this->doSaveMentorData(
			$mentorData,
			$summary,
			$performer,
			$bypassWarnings
		);

		if ( $result->isOK() ) {
			$this->mentorProvider->invalidateMentorsCache();
			if ( $mentorUserIdentity ) {
				// MentorStatusManager has an in-process cache, if there's a mentor informed by callers, assume the
				// status may have changed and the cache needs invalidation
				$this->mentorStatusManager->invalidateAwayReasonCache( $mentorUserIdentity );
			}
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function addMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue {
		$mentorUserIdentity = $mentor->getUserIdentity();
		if ( !$mentorUserIdentity->isRegistered()
			|| !$this->userFactory->newFromUserIdentity( $mentorUserIdentity )->isNamed()
		) {
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-anonymous-user',
				$mentorUserIdentity->getName()
			);
		}

		$mentorData = $this->getMentorData();
		if ( array_key_exists( $mentorUserIdentity->getId(), $mentorData ) ) {
			// we're trying to add someone who's already added
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-already-added',
				$mentorUserIdentity->getName()
			);
		}
		$mentorData[$mentorUserIdentity->getId()] = $this->serializeMentor( $mentor );

		return $this->saveMentorData(
			$mentorData,
			MentorshipSummaryCreator::createAddSummary(
				$performer,
				$mentorUserIdentity,
				$summary
			),
			$performer,
			$bypassWarnings,
			$mentorUserIdentity
		);
	}

	/**
	 * @inheritDoc
	 */
	public function removeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue {
		$mentorUserIdentity = $mentor->getUserIdentity();

		$mentorData = $this->getMentorData();
		if ( !array_key_exists( $mentorUserIdentity->getId(), $mentorData ) ) {
			// we're trying to remove someone who isn't added
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-not-in-the-list',
				$mentorUserIdentity->getName()
			);
		}
		unset( $mentorData[$mentorUserIdentity->getId()] );

		return $this->saveMentorData(
			$mentorData,
			MentorshipSummaryCreator::createRemoveSummary(
				$performer,
				$mentorUserIdentity,
				$summary
			),
			$performer,
			$bypassWarnings,
			$mentorUserIdentity,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function changeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue {
		$mentorUserIdentity = $mentor->getUserIdentity();

		$mentorData = $this->getMentorData();
		if ( !array_key_exists( $mentorUserIdentity->getId(), $mentorData ) ) {
			// we're trying to change someone who isn't added
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-not-in-the-list',
				$mentorUserIdentity->getName()
			);
		}
		$mentorData[$mentorUserIdentity->getId()] = $this->serializeMentor( $mentor );

		return $this->saveMentorData(
			$mentorData,
			MentorshipSummaryCreator::createChangeSummary(
				$performer,
				$mentorUserIdentity,
				$summary
			),
			$performer,
			$bypassWarnings,
			$mentorUserIdentity,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function touchList( UserIdentity $performer, string $summary ): StatusValue {
		$mentorData = $this->getMentorData();
		foreach ( $mentorData as $mentorId => $mentorArr ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByUserId( $mentorId );
			if ( !$mentorUser ) {
				continue;
			}
			$mentorData[$mentorUser->getId()] = $this->serializeMentor(
				$this->mentorProvider->newMentorFromUserIdentity( $mentorUser )
			);
		}
		return $this->saveMentorData(
			$mentorData,
			$summary,
			$performer,
			true,
		);
	}
}
