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

        // Skip migration if all tables have the same number of records
        if (($termsCount === $baseObjectsCount) && ($termsCount == $baseTermsCount)) {
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
                            array_push($dbFields, 'RecordID', 'Version');
                        }
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
