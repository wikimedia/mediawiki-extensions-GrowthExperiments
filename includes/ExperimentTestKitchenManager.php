<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class ExperimentTestKitchenManager extends AbstractExperimentManager {

	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
	];
	// TODO: valid experiments and variants should/could be read from config
	public const REVISE_TONE_EXPERIMENT = 'growthexperiments-revise-tone';
	public const VARIANT_CONTROL = 'control';
	public const VARIANT_TREATMENT = 'treatment';

	public const REVISE_TONE_EXPERIMENT_TREATMENT_GROUP_NAME = self::REVISE_TONE_EXPERIMENT .
	'_' . self::VARIANT_TREATMENT;
	public const VALID_EXPERIMENTS = [
		self::REVISE_TONE_EXPERIMENT,
	];

	/** Map of (experiment name => assigned group) */
	private array $assignments = [];
	private ?string $currentExperimentName = null;
	private bool $computedAssignments = false;

	public function __construct(
		ServiceOptions $options,
		private readonly LoggerInterface $logger,
		private readonly ExperimentManager $experimentManager,
	) {
		parent::__construct( $options );
	}

	public function getCurrentExperiment(): ?Experiment {
		$this->initialize();
		if ( $this->currentExperimentName === null ) {
			return null;
		}
		return $this->experimentManager->getExperiment( $this->currentExperimentName );
	}

	private function initialize(): void {
		if ( $this->computedAssignments ) {
			return;
		}

		foreach ( static::VALID_EXPERIMENTS as $experimentName ) {
			$experiment = $this->experimentManager->getExperiment( $experimentName );
			$group = $experiment->getAssignedGroup();

			// Get all Growth experiments assignments
			if ( $group !== null ) {
				$this->assignments[ $experimentName ] = $group;
			}
		}
		// Maybe select first experiment as "in-course", log info if more than one assignment is found
		$this->currentExperimentName = array_key_first( $this->assignments );
		$numberOfAssignments = count( $this->assignments );
		if ( $numberOfAssignments > 1 ) {
			$this->logger->info(
				'Experiment manager initialized with {numberOfAssignments} experiments, only one is supported.'
				. ' assigned first experiment {experimentName} with group {group}', [
				'$numberOfAssignments' => $numberOfAssignments,
				'experimentName' => $this->currentExperimentName,
				'group' => $this->currentExperimentName ? $this->assignments[ $this->currentExperimentName ] : null,
				] );
		}
		$this->computedAssignments = true;
	}

	/**
	 * Maybe get an experiment variant name if the user is enrolled into an active experiment.
	 * Otherwise, the return value will be the default set by "GEHomepageDefaultVariant".
	 * For enrolled experiments, the variant name will be in the format "<experiment-name>_<assigned-group>.
	 */
	public function getVariant( ?UserIdentity $user ): string {
		$this->initialize();
		// No experiment in course, behave as ExperimentUserManager and return default variant
		if ( !$this->currentExperimentName ) {
			return $this->options->get( 'GEHomepageDefaultVariant' );
		}
		$group = $this->assignments[ $this->currentExperimentName ];
		return $this->currentExperimentName . '_' . $group;
	}

	public function getValidVariants(): array {
		$variants = [];
		foreach ( static::VALID_EXPERIMENTS as $experimentName ) {
			$variants = array_merge( $variants, [
				$experimentName . '_' . self::VARIANT_CONTROL,
				$experimentName . '_' . self::VARIANT_TREATMENT,
			] );
		}
		return $variants;
	}

	/** TODO deprecate and/or migrate geForcedVariant feature once T404622 is resolved */
	public function isValidVariant( string $variant ): bool {
		// Only used for geForcedVariant feature, Test Kitchen does not support this.
		return false;
	}

	/** TODO deprecate and/or migrate geForcedVariant feature once T404622 is resolved */
	public function setVariant( UserIdentity $user, string $variant ): void {
		// Only used for geForcedVariant feature, should not be called.
	}
}
