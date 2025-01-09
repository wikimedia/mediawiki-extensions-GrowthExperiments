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
	 * Get statsd key where the metric should be stored
	 *
	 * This is a per-wiki key.
	 *
	 * @return string
	 * @deprecated Will be removed when StatsD support is dropped. Use getStatsLibKey() instead.
	 */
	public function getStatsdKey(): string;

	/**
	 * Get StatsLib compatible key for the metric
	 *
	 * @return string
	 */
	public function getStatsLibKey(): string;
}
