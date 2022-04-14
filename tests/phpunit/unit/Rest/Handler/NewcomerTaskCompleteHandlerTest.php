<?php

namespace GrowthExperiments\Tests\Rest\Handler;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\Rest\Handler\NewcomerTaskCompleteHandler;
use HashConfig;
use IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\ResponseFactory;
use MediaWiki\Rest\Router;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @coversDefaultClass \GrowthExperiments\Rest\Handler\NewcomerTaskCompleteHandler
 */
class NewcomerTaskCompleteHandlerTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithSuggestedEditsDisabledGlobally() {
		$handler = $this->getInitializedNewcomerTaskCompleteHandlerWithConfigAndUser(
			[ 'GEHomepageSuggestedEditsEnabled' => false ],
			new UserIdentityValue( 1, 'Foo' ),
			$this->createMock( UserOptionsLookup::class ),
			$this->createMock( RevisionLookup::class )
		);
		$validator = $this->createMock( Validator::class );
		$validator->method( 'validateParams' )->willReturn( [ 'taskTypeId' => 'foo', 'revId' => 123 ] );
		$handler->validate( $validator );
		$this->expectExceptionMessage( 'Suggested edits are not enabled or activated for your user.' );
		$this->expectException( HttpException::class );
		$handler->run();
	}

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithSuggestedEditsDisabledForUser() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )->willReturn( false );
		$handler = $this->getInitializedNewcomerTaskCompleteHandlerWithConfigAndUser(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup,
			$this->createMock( RevisionLookup::class )
		);
		$validator = $this->createMock( Validator::class );
		$validator->method( 'validateParams' )->willReturn( [ 'taskTypeId' => 'foo', 'revId' => 123 ] );
		$handler->validate( $validator );
		$this->expectExceptionMessage( 'Suggested edits are not enabled or activated for your user.' );
		$this->expectException( HttpException::class );
		$handler->run();
	}

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunWithSuggestedEditsInvalidTaskTypeId() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )->willReturn( true );
		$handler = $this->getInitializedNewcomerTaskCompleteHandlerWithConfigAndUser(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup,
			$this->createMock( RevisionLookup::class )
		);
		$validator = $this->createMock( Validator::class );
		$validator->method( 'validateParams' )->willReturn( [ 'taskTypeId' => 'foo', 'revId' => 123 ] );
		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( 'Invalid task type ID: foo' );
		$handler->validate( $validator );
		$handler->run();
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
		$handler = $this->getInitializedNewcomerTaskCompleteHandlerWithConfigAndUser(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup,
			$revisionLookup
		);
		$validator = $this->createMock( Validator::class );
		$validator->method( 'validateParams' )->willReturn(
			[ 'taskTypeId' => 'copyedit', 'revId' => 123 ]
		);
		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( '123 is not a valid revision ID.' );
		$handler->validate( $validator );
		$handler->run();
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
		$handler = $this->getInitializedNewcomerTaskCompleteHandlerWithConfigAndUser(
			[ 'GEHomepageSuggestedEditsEnabled' => true ],
			$user,
			$userOptionsLookup,
			$revisionLookup
		);
		$validator = $this->createMock( Validator::class );
		$validator->method( 'validateParams' )->willReturn(
			[ 'taskTypeId' => 'copyedit', 'revId' => 123 ]
		);
		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( 'User ID 2 on revision does not match logged-in user ID 1.' );
		$handler->validate( $validator );
		$handler->run();
	}

	private function getInitializedNewcomerTaskCompleteHandlerWithConfigAndUser(
		array $config,
		UserIdentityValue $user,
		UserOptionsLookup $userOptionsLookup,
		RevisionLookup $revisionLookup
	) {
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getConfig' )->willReturn( new HashConfig( $config ) );
		$context->method( 'getUser' )->willReturn( $user );
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn(
			[ 'copyedit' => 'copyedit', 'link-recommendation' => 'link-recommendation' ]
		);
		$handler = $this->getNewcomerTaskCompleteHandler(
			$context,
			$userOptionsLookup,
			$configurationLoader,
			$revisionLookup
		);
		$authorityMock = $this->createMock( Authority::class );
		$authorityMock->method( 'getUser' )->willReturn( $user );
		$handler->init(
			$this->createMock( Router::class ),
			$this->createMock( RequestInterface::class ),
			[],
			$authorityMock,
			$this->createMock( ResponseFactory::class ),
			$this->createMock( HookContainer::class )
		);
		return $handler;
	}

	private function getNewcomerTaskCompleteHandler(
		IContextSource $context,
		UserOptionsLookup $userOptionsLookup,
		ConfigurationLoader $configurationLoader,
		RevisionLookup $revisionLookup
	): NewcomerTaskCompleteHandler {
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getLazyConnectionRef' )->willReturn(
			$this->createMock( DBConnRef::class )
		);
		$newcomerTasksChangeTagsHandler = new NewcomerTasksChangeTagsManager(
			$userOptionsLookup,
			$this->createNoOpMock( TaskTypeHandlerRegistry::class ),
			$configurationLoader,
			$this->createNoOpMock( \PrefixingStatsdDataFactoryProxy::class ),
			$revisionLookup,
			$loadBalancer,
			$context
		);
		return new NewcomerTaskCompleteHandler( $newcomerTasksChangeTagsHandler );
	}
}
