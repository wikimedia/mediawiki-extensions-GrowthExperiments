<?php

namespace phpunit\integration\Config\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Tests\SchemaProviderTestCase;

/**
 * @coversNothing
 */
class HomepageSchemaProviderTest extends SchemaProviderTestCase {

	protected function getExtensionName(): string {
		return 'GrowthExperiments';
	}

	protected function getProviderId(): string {
		return 'GrowthHomepage';
	}

}
