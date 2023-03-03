# GrowthExperiments Frontend Documentation

This site provides documentation to showcase the Vue components used in building
the frontend for GrowthExperiments features.

It can also serve for creating UI prototypes using [Vue 3](https://vuejs.org/)
and Wikimedia's [Codex](https://doc.wikimedia.org/codex) library.

To learn how to contribute to this documentation please see the repository's
[README.md](https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/GrowthExperiments/+/refs/heads/master/documentation/frontend/README.md)
file.

## Demos

### Production

These are demos of components currently used in production.

- _to be created!_

### Proof-of-concept

These are not complete demonstrations, they exist only to showcase how one can
create demos for GrowthExperiments components.

 - [Topic selection dialog](./demos/topic-selection-dialog)
 - [Post edit dialog](./demos/post-edit-dialog)

## Importing components into MediaWiki

Due to MediaWiki's ResourceLoader constraints on ES modules usage and the
limited support for Vue SFCs in MediaWiki (see [Use_Single-file_components](https://www.mediawiki.org/wiki/Vue.js#Use_Single-file_components))
it's not possible to document the existing GrowthExperiments components
with VitePress. Therefore the existing components under:
 - `/modules/vue-components`
 - `/modules/ext.growthExperiments.MentorDashboard`
 - `/modules/ext.growthExperiments.Homepage.NewImpact`

can't be directly imported into the __docs__ project. Alternatives to
solve this problem are being considered in [Phab T328125](https://phabricator.wikimedia.org/T328125).