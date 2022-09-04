-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: GrowthExperiments/sql/abstractSchemaChanges/patch-add_gels_anchor.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE  /*_*/growthexperiments_link_submissions
DROP  PRIMARY KEY;
ALTER TABLE  /*_*/growthexperiments_link_submissions
ADD  gels_anchor_offset INT UNSIGNED NOT NULL,
ADD  gels_anchor_length INT UNSIGNED NOT NULL;
ALTER TABLE  /*_*/growthexperiments_link_submissions
ADD  PRIMARY KEY (    gels_revision, gels_target, gels_anchor_offset  );