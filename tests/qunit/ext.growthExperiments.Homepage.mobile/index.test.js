'use strict';

QUnit.module( 'ext.growthExperiments.Homepage.mobile/index.js', QUnit.newMwEnvironment( {
	config: {
		homepagemodules: {
			'suggested-edits': {
				'task-preview': {
					title: 'Article title'
				}
			}
		},
		'wgGEHomepageModuleActionData-suggested-edits': {
			taskCount: 1
		}
	},
	beforeEach() {
		// Avoid running the 'mobile.init' initialization code which requires 'mediawiki.router'
		// module and others that trigger proxy warnings since the scripts are not loaded
		// see modules/ext.growthExperiments.Homepage.mobile/index.js#beforeMobileInit()
		this.sandbox.stub( mw.loader, 'getState' ).withArgs( 'mobile.init' ).returns( false );
	}
} ) );

QUnit.test( 'should hide page views in small preview card', function ( assert ) {
	const done = assert.async();
	const GrowthTasksApi = require( '../../../modules/ext.growthExperiments.Homepage.SuggestedEdits/GrowthTasksApi.js' );
	const task = {
		title: 'Article title',
		difficulty: 'easy',
		tasktype: 'copyedit',
		pageviews: 200
	};
	this.sandbox.stub( GrowthTasksApi.prototype, 'getExtraDataFromPcs' )
		.returns( $.Deferred().resolve( task ).promise() );
	const HomepageMobileModule = require( '../../../modules/ext.growthExperiments.Homepage.mobile/index.js' );
	HomepageMobileModule.loadExtraDataForSuggestedEdits( '.some-class', false )
		.then( ( previewTask ) => {
			assert.strictEqual( previewTask.pageviews, null );
			done();
		} );
} );
