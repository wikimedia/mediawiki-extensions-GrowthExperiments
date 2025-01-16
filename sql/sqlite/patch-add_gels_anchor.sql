-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-add_gels_anchor.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TEMPORARY TABLE /*_*/__temp__growthexperiments_link_submissions AS
SELECT
  gels_revision,
  gels_target,
  gels_page,
  gels_edit_revision,
  gels_user,
  gels_feedback
FROM /*_*/growthexperiments_link_submissions;
DROP TABLE /*_*/growthexperiments_link_submissions;


CREATE TABLE /*_*/growthexperiments_link_submissions (
    gels_revision INTEGER UNSIGNED NOT NULL,
    gels_target INTEGER UNSIGNED NOT NULL,
    gels_anchor_offset INTEGER UNSIGNED NOT NULL,
    gels_page INTEGER UNSIGNED NOT NULL,
    gels_edit_revision INTEGER UNSIGNED DEFAULT NULL,
    gels_user INTEGER UNSIGNED NOT NULL,
    gels_feedback VARCHAR(1) NOT NULL,
    gels_anchor_length INTEGER UNSIGNED NOT NULL,
    PRIMARY KEY(
      gels_revision, gels_target, gels_anchor_offset
    )
  );
INSERT INTO /*_*/growthexperiments_link_submissions (
    gels_revision, gels_target, gels_page,
    gels_edit_revision, gels_user, gels_feedback
  )
SELECT
  gels_revision,
  gels_target,
  gels_page,
  gels_edit_revision,
  gels_user,
  gels_feedback
FROM
  /*_*/__temp__growthexperiments_link_submissions;
DROP TABLE /*_*/__temp__growthexperiments_link_submissions;

CREATE INDEX gels_page_feedback_target ON /*_*/growthexperiments_link_submissions (
    gels_page, gels_feedback, gels_target
  );
