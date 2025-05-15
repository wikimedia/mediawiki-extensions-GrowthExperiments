<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\Rest\Handler\NewcomerTaskCompleteHandler;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Config\HashConfig;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Stats\StatsFactory;

/**
 * @coversDefaultClass \GrowthExperiments\Rest\Handler\NewcomerTaskCompleteHandler
 */
class NewcomerTaskCompleteHandlerTest extends \MediaWikiUnitTestCase {
	use HandlerTestTrait;

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithSuggestedEditsDisabledGlobally() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$handler = $this->getNewcomerTaskCompleteHandler(
			[ 'GEHomepageSuggestedEditsEnabled' => false ],
			$user,
			$this->createMock( UserOptionsLookup::class )
		);
		$authorityMock = $this->createMock( Authority::class );
		$authorityMock->method( 'getUser' )->willReturn( $user );
		$this->expectExceptionMessage( 'Suggested edits are not enabled or activated for your user.' );
		$this->expectException( HttpException::class );
		$this->executeHandler(
			$handler,
			new RequestData(),
			[ 'GEHomepageSuggestedEditsEnabled' => false ],
			[],
			[ 'taskTypeId' => 'foo', 'revId' => 123 ],
			[],
			$authorityMock
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithSuggestedEditsDisabledForUser() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )->willReturn( false );
		$handler = $this->getNewcomerTaskCompleteHandler(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup
		);
		$authorityMock = $this->createMock( Authority::class );
		$authorityMock->method( 'getUser' )->willReturn( $user );
		$this->expectExceptionMessage( 'Suggested edits are not enabled or activated for your user.' );
		$this->expectException( HttpException::class );
		$this->executeHandler(
			$handler,
			new RequestData(),
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			[],
			[ 'taskTypeId' => 'foo', 'revId' => 123 ],
			[],
			$authorityMock
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithSuggestedEditsInvalidTaskTypeId() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )->willReturn( true );
		$handler = $this->getNewcomerTaskCompleteHandler(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup
		);
		$authorityMock = $this->createMock( Authority::class );
		$authorityMock->method( 'getUser' )->willReturn( $user );
		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( 'Invalid task type ID: foo' );
		$this->executeHandler(
			$handler,
			new RequestData(),
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			[],
			[ 'taskTypeId' => 'foo', 'revId' => 123 ],
			[],
			$authorityMock
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithInvalidRevision() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )->willReturn( true );
		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )->willReturn( null );
		$handler = $this->getNewcomerTaskCompleteHandler(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup,
			$revisionLookup
		);
		$authorityMock = $this->createMock( Authority::class );
		$authorityMock->method( 'getUser' )->willReturn( $user );
		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( '123 is not a valid revision ID.' );
		$this->executeHandler(
			$handler,
			new RequestData(),
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			[],
			[ 'taskTypeId' => 'copyedit', 'revId' => 123 ],
			[],
			$authorityMock
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithRevisionNotOwnedByUser() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )->willReturn( true );
		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getUser' )->willReturn(
			new UserIdentityValue( 2, 'Bar' )
		);
		$revisionLookup->method( 'getRevisionById' )->willReturn( $revisionRecord );
		$handler = $this->getNewcomerTaskCompleteHandler(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup,
			$revisionLookup
		);
		$authorityMock = $this->createMock( Authority::class );
		$authorityMock->method( 'getUser' )->willReturn( $user );
		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( 'User ID 2 on revision does not match logged-in user ID 1.' );
		$this->executeHandler(
			$handler,
			new RequestData(),
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			[],
			[ 'taskTypeId' => 'copyedit', 'revId' => 123 ],
			[],
			$authorityMock
		);
	}

	private function getNewcomerTaskCompleteHandler(
		array $config,
		UserIdentity $user,
		UserOptionsLookup $userOptionsLookup,
		?RevisionLookup $revisionLookup = null
	): NewcomerTaskCompleteHandler {
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn(
			[ 'copyedit' => new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY ),
				'link-recommendation' => new TaskType( 'link-recommendation', TaskType::DIFFICULTY_EASY ) ]
		);
		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isNamed' )
			->willReturnCallback( static function ( UserIdentity $userIdentity ) {
				return (bool)$userIdentity->getId();
			} );

		$newcomerTasksChangeTagsManager = new NewcomerTasksChangeTagsManager(
			$userOptionsLookup,
			$this->createMock( TaskTypeHandlerRegistry::class ),
			$configurationLoader,
			$revisionLookup ?? $this->createMock( RevisionLookup::class ),
			$this->createMock( IConnectionProvider::class ),
			$userIdentityUtils,
			$this->createMock( ChangeTagsStore::class ),
			StatsFactory::newNull(),
			new HashConfig( $config ),
			$user
		);
		return new NewcomerTaskCompleteHandler( $newcomerTasksChangeTagsManager );
	}
}
