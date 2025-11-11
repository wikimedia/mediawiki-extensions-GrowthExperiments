<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\ReviseTone\SubpageReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskType;
use MediaWiki\Content\JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ReviseTone\SubpageReviseToneRecommendationProvider
 * @group Database
 */
class SubpageReviseToneRecommendationProviderTest extends MediaWikiIntegrationTestCase {

	public function testGetReturnsDataFromToneJsonSubpage() {
		$this->overrideConfigValue( 'GEReviseToneRecommendationProvider', 'subpage' );
		$pageTitle = 'TestArticle';
		$toneJson = [
			'wiki' => 'enwiki',
			'article_id' => '123',
			'revision_id' => '456789',
			'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis ut lorem sit amet nisi tempus
			semper nec in massa. Duis mollis erat eu elit finibus, ac laoreet ante vehicula. Cras luctus dolor et odio
			mattis, porttitor commodo lorem molestie. Proin mattis elit nec lectus bibendum accumsan. Aenean eu
			ultrices nulla. Phasellus pulvinar scelerisque volutpat. Nunc et enim a urna volutpat bibendum at at mi.
			Quisque elementum cursus nisi, nec iaculis ipsum fringilla at. Pellentesque habitant morbi tristique
			senectus et netus et malesuada fames ac turpis egestas. Vestibulum posuere laoreet erat posuere feugiat.
			In ac erat auctor, venenatis enim non, pharetra augue. Praesent vel ligula eget velit consequat eleifend
			non hendrerit orci. Sed eget mauris porttitor, dictum nibh id, interdum ante. Cras blandit tincidunt sem,
			ac laoreet metus faucibus mollis. Duis pretium lorem vitae mi porta, a fermentum eros eleifend. ',
			'prediction' => [
				'status_code' => 200,
				'model_name' => 'edit-check',
				'model_version' => 'v1',
				'check_type' => 'tone',
				'language' => 'en',
				'page_title' => 'this is a test',
				'prediction' => true,
				'probability' => 0.831,
				'details' => [],
			],
		];
		$this->editPage( $pageTitle, 'Some article content' );
		$subpage = Title::newFromText( "{$pageTitle}/tone.json" );
		$this->editPage( $subpage, new JsonContent( json_encode( $toneJson, JSON_PRETTY_PRINT ) ) );

		/** @var SubpageReviseToneRecommendationProvider $provider */
		$provider = MediaWikiServices::getInstance()->get( 'GrowthExperimentsReviseToneRecommendationProvider' );

		$taskType = new ReviseToneTaskType( 'tone-check', 'easy' );

		$recs = $provider->get( Title::newFromText( $pageTitle ), $taskType );
		if ( $recs instanceof StatusValue ) {
			$this->assertStatusGood( $recs, 'Provider status should be OK' );
		}

		$recArray = $recs->toArray();
		$this->assertSame( $toneJson['text'], $recArray['paragraphText'] );
	}

}
