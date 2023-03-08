module.exports = exports = {
	plugins: {
		'postcss-prefix-selector': {
			/**
			 * Add a special raw container or vp-raw class
			 * that can be used to prevent style and router conflicts
			 * with VitePress.
			 */
			prefix: ':not(:where(.vp-raw *))',
			includeFiles: [ /vp-doc\.css/ ],
			transform( prefix, _selector ) {
				const [ selector, pseudo = '' ] = _selector.split( /(:\S*)$/ );
				return selector + prefix + pseudo;
			}
		}
	}
};
