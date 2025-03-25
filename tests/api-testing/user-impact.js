'use strict';

const { assert, REST } = require( 'api-testing' );

describe( 'POST and GET requests to /growthexperiments/v0/user-impact/{user}', () => {

	const client = new REST( 'rest.php/growthexperiments/v0/user-impact/' );

	function getExpectedResponse() {
		return {
			'@version': 11,
			userId: 1,
			userName: 'Admin',
			receivedThanksCount: 10,
			givenThanksCount: 5,
			recentEditsWithoutPageviews: [],
			editCountByNamespace: [
				2
			],
			editCountByDay: {
				'2022-08-24': 1,
				'2022-08-25': 1
			},
			revertedEditCount: 1,
			newcomerTaskEditCount: 2,
			editCountByTaskType: {
				copyedit: 1,
				'link-recommendation': 1
			},
			lastEditTimestamp: 1661385600,
			longestEditingStreak: '',
			totalEditsCount: 2,
			dailyTotalViews: {
				'2022-08-24': 1000,
				'2022-08-25': 2000
			},
			totalPageviewsCount: 3000,
			totalUserEditCount: 2,
			topViewedArticlesCount: 3000,
			topViewedArticles: {
				Bar: {
					firstEditDate: '2022-08-24',
					newestEdit: '20220825143818',
					viewsCount: 2000,
					views: {
						'2022-08-24': 1000,
						'2022-08-25': 1000
					}
				},
				Foo: {
					firstEditDate: '2022-08-24',
					newestEdit: '20220825143817',
					viewsCount: 1000,
					views: {
						'2022-08-24': 500,
						'2022-08-25': 500
					}
				}
			}
		};
	}

	it( 'GET: Data loaded for mocked user 1 via static user impact lookup (see GrowthExperiments.LocalSettings.php)', async () => {
		const { body: sourceBody } = await client.get( encodeURIComponent( '#1' ) );
		const expectedResponse = getExpectedResponse();
		// These vary based on the current date, and probably not worth asserting anything about.
		delete sourceBody.topViewedArticles.Foo.pageviewsUrl;
		delete sourceBody.topViewedArticles.Bar.pageviewsUrl;
		delete sourceBody.generatedAt;
		assert.deepEqual( sourceBody, expectedResponse );
	} );

	it( 'POST: Data loaded for mocked user 1 via static user impact lookup (see GrowthExperiments.LocalSettings.php)', async () => {
		const { body: sourceBody } = await client.post( encodeURIComponent( '#1' ) );
		const expectedResponse = getExpectedResponse();
		// These vary based on the current date, and probably not worth asserting anything about.
		delete sourceBody.topViewedArticles.Foo.pageviewsUrl;
		delete sourceBody.topViewedArticles.Bar.pageviewsUrl;
		delete sourceBody.generatedAt;
		assert.deepEqual( sourceBody, expectedResponse );
	} );

} );
