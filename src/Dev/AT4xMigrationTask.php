<?php

namespace Chrometoaster\AdvancedTaxonomies\Dev;

use Chrometoaster\AdvancedTaxonomies\Models\BaseObject;
use Chrometoaster\AdvancedTaxonomies\Models\BaseTerm;
use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

class AT4xMigrationTask extends BuildTask
{
    private static $segment = 'AT4x-migration-task';

    protected $title = 'Migrate AT db data from version 3.x to version 4.x';

    protected $description = 'Migrate AT db data from version 3.x to version 4.x';

    protected $basicFields = ['ID', 'RecordID', 'Version'];

    /**
     * Static run wrapper with a dummy request
     *
     * @throws Exception
     */
    public static function migrate()
    {
        self::create()->run(new HTTPRequest('GET', '/'));
    }


    /**
     * @param HTTPRequest $request
     * @throws Exception
     */
    public function run($request)
    {
        if (!Config::forClass(self::class)->get('enable_v4_migration')) {
            DB::get_schema()->alterationMessage('Data migration to Advanced Taxonomies 4.x format is disabled.', 'notice');

            return;
        }

        $schema          = DataObject::getSchema();
        $termTable       = $schema->tableName(TaxonomyTerm::class);
        $baseObjectTable = $schema->tableName(BaseObject::class);
        $baseTermTable   = $schema->tableName(BaseTerm::class);

        // Safety net
        if (!($baseObjectTable && $baseTermTable && $termTable)) {
            throw new Exception(sprintf("One of the required db tables (%s, %s, %s) doesn't exist, did you run dev/build with the flush param?", $baseObjectTable, $baseTermTable, $termTable));
        }

        // Get row numbers for all models
        $termsCount       = DB::query(sprintf('SELECT COUNT(1) FROM "%s"', $termTable))->value();
        $baseObjectsCount = DB::query(sprintf('SELECT COUNT(1) FROM "%s"', $baseObjectTable))->value();
        $baseTermsCount   = DB::query(sprintf('SELECT COUNT(1) FROM "%s"', $baseTermTable))->value();

        // Skip migration when BaseObject or BaseTerm have any data in them
        if ($baseObjectsCount || $baseTermsCount) {
            DB::get_schema()->alterationMessage("BaseObject or BaseTerm table already contains data, skipping the migration.", 'notice');
            DB::get_schema()->alterationMessage('If you want to disable the migration completely and hide this message, set AT4xMigrationTask::enable_v4_migration to false.', 'notice');

            return;
        }

        // Skip migration if there's no data at all
        if (($baseObjectsCount == $baseTermsCount) && ($baseObjectsCount == $termsCount) && ($baseObjectsCount == 0)) {
            DB::get_schema()->alterationMessage("There's no data to migrate, skipping the migration.", 'notice');
            DB::get_schema()->alterationMessage('If you want to disable the migration completely, e.g. for fresh installs, set AT4xMigrationTask::enable_v4_migration to false.', 'notice');

            return;
        }

        $versionedFields = array_keys(Config::inst()->get(Versioned::class, 'db_for_versions_table'));
        $termTableFields = DB::query(sprintf('SHOW COLUMNS FROM "%s"', $termTable))->column();

        DB::get_conn()->withTransaction(function () use ($schema, $termTable, $termTableFields, $versionedFields) {

            // cater for standard versioning db table suffiixes
            $dbTableSuffixes = [
                '',
                '_' . Versioned::LIVE,
                '_Versions', // add RecordID and Version fields
            ];

            foreach ($dbTableSuffixes as $dbTableSuffix) {
                foreach ([BaseObject::class, BaseTerm::class] as $model) {

                    // get db table with suffix and all uninherited db fields, remove fields not shared with TaxonomyTerm
                    $dbTable = $schema->tableName($model) . $dbTableSuffix;
                    $dbFields = array_keys($schema->databaseFields($model, false));
                    $dbFields = array_intersect($dbFields, $termTableFields);

                    // add special versioning fields
                    if ($dbTableSuffix === '_Versions') {
                        if (get_parent_class($model) === DataObject::class) {
                            array_push($dbFields, ...$versionedFields);
                        } else {
                            array_push($dbFields, ...$this->basicFields);
                        }
                    }


                    $currentTermTableFields = DB::query(sprintf('SHOW COLUMNS FROM "%s"', $termTable . $dbTableSuffix))->column();
                    $dbFieldsDiff = array_intersect(array_unique($dbFields), $currentTermTableFields);

                    // if there are no columns to migrate, i.e. if the only fields are the basic versioning related field
                    // and an ID, skip the table for the current model
                    if (empty($dbFieldsDiff) || empty(array_diff($dbFieldsDiff, $this->basicFields)) || ($dbFieldsDiff == ['ID'])) {
                        DB::get_schema()->alterationMessage(sprintf('No column data to migrate to %s table.', $dbTable), 'notice');

                        continue;
                    }


                    DB::get_schema()->alterationMessage(sprintf('Migrating data to %s table.', $dbTable), 'changed');

                    // make sure the table is empty to avoid foreign key conflicts
                    DB::query(sprintf('DELETE FROM "%s"', $dbTable));

                    // create a list of unique db table fields
                    $dbFieldsList = implode('","', array_unique($dbFields));

                    // prepare the insert query
                    $sql = sprintf(
                        'INSERT INTO "%s" ("%s") SELECT "%s" FROM "%s"',
                        $dbTable,
                        $dbFieldsList,
                        $dbFieldsList,
                        $termTable . $dbTableSuffix
                    );

                    DB::query($sql);
                }
            }

            DB::get_schema()->alterationMessage('Taxonomy terms data migrated successfully into Advanced Taxonomies 4.x format.', 'changed');
        }, function () {
            DB::get_schema()->alterationMessage('Failed to migrate taxonomy terms data to Advanced Taxonomies 4.x format.', 'error');
        }, false, true);
    }
}
