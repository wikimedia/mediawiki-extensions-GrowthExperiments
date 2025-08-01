-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-add_gels_anchor.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE growthexperiments_link_submissions
  DROP CONSTRAINT growthexperiments_link_submissions_pkey;

ALTER TABLE growthexperiments_link_submissions
  ADD gels_anchor_offset INT NOT NULL;

ALTER TABLE growthexperiments_link_submissions
  ADD gels_anchor_length INT NOT NULL;

ALTER TABLE growthexperiments_link_submissions
  ADD PRIMARY KEY (
  gels_revision, gels_target, gels_anchor_offset
);
