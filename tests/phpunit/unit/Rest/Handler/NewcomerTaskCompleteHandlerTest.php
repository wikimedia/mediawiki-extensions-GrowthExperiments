<?php

namespace GrowthExperiments\Tests\Rest\Handler;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\Rest\Handler\NewcomerTaskCompleteHandler;
use HashConfig;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\ILoadBalancer;

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
		RevisionLookup $revisionLookup = null
	): NewcomerTaskCompleteHandler {
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn(
			[ 'copyedit' => new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY ),
				'link-recommendation' => new TaskType( 'link-recommendation', TaskType::DIFFICULTY_EASY ) ]
		);
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getLazyConnectionRef' )->willReturn(
			$this->createMock( DBConnRef::class )
		);
		$newcomerTasksChangeTagsManager = new NewcomerTasksChangeTagsManager(
			$userOptionsLookup,
			$this->createMock( TaskTypeHandlerRegistry::class ),
			$configurationLoader,
			$this->createNoOpMock( \PrefixingStatsdDataFactoryProxy::class ),
			$revisionLookup ?? $this->createMock( RevisionLookup::class ),
			$loadBalancer,
			new HashConfig( $config ),
			$user
		);
		return new NewcomerTaskCompleteHandler( $newcomerTasksChangeTagsManager );
	}
}
