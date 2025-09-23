/**
 * SVGO Configuration
 * Compatible to v4.0.0+
 * Recommended options from:
 * https://www.mediawiki.org/wiki/Manual:Coding_conventions/SVG#Exemplified_safe_configuration
 */

'use strict';

module.exports = {
	plugins: [
		{
			// Set of built-in plugins enabled by default.
			name: 'preset-default',
			params: {
				overrides: {
					cleanupIds: false,
					removeDesc: false,
					// If the SVG doesn't start with an XML declaration, then its MIME type will
					// be detected as "text/plain" rather than "image/svg+xml" by libmagic and,
					// consequently, MediaWiki's CSSMin CSS minifier. libmagic's default database
					// currently requires that SVGs contain an XML declaration:
					// https://github.com/threatstack/libmagic/blob/master/magic/Magdir/sgml#L5
					removeXMLProcInst: false, // https://phabricator.wikimedia.org/T327446
					convertPathData: false, // https://github.com/svg/svgo/issues/880 https://github.com/svg/svgo/issues/1487
					removeMetadata: false, // Copyright-Violation
					removeHiddenElems: false, // source for converted text2path
					removeUnknownsAndDefaults: false, // removes Flow-Text: https://commons.wikimedia.org/wiki/User:JoKalliauer/RepairFlowRoot
					cleanupNumericValues: false, // https://github.com/svg/svgo/issues/1080
					minifyStyles: false, // https://github.com/svg/svgo/issues/888
					removeComments: false, // reduces readability
					removeEditorsNSData: false, // https://github.com/svg/svgo/issues/1096
					collapseGroups: false, // https://github.com/svg/svgo/issues/1057
					removeEmptyContainers: false, // https://github.com/svg/svgo/issues/1194 https://github.com/svg/svgo/issues/1618
					convertTransform: false, // https://github.com/svg/svgo/issues/988 https://github.com/svg/svgo/issues/1021
					inlineStyles: false, // https://github.com/svg/svgo/issues/1486
				},
			},
		},
		'removeRasterImages',
		'sortAttrs',
	],
	// Set whitespace according to Wikimedia Coding Conventions.
	// @see https://github.com/svg/svgo/blob/main/lib/stringifier.js#L39 for available options.
	js2svg: {
		eol: 'lf',
		finalNewline: true,
		// Configure the indent to tabs (default 4 spaces) used by '--pretty' here.
		indent: '\t',
		pretty: true,
	},
	multipass: true,
};
