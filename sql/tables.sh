#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
ENGINES='mysql sqlite postgres'
TABLES='growthexperiments_link_recommendations growthexperiments_link_submissions growthexperiments_mentee_data growthexperiments_mentor_mentee growthexperiments_user_impact'
SCHEMA_CHANGES=`ls "$DIR"/abstractSchemaChanges/*.json | xargs -n1 basename`

for engine in $ENGINES; do
	for table in $TABLES; do
		mwscript generateSchemaSql.php --json "$DIR/$table.json" --type $engine --sql "$DIR/$engine/$table.sql"
	done
	for change in $SCHEMA_CHANGES; do
		mwscript generateSchemaChangeSql.php --json "$DIR/abstractSchemaChanges/$change" --type $engine --sql "$DIR/$engine/${change%.json}.sql"
	done
done
