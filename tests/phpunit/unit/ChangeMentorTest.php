<?php

namespace GrowthExperiments;

use IContextSource;
use LogPager;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use User;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\ChangeMentor
 */
class ChangeMentorTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( ChangeMentor::class,
			new ChangeMentor(
				$this->getUserMock(),
				$this->getUserMock(),
				$this->getContextMock(),
				new NullLogger(),
				$this->getMentorMock(),
				$this->getMockBuilder( LogPager::class )
					->disableOriginalConstructor()
					->getMock()
			)
		);
	}

	/**
	 * @covers ::wasMentorChanged
	 */
	public function testWasMentorChangedSuccess() {
		$logPagerMock = $this->getMockBuilder( LogPager::class )
			->disableOriginalConstructor()
			->getMock();
		$resultMock = $this->getMockBuilder( IResultWrapper::class )
			->getMock();
		$resultMock->method( 'fetchRow' )->willReturn( [ 'foo' ] );
		$logPagerMock->method( 'getResult' )->willReturn( $resultMock );
		$changeMentor = new ChangeMentor(
			$this->getUserMock(),
			$this->getUserMock(),
			$this->getContextMock(),
			new NullLogger(),
			$this->getMentorMock(),
			$logPagerMock
		);
		$this->assertNotFalse( $changeMentor->wasMentorChanged() );
	}

	private function getUserMock() {
		return $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function getContextMock() {
		return $this->getMockBuilder( IContextSource::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function getMentorMock() {
		return $this->getMockBuilder( Mentor::class )
			->disableOriginalConstructor()
			->getMock();
	}

}
