-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-modify_gemm_mentee_is_active_mwtinyint.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TEMPORARY TABLE /*_*/__temp__growthexperiments_mentor_mentee AS
SELECT
  gemm_mentee_id,
  gemm_mentor_role,
  gemm_mentor_id,
  gemm_mentee_is_active
FROM /*_*/growthexperiments_mentor_mentee;
DROP TABLE /*_*/growthexperiments_mentor_mentee;


CREATE TABLE /*_*/growthexperiments_mentor_mentee (
    gemm_mentee_id INTEGER UNSIGNED NOT NULL,
    gemm_mentor_role BLOB NOT NULL,
    gemm_mentor_id INTEGER UNSIGNED NOT NULL,
    gemm_mentee_is_active SMALLINT DEFAULT 1 NOT NULL,
    PRIMARY KEY(
      gemm_mentee_id, gemm_mentor_role
    )
  );
INSERT INTO /*_*/growthexperiments_mentor_mentee (
    gemm_mentee_id, gemm_mentor_role,
    gemm_mentor_id, gemm_mentee_is_active
  )
SELECT
  gemm_mentee_id,
  gemm_mentor_role,
  gemm_mentor_id,
  gemm_mentee_is_active
FROM
  /*_*/__temp__growthexperiments_mentor_mentee;
DROP TABLE /*_*/__temp__growthexperiments_mentor_mentee;

CREATE INDEX gemm_mentor ON /*_*/growthexperiments_mentor_mentee (
    gemm_mentor_id, gemm_mentee_is_active
  );
