<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\GrowthExperimentsServices;
use HashBagOStuff;
use MediaWiki\Linker\LinkTarget;
use MediaWikiIntegrationTestCase;
use Title;
use User;

/**
 * @coversDefaultClass \GrowthExperiments\Config\WikiPageConfigWriter
 * @group Database
 */
class WikiPageConfigWriterTest extends MediaWikiIntegrationTestCase {
	/** @var GrowthExperimentsServices */
	private $geServices;

	/** @var Title */
	private $defaultConfigTitle;

	protected function setUp(): void {
		parent::setUp();

		$this->geServices = GrowthExperimentsServices::wrap(
			$this->getServiceContainer()
		);
		$this->defaultConfigTitle = $this->getServiceContainer()
			->getTitleFactory()
			->newFromText( 'MediaWiki:GrowthExperimentsConfig.json' );
	}

	/**
	 * @param LinkTarget|null $configPage
	 * @param User|null $performer
	 * @return WikiPageConfigWriter
	 */
	private function getWriter(
		?LinkTarget $configPage = null,
		?User $performer = null
	): WikiPageConfigWriter {
		return $this->geServices
			->getWikiPageConfigWriterFactory()
			->newWikiPageConfigWriter(
				$configPage ?? $this->defaultConfigTitle,
				$performer
			);
	}

	private function getLoader() {
		$loader = $this->geServices->getWikiPageConfigLoader();
		$loader->setCache( new HashBagOStuff(), HashBagOStuff::TTL_UNCACHEABLE );
		return $loader;
	}

	/**
	 * @covers ::save
	 * @covers ::setVariable
	 */
	public function testInitialSave() {
		$this->assertFalse( $this->defaultConfigTitle->exists() );
		$writer = $this->getWriter();
		$writer->setVariable( 'GEMentorshipEnabled', false );
		$writer->save();
		$this->assertTrue( $this->defaultConfigTitle->exists() );
		$this->assertArrayEquals( [
			'GEMentorshipEnabled' => false
		], $this->getLoader()->load( $this->defaultConfigTitle ) );
	}

	/**
	 * @covers ::setVariable
	 * @covers ::setVariables
	 * @covers ::save
	 */
	public function testSaveChange() {
		$writer = $this->getWriter();
		$writer->setVariables( [
			'GEMentorshipEnabled' => false,
			'GEHelpPanelAskMentor' => false,
		] );
		$writer->save();
		$this->assertTrue( $this->defaultConfigTitle->exists() );
		$this->assertArrayEquals( [
			'GEMentorshipEnabled' => false,
			'GEHelpPanelAskMentor' => false
		], $this->getLoader()->load( $this->defaultConfigTitle ) );

		$writer = $this->getWriter();
		$writer->setVariable( 'GEMentorshipEnabled', true );
		$writer->save();
		$this->assertTrue( $this->defaultConfigTitle->exists() );
		$this->assertArrayEquals( [
			'GEMentorshipEnabled' => true,
			'GEHelpPanelAskMentor' => false
		], $this->getLoader()->load( $this->defaultConfigTitle ) );
	}
}
