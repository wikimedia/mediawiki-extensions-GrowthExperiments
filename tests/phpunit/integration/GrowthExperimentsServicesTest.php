<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Tests\ExtensionServicesTestBase;

/**
 * @covers \GrowthExperiments\GrowthExperimentsServices
 */
class GrowthExperimentsServicesTest extends ExtensionServicesTestBase {

	/** @inheritDoc */
	protected static string $className = GrowthExperimentsServices::class;

	/** @inheritDoc */
	protected static bool $wrapsServiceContainer = true;

	/** @inheritDoc */
	protected string $serviceNamePrefix = 'GrowthExperiments';

	/** @inheritDoc */
	protected array $serviceNamesWithoutMethods = [
		'GrowthExperimentsLinkRecommendationProviderUncached',
		'GrowthExperimentsPostSignupOnboardingEligibility',
		'GrowthExperimentsUserImpactUpdater',
	];

	/** @inheritDoc */
	protected array $serviceNamesByMethod = [
		'getGrowthConfig' => 'GrowthExperimentsConfig',
		'getGrowthWikiConfig' => 'GrowthExperimentsCommunityConfig',
		'getGrowthConnectionProvider' => 'GrowthExperimentsConnectionProvider',
		'getDatabaseMenteeOverviewDataProvider' => 'GrowthExperimentsMenteeOverviewDataProviderDatabase',
		'getUncachedMenteeOverviewDataProvider' => 'GrowthExperimentsMenteeOverviewDataProviderUncached',
		'getDatabaseMentorStore' => 'GrowthExperimentsMentorStoreDatabase',
		'getGrowthExperimentsCampaignConfig' => 'GrowthExperimentsCampaignConfig',
		'getGrowthExperimentsCampaignLoader' => 'GrowthExperimentsCampaignLoader',
		'getUncachedUserImpactLookup' => 'GrowthExperimentsUserImpactLookup_Computed',
	];

}
