<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests;

// files in maintenance/ are not autoloaded to avoid accidental usage, so load explicitly
require_once __DIR__ . '/../../../../maintenance/migrateCommunityConfig.php';

use GrowthExperiments\Maintenance\MigrateCommunityConfig;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \GrowthExperiments\Maintenance\MigrateCommunityConfig
 * @group Database
 */
class MigrateCommunityConfigTest extends MaintenanceBaseTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'CommunityConfiguration' );
		$this->hideDeprecated( 'GrowthExperimentsWikiPageConfigLoader' );
	}

	protected function getMaintenanceClass(): string {
		return MigrateCommunityConfig::class;
	}

	public function testMigrateEnglishWikipediaGrowthExperimentsConfig(): void {
		$enWpNewcomerTasksConfigAsOf2024_04_22 = '{
  "copyedit": {
    "disabled": false,
    "templates": [
      "Awkward",
      "Inappropriate person",
      "In-universe",
      "Tone",
      "Advert",
      "Peacock"
    ],
    "excludedTemplates": [
      "No newcomer task"
    ],
    "excludedCategories": [],
    "type": "template-based",
    "group": "easy",
    "learnmore": "Wikipedia:Writing better articles"
  },
  "expand": {
    "disabled": false,
    "templates": [
      "Stub",
      "Expand section",
      "Expand lead"
    ],
    "excludedTemplates": [
      "No newcomer task"
    ],
    "excludedCategories": [],
    "type": "template-based",
    "group": "hard",
    "learnmore": "Wikipedia:Writing better articles"
  },
  "image-recommendation": {
    "disabled": false,
    "group": "medium",
    "templates": [],
    "excludedTemplates": [],
    "excludedCategories": [],
    "type": "image-recommendation",
    "learnmore": ""
  },
  "link-recommendation": {
    "disabled": false,
    "group": "easy",
    "templates": [],
    "excludedTemplates": [],
    "excludedCategories": [],
    "type": "link-recommendation",
    "learnmore": ""
  },
  "links": {
    "disabled": false,
    "templates": [
      "Underlinked",
      "Dead end"
    ],
    "excludedTemplates": [
      "No newcomer task"
    ],
    "excludedCategories": [],
    "type": "template-based",
    "group": "easy",
    "learnmore": "Help:Introduction to editing with VisualEditor/3"
  },
  "references": {
    "disabled": false,
    "templates": [
      "Unreferenced",
      "Unreferenced section",
      "More references",
      "More references needed section"
    ],
    "excludedTemplates": [
      "No newcomer task"
    ],
    "excludedCategories": [],
    "type": "template-based",
    "group": "medium",
    "learnmore": "Wikipedia:Verifiability"
  },
  "section-image-recommendation": {
    "type": "section-image-recommendation",
    "group": "medium",
    "maxTasksPerDay": 25
  },
  "update": {
    "disabled": false,
    "templates": [
      "Update"
    ],
    "excludedTemplates": [
      "No newcomer task"
    ],
    "excludedCategories": [],
    "type": "template-based",
    "group": "medium",
    "learnmore": "Wikipedia:Pages needing attention"
  }
}';
		$enWpGEConfigAsOf2024_05_22 = '{
	"GEHelpPanelAskMentor": true,
	"GEHelpPanelExcludedNamespaces": [],
	"GEHelpPanelHelpDeskPostOnTop": false,
	"GEHelpPanelHelpDeskTitle": "",
	"GEHelpPanelLinks": [
		{
			"title": "Wikipedia:Writing better articles",
			"text": "How to write a good article",
			"id": "Q10973854"
		},
		{
			"title": "Help:Introduction to editing with VisualEditor/1",
			"text": "How to edit a page",
			"id": "Q27888216"
		},
		{
			"title": "Help:Introduction to images with VisualEditor/1",
			"text": "How to add an image",
			"id": "Q27919584"
		},
		{
			"title": "Help:Introduction to referencing with VisualEditor/1",
			"text": "How to edit a citation",
			"id": "Q24238629"
		},
		{
			"title": "Wikipedia:Article wizard",
			"text": "How to create a new article",
			"id": "Q10968373"
		}
	],
	"GEHelpPanelReadingModeNamespaces": [
		2,
		4,
		12
	],
	"GEHelpPanelSearchNamespaces": [
		4,
		12
	],
	"GEHelpPanelSuggestedEditsPreferredEditor": {
		"template-based": "visualeditor",
		"link-recommendation": "machineSuggestions"
	},
	"GEHelpPanelViewMoreTitle": "Help:Contents",
	"GEHomepageManualAssignmentMentorsList": "Wikipedia:Growth Team features/Mentor list/Manual",
	"GEHomepageMentorsList": "Wikipedia:Growth Team features/Mentor list",
	"GEHomepageSuggestedEditsIntroLinks": {
		"create": "Help:Creating pages",
		"image": "Help:Viewing media"
	},
	"GEInfoboxTemplates": [],
	"GELevelingUpKeepGoingNotificationThresholds": [
		1,
		0
	],
	"GEMentorshipAutomaticEligibility": true,
	"GEMentorshipEnabled": true,
	"GEMentorshipMinimumAge": 90,
	"GEMentorshipMinimumEditcount": 500
}';

		$this->editPage( 'MediaWiki:NewcomerTasks.json', $enWpNewcomerTasksConfigAsOf2024_04_22 );
		$this->editPage( 'MediaWiki:GrowthExperimentsConfig.json', $enWpGEConfigAsOf2024_05_22 );

		$this->expectOutputRegex(
			'/^(?![\s\S]*Errors found:)/'
		);
		$this->maintenance->execute();

		$this->assertExpectedToActualConfigWithOverrides(
			[
				'$version' => '1.0.0',
				'GEHelpPanelAskMentor' => 'mentor-talk-page',
				'GEHelpPanelExcludedNamespaces' => [],
				'GEHelpPanelHelpDeskPostOnTop' => 'bottom',
				'GEHelpPanelHelpDeskTitle' => '',
				'GEHelpPanelLinks' => [
					(object)[
						'title' => 'Wikipedia:Writing better articles',
						'text' => 'How to write a good article',
						'id' => 'Q10973854',
					],
					(object)[
						'title' => 'Help:Introduction to editing with VisualEditor/1',
						'text' => 'How to edit a page',
						'id' => 'Q27888216',
					],
					(object)[
						'title' => 'Help:Introduction to images with VisualEditor/1',
						'text' => 'How to add an image',
						'id' => 'Q27919584',
					],
					(object)[
						'title' => 'Help:Introduction to referencing with VisualEditor/1',
						'text' => 'How to edit a citation',
						'id' => 'Q24238629',
					],
					(object)[
						'title' => 'Wikipedia:Article wizard',
						'text' => 'How to create a new article',
						'id' => 'Q10968373',
					],
				],
				'GEHelpPanelReadingModeNamespaces' => [ 2, 4, 12 ],
				'GEHelpPanelSearchNamespaces' => [ 4, 12 ],
				'GEHelpPanelViewMoreTitle' => 'Help:Contents',
			],
			[],
			'MediaWiki:GrowthExperimentsHelpPanel.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			[
				'$version' => '1.0.0',
				'GEMentorshipAutomaticEligibility' => true,
				'GEMentorshipEnabled' => true,
				'GEMentorshipMinimumAge' => 90,
				'GEMentorshipMinimumEditcount' => 500,
			],
			[],
			'MediaWiki:GrowthExperimentsMentorship.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			[
				'$version' => '1.0.0',
				'GEHomepageSuggestedEditsIntroLinks' => (object)[
					'create' => 'Help:Creating pages',
					'image' => 'Help:Viewing media',
				],
				'GELevelingUpKeepGoingNotificationThresholds' => 0,
			],
			[],
			'MediaWiki:GrowthExperimentsHomepage.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			[
				'$version' => '1.0.0',
				'GEInfoboxTemplates' => [],
				'copyedit' => (object)[
					'disabled' => false,
					'templates' => [
						"Awkward",
						"Inappropriate person",
						"In-universe",
						"Tone",
						"Advert",
						"Peacock"
					],
					'excludedTemplates' => [ 'No newcomer task' ],
					'excludedCategories' => [],
					'learnmore' => 'Wikipedia:Writing better articles'
				],
				'expand' => (object)[
					'disabled' => false,
					'templates' => [
						"Stub",
						"Expand section",
						"Expand lead"
					],
					'excludedTemplates' => [ 'No newcomer task' ],
					'excludedCategories' => [],
					'learnmore' => 'Wikipedia:Writing better articles'
				],
				'image_recommendation' => (object)[
					'disabled' => false,
					'templates' => [],
					'excludedTemplates' => [],
					'excludedCategories' => [],
					'learnmore' => ''
				],
				'link_recommendation' => (object)[
					'disabled' => false,
					'templates' => [],
					'excludedTemplates' => [],
					'excludedCategories' => [],
					'learnmore' => ''
				],
				'links' => (object)[
					'disabled' => false,
					'templates' => [
						"Underlinked",
						"Dead end"
					],
					'excludedTemplates' => [ 'No newcomer task' ],
					'excludedCategories' => [],
					'learnmore' => 'Help:Introduction to editing with VisualEditor/3'
				],
				'references' => (object)[
					'disabled' => false,
					'templates' => [
						"Unreferenced",
						"Unreferenced section",
						"More references",
						"More references needed section"
					],
					'excludedTemplates' => [ 'No newcomer task' ],
					'excludedCategories' => [],
					'learnmore' => 'Wikipedia:Verifiability'
				],
				'section_image_recommendation' => (object)[
					'maxTasksPerDay' => 25,
				],
				'update' => (object)[
					'disabled' => false,
					'templates' => [
						"Update"
					],
					'excludedTemplates' => [ 'No newcomer task' ],
					'excludedCategories' => [],
					'learnmore' => 'Wikipedia:Pages needing attention'
				],
			],
			[],
			'MediaWiki:GrowthExperimentsSuggestedEdits.json'
		);
	}

	public function testMigrateEmptyGrowthExperimentsConfig(): void {
		// No validation errors about NULL values, all config missing (its empty), nothing migrated
		$this->expectOutputRegex(
			'/^(?![\s\S]*Errors found:)/'
		);

		$this->maintenance->execute();

		// assert all the new config pages are empty ( === '{}')
		$this->assertExpectedToActualConfigWithOverrides(
			[ '$version' => '1.0.0' ],
			[],
			'MediaWiki:GrowthExperimentsHelpPanel.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			[ '$version' => '1.0.0' ],
			[],
			'MediaWiki:GrowthExperimentsMentorship.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			[ '$version' => '1.0.0' ],
			[],
			'MediaWiki:GrowthExperimentsHomepage.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			[ '$version' => '1.0.0' ],
			[],
			'MediaWiki:GrowthExperimentsSuggestedEdits.json'
		);
	}

	public static function provideTestData(): iterable {
		yield 'all defaults' => [
			[],
			[],
			[],
			[],
			[],
			[],
		];
		yield 'ask mentor false' => [
			[ 'GEHelpPanelAskMentor' => false ],
			[],
			[ 'GEHelpPanelAskMentor' => 'help-desk-page' ],
			[],
			[],
			[],

		];
		yield 'post on top true' => [
			[ 'GEHelpPanelHelpDeskPostOnTop' => true ],
			[],
			[ 'GEHelpPanelHelpDeskPostOnTop' => 'top' ],
			[],
			[],
			[],
		];
	}

	/**
	 * @dataProvider provideTestData
	 */
	public function testMigrateDefaultGrowthExperimentsConfig(
		$geConfigOverrides,
		$newcomerTasksOverrides,
		$expectedHelpPanelOverrides,
		$expectedHomepageOverrides,
		$expectedSuggestedEditsOverrides,
		$expectedMentorshipOverrides
	): void {
		$defaultNewcomerTasks = [
			'copyedit' => (object)[
				'disabled' => false,
				'group' => 'easy',
				'templates' => [ 'In-universe', 'Copy' ],
				'excludedTemplates' => [ 'No newcomer task' ],
				'excludedCategories' => [],
				'type' => 'template-based',
				'learnmore' => 'Wikipedia:Writing better articles'
			],
			'expand' => (object)[
				'disabled' => false,
				'group' => 'hard',
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'type' => 'template-based',
			],
			'image-recommendation' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'type' => 'image-recommendation',
				'group' => 'medium',
				'maxTasksPerDay' => 25,
				'learnmore' => '',
			],
			'link-recommendation' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'type' => 'link-recommendation',
				'group' => 'easy',
				'learnmore' => '',
				'maximumLinksToShowPerTask' => 3,
				'maxTasksPerDay' => 25,
				'excludedSections' => [],
			],
			'links' => (object)[
				'disabled' => false,
				'group' => 'easy',
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'type' => 'template-based',
			],
			'references' => (object)[
				'disabled' => false,
				'group' => 'medium',
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'type' => 'template-based',
			],
			'section-image-recommendation' => (object)[
				'disabled' => true,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'type' => 'section-image-recommendation',
				'group' => 'medium',
				'maxTasksPerDay' => 25,
				'learnmore' => '',
			],
			'update' => (object)[
				'disabled' => false,
				'group' => 'medium',
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'type' => 'template-based',
			],
		];

		// TODO: allow for unsetting options with `null` in the overrides
		$effectiveNewcomerTasks = array_merge( $defaultNewcomerTasks, $newcomerTasksOverrides );
		$this->editPage( 'MediaWiki:NewcomerTasks.json', json_encode( $effectiveNewcomerTasks ) );

		$defaultGEConfig = [
			'GEHelpPanelAskMentor' => true,
			'GEHelpPanelExcludedNamespaces' => [],
			'GEHelpPanelHelpDeskPostOnTop' => false,
			'GEHelpPanelHelpDeskTitle' => 'Main_Page',
			'GEHelpPanelLinks' => [
				(object)[
					'title' => 'Project:Help',
					'text' => 'Site help',
					'id' => 'Project:Help',
				],
			],
			'GEHelpPanelReadingModeNamespaces' => [ 2, 4, 12 ],
			'GEHelpPanelSearchNamespaces' => [ 4, 12 ],
			'GEHelpPanelViewMoreTitle' => 'Main_Page',
			'GEHomepageSuggestedEditsIntroLinks' => (object)[
				'create' => 'Help:Creating pages',
				'image' => 'Help:Images',
			],
			'GEInfoboxTemplates' => [],
			'GELevelingUpGetStartedMaxTotalEdits' => 10,
			'GELevelingUpKeepGoingNotificationThresholds' => [ 1, 4 ],
			'GEMentorshipAutomaticEligibility' => true,
			'GEMentorshipEnabled' => true,
			'GEMentorshipMinimumAge' => 90,
			'GEMentorshipMinimumEditcount' => 500,
			'GEPersonalizedPraiseDays' => 7,
			'GEPersonalizedPraiseDefaultNotificationsFrequency' => 168,
			'GEPersonalizedPraiseMaxEdits' => 500,
			'GEPersonalizedPraiseMinEdits' => 8,
		];
		// TODO: allow for unsetting options with `null` in the overrides
		$effectiveGEConfig = array_merge( $defaultGEConfig, $geConfigOverrides );

		$this->editPage( 'MediaWiki:GrowthExperimentsConfig.json', json_encode( $effectiveGEConfig ) );

		$this->maintenance->execute();

		$defaultHelpPanelConfig = [
			'$version' => '1.0.0',
			'GEHelpPanelAskMentor' => 'mentor-talk-page',
			'GEHelpPanelExcludedNamespaces' => [],
			'GEHelpPanelHelpDeskPostOnTop' => 'bottom',
			'GEHelpPanelHelpDeskTitle' => 'Main_Page',
			'GEHelpPanelLinks' => [
				(object)[
					'title' => 'Project:Help',
					'text' => 'Site help',
					'id' => 'Project:Help',
				],
			],
			'GEHelpPanelReadingModeNamespaces' => [ 2, 4, 12 ],
			'GEHelpPanelSearchNamespaces' => [ 4, 12 ],
			'GEHelpPanelViewMoreTitle' => 'Main_Page',
		];
		$defaultMentorshipConfig = [
			'$version' => '1.0.0',
			'GEMentorshipAutomaticEligibility' => true,
			'GEMentorshipEnabled' => true,
			'GEMentorshipMinimumAge' => 90,
			'GEMentorshipMinimumEditcount' => 500,
			'GEPersonalizedPraiseMaxEdits' => 500,
			'GEPersonalizedPraiseDefaultNotificationsFrequency' => 168,
			'GEPersonalizedPraiseMinEdits' => 8,
			'GEPersonalizedPraiseDays' => 7,
		];
		$defaultHomepageConfig = [
			'$version' => '1.0.0',
			'GEHomepageSuggestedEditsIntroLinks' => (object)[
				'create' => 'Help:Creating pages',
				'image' => 'Help:Images',
			],
			'GELevelingUpGetStartedMaxTotalEdits' => 10,
			'GELevelingUpKeepGoingNotificationThresholds' => 4,
		];
		$defaultSuggestedEditsConfig = [
			'$version' => '1.0.0',
			'link_recommendation' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'learnmore' => '',
				'maximumLinksToShowPerTask' => 3,
				'maxTasksPerDay' => 25,
				'excludedSections' => [],
			],
			'copyedit' => (object)[
				'disabled' => false,
				'templates' => [ 'In-universe', 'Copy' ],
				'excludedTemplates' => [ 'No newcomer task' ],
				'excludedCategories' => [],
				'learnmore' => 'Wikipedia:Writing better articles'
			],
			'expand' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
			],
			'section_image_recommendation' => (object)[
				'disabled' => true,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'maxTasksPerDay' => 25,
				'learnmore' => '',
			],
			'image_recommendation' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'learnmore' => '',
				'maxTasksPerDay' => 25,
			],
			'links' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
			],
			'references' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
			],
			'update' => (object)[
				'disabled' => false,
				'templates' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
			],
			'GEInfoboxTemplates' => [],
		];
		$this->assertExpectedToActualConfigWithOverrides(
			$defaultHelpPanelConfig,
			$expectedHelpPanelOverrides,
			'MediaWiki:GrowthExperimentsHelpPanel.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			$defaultMentorshipConfig,
			$expectedMentorshipOverrides,
			'MediaWiki:GrowthExperimentsMentorship.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			$defaultHomepageConfig,
			$expectedHomepageOverrides,
			'MediaWiki:GrowthExperimentsHomepage.json'
		);
		$this->assertExpectedToActualConfigWithOverrides(
			$defaultSuggestedEditsConfig,
			$expectedSuggestedEditsOverrides,
			'MediaWiki:GrowthExperimentsSuggestedEdits.json'
		);
	}

	private function assertExpectedToActualConfigWithOverrides(
		array $default,
		array $overrides,
		string $pagename
	): void {
		// TODO: allow for unsetting options with `null` in the overrides
		$expectedConfig = array_merge( $default, $overrides );
		$services = $this->getServiceContainer();
		$title = $services->getTitleFactory()->newFromText( $pagename );
		$page = $services->getWikiPageFactory()->newFromTitle( $title );
		$this->assertTrue( $page->exists() );
		$content = $page->getContent();
		$this->assertEquals( (object)$expectedConfig, $content->getData()->getValue(), $pagename );
	}
}
