<template>
	<div class="data-table-cell-mentee">
		<cdx-icon
			class="star-icon"
			:icon="value.isStarred ? cdxIconUnStar : cdxIconStar"
			:icon-label="$i18n( 'tbd-favorite' )"
			@click="toggleStarred"
		></cdx-icon>
		<div class="user-info">
			<a
				:class="{ new: !value.userPageExists }"
				:href="usernameHref"
			>
				{{ value.username }}
			</a>
			<div class="last-seen">
				{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-active-ago', value.lastActive ) }}
			</div>
		</div>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconStar, cdxIconUnStar } = require( '../icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon
	},
	props: {
		value: { type: Object, required: true }
	},
	emits: [ 'toggle-starred' ],
	setup() {
		return {
			cdxIconStar,
			cdxIconUnStar
		};
	},
	computed: {
		usernameHref: function () {
			return ( new mw.Title( this.value.username, 2 ) ).getUrl();
		}
	},
	methods: {
		toggleStarred: function () {
			this.$emit( 'toggle-starred', { starred: this.value.isStarred, userId: this.value.userId } );
		}
	}
};
</script>

<style lang="less">
@import '../variables.less';

@mentee-table-last-seen-font-size: 12px;

.data-table-cell-mentee {
	display: flex;

	.star-icon {
		min-width: 32px;
		min-height: 32px;
		opacity: 0.66;

		> svg {
			cursor: pointer;
		}
	}

	.user-info {
		padding: 0 8px;
		flex: 2;

		.last-seen {
			font-size: @mentee-table-last-seen-font-size;
			color: @colorGray500;
		}
	}
}
</style>
