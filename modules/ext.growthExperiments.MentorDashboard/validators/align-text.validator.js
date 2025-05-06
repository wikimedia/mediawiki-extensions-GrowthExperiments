/**
 * Validator for CSS text-align values.
 * To be used in  components that have an "align" property.
 *
 * @param {string} value — The value of the text-align property
 * @return {boolean} Whereas the value passed is a valid CSS text-align token.
 */
module.exports = exports = ( value ) => [ 'left', 'right', 'center' ].includes( value );
