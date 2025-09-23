/**
 * Filter functions to use in Vue components. They will
 * be accessible in the component instance under this.$filters.*
 * So far each new filter needs to be added per Vue application.
 */

/**
 * Wrapper for mw.language.convertNumber
 *
 * @param {number} n The nuber to convert
 * @return {string} The number converted to a localised numeral
 */
function convertNumber( n ) {
	return mw.language.convertNumber( n );
}

exports = module.exports = {
	convertNumber: convertNumber,
};
