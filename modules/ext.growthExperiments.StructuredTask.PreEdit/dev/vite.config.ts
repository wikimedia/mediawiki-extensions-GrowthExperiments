import { defineConfig } from 'vite';
// @ts-expect-error Something is strange about that plugin's types, but it works
import vue from '@vitejs/plugin-vue';
import transformPlugin from './SimpleTransformPlugin';

module.exports = defineConfig( {
	plugins: [
		transformPlugin( {
			callbackArray: [
				( str: string ) => str.replace( /const \{([\s\w,]+)\} = require\( '([.@\w/-]+)' \);/gm, 'import {$1} from \'$2\';' ),
				( str: string ) => str.replace( /const ([\s\w,]+) = require\( '([.@\w/-]+)' \);/gm, 'import $1 from \'$2\';' ),
				( str: string ) => str.replace( /module\.exports = (?:exports = )?/gm, 'export default ' ),
			],
		} ),
		vue(),
	],
	resolve: {
		alias: {
			'mediawiki.skin.codex-design-tokens': '@wikimedia/codex-design-tokens',
			'mediawiki.skin.codex': '@wikimedia/codex',
			'./codex-icons.json': '@wikimedia/codex-icons',
			'../common/codex-icons.json': '@wikimedia/codex-icons',
		},
	},
	css: {
		preprocessorOptions: {
			less: {
				paths: [
					'../../../../../skins/Vector/resources/mediawiki.less/vector-2022/',
					'../../../../../resources/src/mediawiki.less/',
				],
			},
		},
	},

	server: {
		port: 3000,
	},
} );
