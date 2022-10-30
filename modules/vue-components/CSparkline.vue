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
const d3 = require( 'ext.growthExperiments.d3' );
const INSIDE_GRAPH_VERTICAL_PADDING = 8;
let chart, sparkline, area = null;

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	props: {
		title: {
			type: String,
			required: true
		},
		id: {
			type: String,
			required: true
		},
		data: {
			type: Object,
			required: true
		},
		dimensions: {
			type: Object,
			required: true
		},
		xAccessor: {
			type: Function,
			required: true
		},
		yAccessor: {
			type: Function,
			required: true
		}
	},
	setup( props ) {
		const plot = () => {
			chart.attr( 'viewBox', `0 0 ${props.dimensions.width} ${props.dimensions.height}` );
			const xDomain = d3.extent( props.data, props.xAccessor );
			const xScale = d3.scaleTime()
				.domain( xDomain )
				.range( [ 0, props.dimensions.width ] );
			const yDomain = [ 0, d3.max( props.data, props.yAccessor ) ];

			const yScale = d3.scaleLinear()
				.domain( yDomain )
				// Flip svg Y-axis coordinate system and add some vertical
				// gutter so that the sparkline does not appear cut on min/max values
				.range( [
					props.dimensions.height - INSIDE_GRAPH_VERTICAL_PADDING,
					0 + INSIDE_GRAPH_VERTICAL_PADDING
				] );
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
				.attr( 'stroke-width', 2 )
				.attr( 'stroke-linejoin', 'round' )
				.attr( 'fill', 'none' );
			area
				.data( [ props.data ] )
				.attr( 'd', areaGenerator );
		};

		onMounted( () => {
			chart = d3.select( `#sparkline-${props.id}` );
			sparkline = chart.append( 'path' ).attr( 'class', 'ext-growthExperiments-CSparkline__line' );
			area = chart.append( 'path' ).attr( 'class', 'ext-growthExperiments-CSparkline__area' );
			plot();
		} );

		return {};
	}

};
</script>

<style lang="less">
@import './variables.less';

.ext-growthExperiments-CSparkline {
	&__line {
		stroke: @background-color-progressive--focus;
	}

	&__area {
		fill: @background-color-progressive-subtle;
	}
}
</style>
