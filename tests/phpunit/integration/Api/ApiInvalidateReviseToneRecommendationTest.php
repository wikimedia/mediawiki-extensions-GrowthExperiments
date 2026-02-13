<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use CirrusSearch\WeightedTagsUpdater;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\User;

/**
 * @group Database
 * @covers \GrowthExperiments\Api\ApiInvalidateReviseToneRecommendation
 */
class ApiInvalidateReviseToneRecommendationTest extends ApiTestCase {

	public function testResetsWeightedTagForPage(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$page = $this->getExistingTestPage();
		$mockWeightedTagsUpdater = $this->createMock( WeightedTagsUpdater::class );
		$mockWeightedTagsUpdater->expects( self::once() )
			->method( 'resetWeightedTags' )->with(
				PageIdentityValue::localIdentity( $page->getId(), 0, $page->getDBkey() ),
				[ 'recommendation.tone' ],
			);
		$this->setService( WeightedTagsUpdater::SERVICE, $mockWeightedTagsUpdater );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthinvalidaterevisetonerecommendation',
				'title' => $page->getDBkey(),
			],
			null,
			$this->getTestUser()->getUser(),
		);
	}

	public function testFailsOnAnonRequest(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$page = $this->getExistingTestPage();
		$mockWeightedTagsUpdater = $this->createNoOpMock( WeightedTagsUpdater::class );
		$this->setService( WeightedTagsUpdater::SERVICE, $mockWeightedTagsUpdater );

		$this->expectApiErrorCode( 'mustbeloggedin-generic' );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthinvalidaterevisetonerecommendation',
				'title' => $page->getDBkey(),
			],
			null,
			new User()
		);
	}
}
