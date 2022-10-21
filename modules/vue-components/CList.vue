<template>
	<div></div>
</template>

<script>
const { h, cloneVNode } = require( 'vue' );
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	props: {
		unstyled: { type: Boolean },
		striped: { type: Boolean, default: false }
	},
	render() {
		// When using v-for Vue 3 wraps all generated elements into
		// a single fragment, extract the children from it
		const children = this.$slots.default()[ 0 ].children;
		const clones = children.map( ( vnode, index ) => {
			const extraProps = {};
			if ( this.striped ) {
				extraProps.backgroundColor = index % 2 ? 'framed' : 'base';
			}
			return cloneVNode( vnode, extraProps );
		} );

		return h( 'ul', {
			class: [
				'ext-growthExperiments-CList',
				this.unstyled ? 'ext-growthExperiments-CList--unstyled' : ''
			]
		}, clones );
	}
};
</script>

<style lang="less">
@import './variables.less';

.ext-growthExperiments-CList {
	&--unstyled {
		list-style: none;
		// Remove vector margin applied on ul's
		margin-left: 0;
	}
}
</style>
