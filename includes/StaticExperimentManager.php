<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;

class StaticExperimentManager implements IExperimentManager {
	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
	];

	public function __construct( private readonly ServiceOptions $options ) {
	}

	public function getAssignments(): array {
		return array_reduce(
			self::EXPERIMENTS,
			fn ( $carry, $expName ) => $carry + [ $expName => $this->getAssignedGroup( $expName ) ],
			[]
		);
	}

	public function getAssignedGroup( string $experimentName ): ?string {
		$experimentsConfigSpec = $this->options->get( 'GEHomepageDefaultVariant' );
		$group = null;
		if ( is_array( $experimentsConfigSpec ) ) {
			$group = $experimentsConfigSpec[ $experimentName ] ?? null;
		}
		if ( is_string( $experimentsConfigSpec ) ) {
			$group = $experimentsConfigSpec;
		}
		return $group;
	}
}
