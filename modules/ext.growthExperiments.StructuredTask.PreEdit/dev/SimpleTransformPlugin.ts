type transformCallback = ( stringBeingTransformed: string ) => string;

const transform = (
	initialString: string,
	callbackArray: transformCallback[],
): string => callbackArray.reduce(
	( stringBeingTransformed, callback ) => callback( stringBeingTransformed ),
	initialString,
);

const checkExcludeStatus = ( filePath: string, excludePatterns: string[] ): boolean => {
	for ( const excludePattern of excludePatterns ) {
		// The excludePatterns are fully defined in code by us developers, so this is safe.
		if ( new RegExp( excludePattern, 'igm' ).test( filePath ) ) {
			return true;
		}
	}
	return false;
};

const transformPlugin = (
	{ callbackArray = [], excludePatterns = [] }: {
		callbackArray?: transformCallback[];
		excludePatterns?: string[];
	},
): { name: string; transform: ( string: string, filePath: string ) => ( boolean | string ) } => ( {
	name: 'transformPlugin',
	transform: ( string: string, filePath: string ) => {
		if ( checkExcludeStatus( filePath, excludePatterns ) ) {
			return false;
		}
		return transform( string, callbackArray );
	},
} );

export default transformPlugin;
