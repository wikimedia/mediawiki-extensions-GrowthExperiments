<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use SpecialPage;
use Status;
use StatusValue;
use Title;

class StructuredMentorProvider extends MentorProvider {

	/** @var WikiPageConfigLoader */
	private $configLoader;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var LinkTarget */
	private $mentorList;

	/**
	 * @param WikiPageConfigLoader $configLoader
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserNameUtils $userNameUtils
	 * @param MessageLocalizer $messageLocalizer
	 * @param LinkTarget $mentorList
	 */
	public function __construct(
		WikiPageConfigLoader $configLoader,
		UserIdentityLookup $userIdentityLookup,
		UserNameUtils $userNameUtils,
		MessageLocalizer $messageLocalizer,
		LinkTarget $mentorList
	) {
		parent::__construct();

		$this->configLoader = $configLoader;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userNameUtils = $userNameUtils;
		$this->messageLocalizer = $messageLocalizer;
		$this->mentorList = $mentorList;
	}

	/**
	 * Wrapper around WikiPageConfigLoader
	 *
	 * Guaranteed to return a valid mentor list. If a valid mentor list cannot be constructed
	 * using the wiki page, it constructs an empty mentor list instead and logs an error.
	 *
	 * This is cached within WikiPageConfigLoader.
	 *
	 * @return array
	 */
	private function getMentorData(): array {
		$res = $this->configLoader->load( $this->mentorList );
		if ( $res instanceof StatusValue ) {
			// Loading the mentor list failed. Log an error and return an empty array.
			$this->logger->error(
				__METHOD__ . ' failed to load mentor list: {error}',
				[
					'error' => Status::wrap( $res )->getWikiText( false, false, 'en' ),
					'impact' => 'No data about mentors can be found; wiki behaves as if it had no mentors at all'
				]
			);
			return [];
		}
		return $res['Mentors'];
	}

	/**
	 * @inheritDoc
	 */
	public function getSignupTitle(): ?Title {
		return SpecialPage::getTitleFor( 'MentorDashboard' );
	}

	/**
	 * @param UserIdentity $mentor
	 * @return array|null
	 */
	private function getMentorDataForUser( UserIdentity $mentor ): ?array {
		return $this->getMentorData()[$mentor->getId()] ?? null;
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
			$this->getCustomMentorIntroText( $mentorUser ),
			$this->getDefaultMentorIntroText( $mentorUser, $menteeUser ?? $mentorUser )
		);
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 * @return string
	 */
	private function getDefaultMentorIntroText(
		UserIdentity $mentor,
		UserIdentity $mentee
	): string {
		return $this->messageLocalizer
			->msg( 'growthexperiments-homepage-mentorship-intro' )
			->inContentLanguage()
			->params( $mentor->getName() )
			->params( $mentee->getName() )
			->text();
	}

	/**
	 * @param UserIdentity $mentor
	 * @return string|null
	 */
	private function getCustomMentorIntroText( UserIdentity $mentor ): ?string {
		$mentorData = $this->getMentorDataForUser( $mentor );
		if ( !$mentorData ) {
			return null;
		}
		return $mentorData['message'];
	}

	/**
	 * @inheritDoc
	 */
	public function getMentors(): array {
		$mentorIds = array_keys( $this->getMentorData() );
		if ( $mentorIds === [] ) {
			return [];
		}

		return $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $mentorIds )
			->registered()
			->fetchUserNames();
	}

	/**
	 * @inheritDoc
	 */
	public function getMentorsSafe(): array {
		return $this->getMentors();
	}

	/**
	 * @inheritDoc
	 */
	public function getAutoAssignedMentors(): array {
		$userIDs = array_keys( array_filter(
			$this->getMentorData(),
			static function ( array $mentorData ) {
				return $mentorData['automaticallyAssigned'];
			}
		) );

		if ( $userIDs === [] ) {
			return [];
		}

		return $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIDs )
			->registered()
			->fetchUserNames();
	}

	/**
	 * @inheritDoc
	 */
	public function getWeightedAutoAssignedMentors(): array {
		$mentors = $this->getMentorData();

		$usernames = [];
		foreach ( $mentors as $userId => $mentorData ) {
			if ( !$mentorData['automaticallyAssigned'] ) {
				continue;
			}

			$user = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
			if ( !$user ) {
				continue;
			}

			$usernames = array_merge( $usernames, array_fill(
				0,
				$mentorData['weight'],
				$user->getName()
			) );
		}
		return $usernames;
	}

	/**
	 * @inheritDoc
	 */
	public function getManuallyAssignedMentors(): array {
		$userIDs = array_keys( array_filter(
			$this->getMentorData(),
			static function ( array $mentorData ) {
				return !$mentorData['automaticallyAssigned'];
			}
		) );

		if ( $userIDs === [] ) {
			return [];
		}

		return $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIDs )
			->registered()
			->fetchUserNames();
	}
}
