// REVIEW maybe use some dependency injection for MW
function convertNumber( n ) {
	return mw.language.convertNumber( n );
}

exports = module.exports = {
	convertNumber: convertNumber
};
