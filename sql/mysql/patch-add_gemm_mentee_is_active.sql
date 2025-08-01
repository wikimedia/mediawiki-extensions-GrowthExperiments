-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-add_gemm_mentee_is_active.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP INDEX gemm_mentor ON /*_*/growthexperiments_mentor_mentee;

ALTER TABLE /*_*/growthexperiments_mentor_mentee
  ADD gemm_mentee_is_active TINYINT(1) DEFAULT 1 NOT NULL;

CREATE INDEX gemm_mentor ON /*_*/growthexperiments_mentor_mentee (
  gemm_mentor_id, gemm_mentee_is_active
);
