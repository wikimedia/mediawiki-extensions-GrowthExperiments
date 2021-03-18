<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Title;
use TitleFactory;
use User;

/**
 * @covers \GrowthExperiments\Config\WikiPageConfigWriterFactory::newWikiPageConfigWriter
 */
class WikiPageConfigWriterFactoryTest extends MediaWikiUnitTestCase {
	public function testNewWikiPageConfigWriter(): void {
		$factory = new WikiPageConfigWriterFactory(
			$this->createNoOpMock( WikiPageConfigLoader::class ),
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( LoggerInterface::class ),
			$this->createNoOpMock( User::class )
		);
		$this->assertInstanceOf(
			WikiPageConfigWriter::class,
			$factory->newWikiPageConfigWriter(
				$this->createNoOpMock( Title::class ),
				null
			)
		);
	}
}
