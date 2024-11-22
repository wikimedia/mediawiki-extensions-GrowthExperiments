/**
 * @typedef {Object} MissingPageQueryResponse
 * @property {number} ns
 * @property {string} title
 * @property {""} missing
 */

/**
 * @typedef {Object} DataQueryResponse
 * @property {number} pageid
 * @property {number} ns
 * @property {string} title
 * @property {string|null} description
 * @property {Object|null} thumbnail
 */

class PageSummaryRepository {

	/**
	 * @param {InstanceType<typeof mw.Api>} api
	 */
	constructor( api ) {
		this.api = api;
	}

	/**
	 * @param {string[]} titles
	 * @return {Promise<Record<string,{title:string;description:string|null;thumbnail:Object|null}|null>>}
	 */
	async loadPageSummariesAndThumbnails( titles ) {

		let linkPageData;
		try {
			linkPageData = await this.api.get( {
				action: 'query',
				prop: 'description|pageimages|extracts',
				titles: titles.join( '|' ),
				pithumbsize: 56 * 2,
				exintro: true,
				exchars: 100,
				explaintext: true,
			} );
		} catch ( error ) {
			return titles.reduce(
				/**
				 * @param {Record<string, null>} acc
				 * @param {string} title
				 * @return {Record<string, null>}
				 */
				( acc, title ) => {
					acc[ title ] = null;
					return acc;
				},
				{},
			);
		}

		/**
		 * @type {Record<string,{title:string;description:string|null;thumbnail:Object|null}|null>}
		 */
		const result = {};

		/**
		 * @param {MissingPageQueryResponse|DataQueryResponse} page
		 */
		Object.values( linkPageData.query.pages ).forEach( ( page ) => {
			if ( page.missing !== undefined ) {
				result[ page.title ] = null;
				return;
			}
			result[ page.title ] = {
				description: page.description ? page.description : page.extract,
				thumbnail: page.thumbnail,
				title: page.title,
			};
		} );

		return result;
	}
}

module.exports = PageSummaryRepository;
