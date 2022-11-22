'use strict';

const { assert, REST } = require( 'api-testing' );

describe( 'GET /growthexperiments/v0/user-impact/{user}', () => {

	const client = new REST( 'rest.php/growthexperiments/v0/user-impact/' );

	it( 'Data loaded for mocked user 1 via static suggester (see GrowthExperiments.LocalSettings.php)', async () => {
		const { body: sourceBody } = await client.get( encodeURIComponent( '#1' ) );
		const expectedResponse = {
			'@version': 5,
			userId: 1,
			userName: 'Admin',
			receivedThanksCount: 10,
			recentEditsWithoutPageviews: [],
			editCountByNamespace: [
				2
			],
			editCountByDay: {
				'2022-08-24': 1,
				'2022-08-25': 1
			},
			timeZone: [
				'ZoneInfo|660|Australia/Sydney',
				660
			],
			newcomerTaskEditCount: 2,
			lastEditTimestamp: 1661385600,
			longestEditingStreak: '',
			totalEditsCount: 2,
			dailyTotalViews: {
				'2022-08-24': 1000,
				'2022-08-25': 2000
			},
			topViewedArticles: {
				Bar: {
					firstEditDate: '2022-08-24',
					newestEdit: '20220825143818',
					views: {
						'2022-08-24': 1000,
						'2022-08-25': 1000
					}
				},
				Foo: {
					firstEditDate: '2022-08-24',
					newestEdit: '20220825143817',
					views: {
						'2022-08-24': 500,
						'2022-08-25': 500
					}
				}
			}
		};
		// These vary based on the current date, and probably not worth asserting anything about.
		delete sourceBody.topViewedArticles.Foo.pageviewsUrl;
		delete sourceBody.topViewedArticles.Bar.pageviewsUrl;
		delete sourceBody.generatedAt;
		assert.deepEqual( sourceBody, expectedResponse );
	} );

} );
