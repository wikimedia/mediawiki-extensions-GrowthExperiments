<?php

namespace GrowthExperiments\Mentorship\Store;

use LogicException;
use MediaWiki\User\UserIdentity;

class MultiWriteMentorStore extends MentorStore {
	/** @var int */
	private $migrationStage;

	/** @var PreferenceMentorStore */
	private $preferenceMentorStore;

	/** @var DatabaseMentorStore */
	private $databaseMentorStore;

	/**
	 * @param int $migrationStage
	 * @param PreferenceMentorStore $preferenceMentorStore
	 * @param DatabaseMentorStore $databaseMentorStore
	 * @param bool $wasPosted
	 */
	public function __construct(
		int $migrationStage,
		PreferenceMentorStore $preferenceMentorStore,
		DatabaseMentorStore $databaseMentorStore,
		bool $wasPosted
	) {
		parent::__construct( $wasPosted );

		$this->migrationStage = $migrationStage;
		$this->preferenceMentorStore = $preferenceMentorStore;
		$this->databaseMentorStore = $databaseMentorStore;
	}

	/**
	 * @inheritDoc
	 */
	public function loadMentorUserUncached(
		UserIdentity $mentee,
		string $mentorRole,
		$flags
	): ?UserIdentity {
		if ( $this->migrationStage & SCHEMA_COMPAT_READ_OLD ) {
			return $this->preferenceMentorStore
				->loadMentorUserUncached( $mentee, $mentorRole, $flags );
		} elseif ( $this->migrationStage & SCHEMA_COMPAT_READ_NEW ) {
			return $this->databaseMentorStore
				->loadMentorUserUncached( $mentee, $mentorRole, $flags );
		} else {
			// Migration stage is supposed to be already validated
			throw new LogicException(
				'Invalid GrowthExperiments mentorship migration stage'
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function setMentorForUserInternal(
		UserIdentity $mentee,
		UserIdentity $mentor,
		string $mentorRole = self::ROLE_PRIMARY
	): void {
		if ( $this->migrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$this->preferenceMentorStore->setMentorForUser( $mentee, $mentor, $mentorRole );
		}

		if ( $this->migrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$this->databaseMentorStore->setMentorForUser( $mentee, $mentor, $mentorRole );
		}
	}
}
