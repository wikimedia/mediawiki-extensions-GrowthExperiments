<?php

namespace GrowthExperiments\PeriodicMetrics;

/**
 * Represents a metric that can be calculated at any time
 */
interface IMetric {
	/**
	 * Calculate the value of the metric
	 *
	 * @return int
	 */
	public function calculate(): int;

	/**
	 * Get StatsLib compatible key for the metric
	 *
	 * @return string
	 */
	public function getStatsLibKey(): string;
}
