<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\HelpPanel\QuestionRecord;

class QuestionRecordTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( QuestionRecord::class, $this->getDefaultQuestionRecord() );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::jsonSerialize
	 */
	public function testJsonSerialize() {
		$this->assertEquals(
			'{"questionText":"foo","sectionHeader":"bar","revId":123,"resultUrl":' .
			'"https:\/\/mediawiki.org\/foo","contentModel":"wikitext",' .
			'"archiveUrl":"https:\/\/mediawiki.org\/bar","timestamp":1235678,' .
			'"isArchived":false,"isVisible":true}',
			json_encode( $this->getDefaultQuestionRecord() )
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::newFromArray
	 */
	public function testNewFromEmptyArray() {
		// Timestamp added as a workaround for not stubbing out call to wfTimestamp()
		$questionRecord = QuestionRecord::newFromArray( [ 'timestamp' => 123 ] );
		$this->assertSame( '', $questionRecord->getQuestionText() );
		$this->assertSame( '', $questionRecord->getSectionHeader() );
		$this->assertSame( 0, $questionRecord->getRevId() );
		$this->assertEquals( 123, $questionRecord->getTimestamp() );
		$this->assertSame( '', $questionRecord->getResultUrl() );
		$this->assertSame( '', $questionRecord->getArchiveUrl() );
		$this->assertFalse( $questionRecord->isArchived() );
		$this->assertTrue( $questionRecord->isVisible() );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::newFromArray
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::getQuestionText
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::getSectionHeader
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::getRevId
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::getTimestamp
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::getResultUrl
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::getArchiveUrl
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::isArchived
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::isVisible
	 */
	public function testNewFromArray() {
		$questionRecord = QuestionRecord::newFromArray( [
			'questionText' => 'foo',
			'sectionHeader' => 'bar',
			'revId' => 123,
			'timestamp' => 456,
			'resultUrl' => 'https://mediawiki.org',
			'archiveUrl' => 'https://mediawiki.org/archived',
			'isArchived' => true,
			'isVisible' => false,
		] );
		$this->assertEquals( 'foo', $questionRecord->getQuestionText() );
		$this->assertEquals( 'bar', $questionRecord->getSectionHeader() );
		$this->assertEquals( 123, $questionRecord->getRevId() );
		$this->assertEquals( 456, $questionRecord->getTimestamp() );
		$this->assertEquals( 'https://mediawiki.org', $questionRecord->getResultUrl() );
		$this->assertEquals( 'https://mediawiki.org/archived', $questionRecord->getArchiveUrl() );
		$this->assertTrue( $questionRecord->isArchived() );
		$this->assertFalse( $questionRecord->isVisible() );
		$this->assertEquals( CONTENT_MODEL_WIKITEXT, $questionRecord->getContentModel() );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::setQuestionText
	 */
	public function testSetQuestionText() {
		$questionRecord = $this->getDefaultQuestionRecord();
		$this->assertEquals( 'foo', $questionRecord->getQuestionText() );
		$questionRecord->setQuestionText( 'bar' );
		$this->assertEquals( 'bar', $questionRecord->getQuestionText() );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::setArchived
	 */
	public function testSetArchived() {
		$questionRecord = $this->getDefaultQuestionRecord();
		$this->assertFalse( $questionRecord->isArchived() );
		$questionRecord->setArchived( true );
		$this->assertTrue( $questionRecord->isArchived() );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::setTimestamp
	 */
	public function testSetTimestamp() {
		$questionRecord = $this->getDefaultQuestionRecord();
		$this->assertEquals( 1235678, $questionRecord->getTimestamp() );
		$questionRecord->setTimestamp( 123 );
		$this->assertEquals( 123, $questionRecord->getTimestamp() );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::setVisible
	 */
	public function testSetVisible() {
		$questionRecord = $this->getDefaultQuestionRecord();
		$this->assertTrue( $questionRecord->isVisible() );
		$questionRecord->setVisible( false );
		$this->assertFalse( $questionRecord->isVisible() );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionRecord::setArchiveUrl
	 */
	public function testSetArchiveUrl() {
		$questionRecord = $this->getDefaultQuestionRecord();
		$this->assertEquals( 'https://mediawiki.org/bar', $questionRecord->getArchiveUrl() );
		$questionRecord->setArchiveUrl( 'https://blah' );
		$this->assertEquals( 'https://blah', $questionRecord->getArchiveUrl() );
	}

	private function getDefaultQuestionRecord() {
		return new QuestionRecord(
			'foo',
			'bar',
			123,
			1235678,
			'https://mediawiki.org/foo',
			CONTENT_MODEL_WIKITEXT,
			'https://mediawiki.org/bar',
			false,
			true
		);
	}

}
