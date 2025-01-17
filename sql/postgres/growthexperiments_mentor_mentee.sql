-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: sql/growthexperiments_mentor_mentee.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE growthexperiments_mentor_mentee (
  gemm_mentee_id INT NOT NULL,
  gemm_mentor_role TEXT NOT NULL,
  gemm_mentor_id INT NOT NULL,
  gemm_mentee_is_active BOOLEAN DEFAULT true NOT NULL,
  PRIMARY KEY(
    gemm_mentee_id, gemm_mentor_role
  )
);

CREATE INDEX gemm_mentor ON growthexperiments_mentor_mentee (
  gemm_mentor_id, gemm_mentee_is_active
);
