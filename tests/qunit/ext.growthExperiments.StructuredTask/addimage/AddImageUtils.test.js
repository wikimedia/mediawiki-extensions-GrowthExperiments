'use strict';

const pathToWidget = '../../../../modules/ext.growthExperiments.StructuredTask/addimage/AddImageUtils.js';
const AddImageUtils = require( pathToWidget );

/**
 * @param {Object} overrides
 * @return { mw.libs.ge.RecommendedImageMetadata}
 */
const getMetadata = ( overrides = {} ) => ( {
	descriptionUrl: 'https://commons.wikimedia.org/wiki/File:HMS_Pandora.jpg',
	thumbUrl: 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3d/HMS_Pandora.jpg/300px-HMS_Pandora.jpg',
	fullUrl: 'https://upload.wikimedia.org/wikipedia/commons/3/3d/HMS_Pandora.jpg',
	originalWidth: 1024,
	originalHeight: 768,
	mustRender: false,
	isVectorized: false,
	...overrides,
} );

/**
 * @param {number} size Thumbnail size
 * @return {string}
 */
const getThumbUrl = ( size ) => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3d/HMS_Pandora.jpg/' + size + 'px-HMS_Pandora.jpg';

QUnit.module( 'ext.growthExperiments.StructuredTask/addimage/AddImageUtils.js', {
	beforeEach() {
		mw.util.setOptionsForTest( { GenerateThumbnailOnParse: false } );
	},
} );

QUnit.test( 'getRenderData: target width < original width', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 2,
	};
	assert.deepEqual(
		AddImageUtils.getImageRenderData( getMetadata(), viewport ), {
			src: getThumbUrl( viewport.innerWidth * viewport.devicePixelRatio ),
			maxWidth: viewport.innerWidth,
		},
	);
} );

QUnit.test( 'getRenderData: the image file needs to be re-rasterized', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 2,
	};
	const metadata = getMetadata( { mustRender: true, originalWidth: 750 } );
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport ), {
			src: getThumbUrl( viewport.innerWidth * viewport.devicePixelRatio ),
			maxWidth: viewport.innerWidth,
		},
	);
} );

QUnit.test( 'getRenderData: the image file needs to be re-rasterized, renderWidth > originalWidth', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 2,
	};
	const metadata = getMetadata( { mustRender: true, originalWidth: 750 } );
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport, 1000 ), {
			src: getThumbUrl( 750 ),
			maxWidth: 750,
		},
	);
} );

QUnit.test( 'getRenderData: the image file needs to be re-rasterized, renderWidth < originalWidth', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 2,
	};
	const metadata = getMetadata( { mustRender: true, originalWidth: 750 } );
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport, 500 ), {
			src: getThumbUrl( 750 ),
			maxWidth: 500,
		},
	);
} );

QUnit.test( 'getRenderData: vector image', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 2,
	};
	const metadata = getMetadata( { isVectorized: true } );
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport ), {
			src: getThumbUrl( viewport.innerWidth * viewport.devicePixelRatio ),
			maxWidth: viewport.innerWidth,
		},
	);
} );

QUnit.test( 'getRenderData: target width > original width', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 2,
	};
	const metadata = getMetadata( { originalWidth: 700 } );
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport ), {
			src: metadata.fullUrl,
			maxWidth: metadata.originalWidth,
		},
	);
} );

QUnit.test( 'getRenderData: target width > original width due to px ratio', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 3,
	};
	const metadata = getMetadata();
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport ), {
			src: metadata.fullUrl,
			maxWidth: metadata.originalWidth,
		},
	);
} );

QUnit.test( 'getRenderData: 3x target width', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 3,
	};
	const metadata = getMetadata( { originalWidth: 5000 } );
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport ), {
			src: getThumbUrl( viewport.devicePixelRatio * viewport.innerWidth ),
			maxWidth: viewport.innerWidth,
		},
	);
} );

QUnit.test( 'getRenderData: 2.5x target width', ( assert ) => {
	const viewport = {
		innerHeight: 629,
		innerWidth: 375,
		devicePixelRatio: 2.5,
	};
	const metadata = getMetadata( { originalWidth: 5000 } );
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport ), {
			src: getThumbUrl( Math.floor( viewport.devicePixelRatio * viewport.innerWidth ) ),
			maxWidth: viewport.innerWidth,
		},
	);
} );

QUnit.test( 'getRenderData: vertical image with landscape viewport', ( assert ) => {
	const viewport = {
		innerWidth: 629,
		innerHeight: 375,
		devicePixelRatio: 2,
	};
	const metadata = getMetadata( { originalWidth: 768, originalHeight: 1024 } );
	const targetWidth = ( metadata.originalWidth / metadata.originalHeight ) * viewport.innerHeight;
	assert.deepEqual(
		AddImageUtils.getImageRenderData( metadata, viewport ), {
			src: getThumbUrl( Math.floor( targetWidth * viewport.devicePixelRatio ) ),
			maxWidth: Math.floor( targetWidth ),
		},
	);
} );

QUnit.test( 'getRenderData: with specified render width', ( assert ) => {
	const viewport = {
		innerWidth: 629,
		innerHeight: 375,
		devicePixelRatio: 2,
	};
	const renderWidth = 100;
	assert.deepEqual(
		AddImageUtils.getImageRenderData( getMetadata(), viewport, renderWidth ), {
			src: getThumbUrl( Math.floor( renderWidth * viewport.devicePixelRatio ) ),
			maxWidth: Math.floor( renderWidth ),
		},
	);
} );
