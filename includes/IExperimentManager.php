<?php
namespace GrowthExperiments;

/**
 * An implementation of IExperimentManager should be capable of returning a variant
 * for a given experiment if the user is sampled in the experiment.
 */
interface IExperimentManager {

	public const VARIANT_CONTROL = 'control';
	public const VARIANT_TREATMENT = 'treatment';
	public const REVISE_TONE_EXPERIMENT = 'growthexperiments-revise-tone';

	// TODO: valid experiments and variants should/could be read from config
	public const EXPERIMENTS = [
		self::REVISE_TONE_EXPERIMENT,
	];

	/**
	 * Return the group assigned to a user for a given experiment
	 * @param string $experimentName
	 * @return string|null
	 */
	public function getAssignedGroup( string $experimentName ): ?string;

	/**
	 * Return group assignments for the known experiments,
	 * @return array<string,string|null> Array of experiment => assignment
	 */
	public function getAssignments(): array;
}
