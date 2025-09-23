<?php

use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\StaticUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityValue;

// Raise limits from I2aead24cb7f47
if ( defined( 'MW_QUIBBLE_CI' ) ) {
	$wgMaxArticleSize = 100;
	$wgParsoidSettings['wt2htmlLimits']['wikitextSize'] = 100 * 1024;
	$wgParsoidSettings['html2wtLimits']['htmlSize'] = 500 * 1024;
}

# Prevent pruning of red links (among other things) for subpage provider.
$wgGEDeveloperSetup = true;

// Set $wgPageViewInfoWikimediaDomain for page view info URL construction.
$wgPageViewInfoWikimediaDomain = 'en.wikipedia.org';

$wgHooks['MediaWikiServices'][] = static function ( MediaWikiServices $services ) {
	// Set up a fake user impact lookup service for CI.
	if ( defined( 'MW_QUIBBLE_CI' ) ) {
		$staticUserImpactLookup = new StaticUserImpactLookup( [
			1 => new GrowthExperiments\UserImpact\ExpensiveUserImpact(
				new UserIdentityValue( 1, 'Admin' ),
				10,
				5,
				[ 0 => 2 ],
				[
					'2022-08-24' => 1,
					'2022-08-25' => 1,
				],
				[ 'copyedit' => 1, 'link-recommendation' => 1 ],
				1,
				2,
				wfTimestamp( TS_UNIX, '20220825000000' ),
				[
					'2022-08-24' => 1000,
					'2022-08-25' => 2000,
				],
				[
					'Foo' => [
						'firstEditDate' => '2022-08-24',
						'newestEdit' => '20220825143817',
						'viewsCount' => 1000,
						'views' => [
							'2022-08-24' => 500,
							'2022-08-25' => 500,
						],
					],
					'Bar' => [
						'firstEditDate' => '2022-08-24',
						'newestEdit' => '20220825143818',
						'viewsCount' => 2000,
						'views' => [
							'2022-08-24' => 1000,
							'2022-08-25' => 1000,
						],
					],
				],
				new EditingStreak(),
				0,
				2
			),
		] );
		$services->redefineService( 'GrowthExperimentsUserImpactLookup',
			static function () use ( $staticUserImpactLookup ): UserImpactLookup {
				return $staticUserImpactLookup;
			} );
		$services->redefineService( 'GrowthExperimentsUserImpactStore',
			static function () use ( $staticUserImpactLookup ): UserImpactLookup {
				return $staticUserImpactLookup;
			} );
	}
};

// Conditionally load Parsoid in CI
if ( defined( 'MW_QUIBBLE_CI' ) && !is_dir( "$IP/services/parsoid" ) ) {
	$PARSOID_INSTALL_DIR = "$IP/vendor/wikimedia/parsoid";
	wfLoadExtension( 'Parsoid', "$PARSOID_INSTALL_DIR/extension.json" );
}
