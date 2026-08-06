<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

/**
 * This interface holds constants for experiments that we may wish to use across our code-base instead of magic strings.
 * This interface is not intended to be implemented.
 */
interface IExperimentManager {

	public const string VARIANT_CONTROL = 'control';
	public const string VARIANT_TREATMENT = 'treatment';
	public const string DE_1_3_1_SPECIALHOMEPAGE_ONBOARDING_AA_TEST = 'de-1-3-1-specialhomepage-onboarding-aa-test';

}
