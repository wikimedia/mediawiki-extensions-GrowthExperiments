<?php
declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\InterestValidator
 * @group Database
 */
class InterestValidatorTest extends MediaWikiIntegrationTestCase {

	/**
	 * T441415: an interest whose article was deleted after the user picked it should be
	 * skipped, and so should a redirect to that article. The other interests are kept.
	 */
	public function testDeletedArticlesAndRedirects(): void {
		$this->insertPage( 'Coffee' );
		$this->insertPage( 'Black Tea', '#REDIRECT [[Coffee]]' );
		$this->insertPage( 'Green Dwarf' );
		$this->insertPage( 'Green Planet', '#REDIRECT [[Green Dwarf]]' );
		$this->deletePage( 'Green Dwarf' );

		$validator = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getInterestValidator();

		$this->assertSame(
			[
				'valid' => [ 'Coffee', 'Black Tea' ],
				'invalid' => [ 'Green Dwarf', 'Green Planet' ],
			],
			$validator->validate( [ 'Coffee', 'Green Dwarf', 'Black Tea', 'Green Planet' ] )
		);
	}

	/**
	 * The search follows redirects, so a redirect that points outside the main namespace
	 * (for example after the article was moved to another namespace) is invalid. A redirect
	 * to a section of an article is valid, because the search uses the whole article.
	 */
	public function testRedirectTargets(): void {
		$this->insertPage( 'Coffee' );
		$this->insertPage( 'Coffee history', '#REDIRECT [[Coffee#History]]' );
		$this->insertPage( 'Help:Green Planet' );
		$this->insertPage( 'Green Planet', '#REDIRECT [[Help:Green Planet]]' );

		$validator = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getInterestValidator();

		$this->assertSame(
			[
				'valid' => [ 'Coffee history' ],
				'invalid' => [ 'Green Planet' ],
			],
			$validator->validate( [ 'Coffee history', 'Green Planet' ] )
		);
	}

}
