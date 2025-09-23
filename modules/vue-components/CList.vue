<template>
	<div></div>
</template>

<script>
const { h, cloneVNode } = require( 'vue' );
// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	props: {
		unstyled: { type: Boolean },
		striped: { type: Boolean, default: false },
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
				this.unstyled ? 'ext-growthExperiments-CList--unstyled ext-growthExperiments-increaseSpecificity' : '',
			],
		}, clones );
	},
};
</script>

<style lang="less">
.ext-growthExperiments-CList {
	&--unstyled.ext-growthExperiments-increaseSpecificity {
		// Remove Minerva list-style-type on .content ul
		list-style: none;
		// Remove Minerva margin on .content ul
		padding-left: 0;
		// Remove vector margin applied on ul's
		margin-left: 0;
	}
}
</style>
