<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentRequest;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Extension\MetricsPlatform\XLab\EnrollmentCssClassSerializer;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class ExperimentXLabManager extends AbstractExperimentManager {

	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableExperimentConfigsFetching',
	];
	// TODO: valid experiments and variants should/could be read from config
	public const GET_STARTED_EXPERIMENT = 'growthexperiments-get-started-notification';
	public const VARIANT_CONTROL = 'control';
	public const VARIANT_TREATMENT = 'treatment';
	public const VALID_EXPERIMENTS = [
		self::GET_STARTED_EXPERIMENT,
	];

	/** Map of (experiment name => assigned group) */
	private array $assignments = [];
	private ?string $currentExperimentName = null;
	private bool $computedAssignments = false;

	public function __construct(
		ServiceOptions $options,
		private readonly LoggerInterface $logger,
		private readonly InstrumentConfigsFetcher $configsFetcher,
		private readonly EnrollmentAuthority $enrollmentAuthority,
		private readonly ExperimentManager $experimentManager,
		private readonly Config $config,
	) {
		parent::__construct( $options );
	}

	public function enrollUser( RequestContext $ctx, UserIdentity $user ) {
		if ( $this->options->get( 'MetricsPlatformEnableExperimentConfigsFetching' ) ) {
			$this->configsFetcher->updateExperimentConfigs();
		}

		$activeLoggedInExperiments = $this->config->has( 'MetricsPlatformExperiments' ) ?
			$this->config->get( 'MetricsPlatformExperiments' ) :
			$this->configsFetcher->getExperimentConfigs();

		$enrollmentRequest = new EnrollmentRequest( $activeLoggedInExperiments, $user, $ctx->getRequest() );
		$result = new EnrollmentResultBuilder();
		$this->enrollmentAuthority->enrollUser( $enrollmentRequest, $result );

		// Override xLab's enrollment result
		$this->experimentManager->initialize( $result->build() );

		$output = $ctx->getOutput();
		// Override the JS xLab SDK config vars
		$output->addJsConfigVars( 'wgMetricsPlatformUserExperiments', $result->build() );
		// T393101: Add CSS classes representing experiment enrollment and assignment automatically so that experiment
		// implementers don't have to do this themselves.
		$output->addBodyClasses( EnrollmentCssClassSerializer::serialize( $result->build() ) );
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
		$this->initialize();
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
		// Only used for geForcedVariant feature, xLab does not support this.
		return false;
	}

	/** TODO deprecate and/or migrate geForcedVariant feature once T404622 is resolved */
	public function setVariant( UserIdentity $user, string $variant ): void {
		// Only used for geForcedVariant feature, should not be called.
	}
}
