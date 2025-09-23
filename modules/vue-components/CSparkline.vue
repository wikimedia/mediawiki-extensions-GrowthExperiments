<template>
	<svg
		:id="`sparkline-${id}`"
		xmlns="http://www.w3.org/2000/svg"
		class="ext-growthExperiments-CSparkline"
	>
		<title>
			{{ title }}
		</title>
	</svg>
</template>

<script>
const { onMounted } = require( 'vue' );
const d3 = require( '../lib/d3/d3.min.js' );
let chart, sparkline, area, circle = null;

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	props: {
		title: {
			type: String,
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		data: {
			type: Object,
			required: true,
		},
		dimensions: {
			type: Object,
			required: true,
		},
		xAccessor: {
			type: Function,
			required: true,
		},
		yAccessor: {
			type: Function,
			required: true,
		},
		withCircle: {
			type: Boolean,
			default: false,
		},
	},
	setup( props ) {
		const plot = () => {
			// Add 2px of right padding for the circle (r = 1px) rendered on top of the last point
			const paddingRight = props.withCircle ? 2 : 0;
			chart.attr( 'viewBox', `0 0 ${ props.dimensions.width + paddingRight } ${ props.dimensions.height }` );
			const xDomain = d3.extent( props.data, props.xAccessor );
			const xScale = d3.scaleTime()
				.domain( xDomain )
				.range( [ 0, props.dimensions.width ] );
			const yDomain = [ 0, d3.max( props.data, props.yAccessor ) ];

			const yScale = d3.scaleLinear()
				.domain( yDomain )
				// Flip svg Y-axis coordinate system and add some a pixel on top to avoid cutting
				// off anti-aliasing pixels. Do not add a pixel on the bottom, that would make the
				// graph non-0-based, and it's rare for the pageviews to be 0.
				.range( [ props.dimensions.height, 1 ] );

			const lineGenerator = d3.line()
				.x( ( d ) => xScale( props.xAccessor( d ) ) )
				.y( ( d ) => yScale( props.yAccessor( d ) ) );

			const areaGenerator = d3.area()
				.x( ( d ) => xScale( props.xAccessor( d ) ) )
				.y1( ( d ) => yScale( props.yAccessor( d ) ) )
				.y0( props.dimensions.height );

			sparkline
				.data( [ props.data ] )
				.attr( 'd', lineGenerator )
				.attr( 'stroke-width', 1 )
				.attr( 'stroke-linejoin', 'round' )
				.attr( 'fill', 'none' );
			area
				.data( [ props.data ] )
				.attr( 'd', areaGenerator );

			if ( props.withCircle ) {
				const lastPoint = {
					x: props.xAccessor( props.data[ props.data.length - 1 ] ),
					y: props.yAccessor( props.data[ props.data.length - 1 ] ),
				};

				circle
					.attr( 'cx', xScale( lastPoint.x ) )
					.attr( 'cy', yScale( lastPoint.y ) )
					.attr( 'r', 1 );
			}

		};

		onMounted( () => {
			chart = d3.select( `#sparkline-${ props.id }` );
			// Append order is relevant. Render the line over the area
			area = chart.append( 'path' ).attr( 'class', 'ext-growthExperiments-CSparkline__area' );
			sparkline = chart.append( 'path' ).attr( 'class', 'ext-growthExperiments-CSparkline__line' );
			if ( props.withCircle ) {
				circle = chart.append( 'circle' ).attr( 'class', 'ext-growthExperiments-CSparkline__circle' );
			}
			plot();
		} );

		return {};
	},

};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-CSparkline {
	&__line {
		stroke: @background-color-progressive--focus;
	}

	&__circle {
		fill: @background-color-progressive--focus;
	}

	&__area {
		fill: @background-color-progressive-subtle;
	}
}
</style>
