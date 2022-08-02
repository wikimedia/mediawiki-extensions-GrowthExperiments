<?php

namespace GrowthExperiments\MentorDashboard\MentorTools;

use IDBAccessObject;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;

class MentorWeightManager implements IDBAccessObject, IMentorWeights {

	public const MENTORSHIP_WEIGHT_PREF = 'growthexperiments-mentorship-weight';
	/** @var int */
	public const MENTORSHIP_DEFAULT_WEIGHT = self::WEIGHT_NORMAL;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		UserOptionsManager $userOptionsManager
	) {
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @param UserIdentity $mentor
	 * @param int $flags One of MentorWeightManager::READ_*
	 * @return int One of MentorWeightManager::WEIGHT_*
	 */
	public function getWeightForMentor( UserIdentity $mentor, int $flags = 0 ): int {
		$weight = $this->userOptionsManager->getIntOption(
			$mentor,
			self::MENTORSHIP_WEIGHT_PREF,
			self::MENTORSHIP_DEFAULT_WEIGHT,
			$flags
		);

		if ( !in_array( $weight, self::WEIGHTS ) ) {
			return self::MENTORSHIP_DEFAULT_WEIGHT;
		}
		return $weight;
	}

	/**
	 * @param UserIdentity $mentor
	 * @param int|null $weight One of MentorWeightManager::WEIGHT_*
	 */
	public function setWeightForMentor( UserIdentity $mentor, ?int $weight = null ): void {
		if (
			$weight !== null && !in_array( $weight, self::WEIGHTS )
		) {
			throw new InvalidArgumentException( 'Invalid $weight passed' );
		}

		$this->userOptionsManager->setOption(
			$mentor,
			self::MENTORSHIP_WEIGHT_PREF,
			$weight
		);
		$this->userOptionsManager->saveOptions( $mentor );

		// TODO: Invalidate MentorManager's cache here. Cannot be done by injecting MentorManager
		// here, as MentorManager depends on MentorWeightManager.
	}
}
