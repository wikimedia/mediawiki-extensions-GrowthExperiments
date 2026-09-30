/**
 * The API's own reasons for a failed request (blocked, read-only, rate limited),
 * one per line, for a request made with errorformat=plaintext.
 *
 * @param {Object} [result] The second argument of a failed mw.Api promise
 * @return {string|null} Null when the API gave no reason
 */
module.exports = function apiErrorText( result ) {
	const errors = ( result && result.errors ) || [];
	return errors.map( ( e ) => e.text || e[ '*' ] || e.code ).join( '\n' ) || null;
};
