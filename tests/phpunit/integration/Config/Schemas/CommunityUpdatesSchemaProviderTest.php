<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Extension\CommunityConfiguration\Tests\SchemaProviderTestCase;

/**
 * @coversNothing
 */
class CommunityUpdatesSchemaProviderTest extends SchemaProviderTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( 'GECommunityUpdatesEnabled', true );
	}

	protected function getExtensionName(): string {
		return 'GrowthExperiments';
	}

	protected function getProviderId(): string {
		return 'CommunityUpdates';
	}

}
