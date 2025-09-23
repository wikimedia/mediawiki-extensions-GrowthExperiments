<?php

namespace GrowthExperiments\MentorDashboard\MentorTools;

/**
 * Interface containing available mentor weights
 */
interface IMentorWeights {

	public const WEIGHT_NONE = 0;
	public const WEIGHT_LOW = 1;
	public const WEIGHT_NORMAL = 2;
	public const WEIGHT_HIGH = 4;

	public const WEIGHTS = [
		self::WEIGHT_NONE,
		self::WEIGHT_LOW,
		self::WEIGHT_NORMAL,
		self::WEIGHT_HIGH,
	];

}
