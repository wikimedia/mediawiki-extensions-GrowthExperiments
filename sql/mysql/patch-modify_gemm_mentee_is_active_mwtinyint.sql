-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-modify_gemm_mentee_is_active_mwtinyint.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE /*_*/growthexperiments_mentor_mentee
  CHANGE gemm_mentee_is_active gemm_mentee_is_active TINYINT DEFAULT 1 NOT NULL;
