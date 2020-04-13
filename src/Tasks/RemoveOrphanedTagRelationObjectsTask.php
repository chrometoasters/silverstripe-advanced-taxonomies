<?php

namespace Chrometoaster\AdvancedTaxonomies\Tasks;

use Chrometoaster\AdvancedTaxonomies\Extensions\DataObjectTaxonomiesDataExtension;
use Chrometoaster\AdvancedTaxonomies\Models\DataObjectTaxonomyTerm;
use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;

/**
 * Class RemoveOrphanedTagRelationObjectsTask
 *
 * All versions before 3.1.2 had a bug where the tag relation object linking
 * the tagged object with a term (a tag) could get orphaned — the tag could have been deleted,
 * the owner could have been deleted or the joining object could have been improperly deleted.
 *
 * This task removes all tag relation objects that shouldn't exists in both draft and live stages
 * for existing classes. If a class ceased to exist or was renamed, the script won't remove the records
 * for that class as it can't identify its database table name.
 *
 * It should be safe to run the task repetitively, however, it is recommended to create a database
 * backup before running it.
 *
 * Run the task:   sake dev/tasks/at-remove-orphaned-tag-relation-objects
 *
 */
class RemoveOrphanedTagRelationObjectsTask extends BuildTask
{
    private static $segment = 'at-remove-orphaned-tag-relation-objects';

    protected $title = 'Advanced Taxonomies — Remove orphaned tag relation objects';


    /**
     * Main
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->removeTagRelationObjectsWithDeletedTerm();

        $this->removeTagRelationObjectsWithDeletedOwner();

        // last task to remove Live orphans possibly created by the above
        $this->removeTagRelationObjectsOrphanedInLive();
    }


    /**
     * Remove tag relation objects orphaned in Live stage
     */
    private function removeTagRelationObjectsOrphanedInLive()
    {
        $tableStage = $this->getTableName(DataObjectTaxonomyTerm::class);
        $tableLive  = $tableStage . '_Live';

        $query = SQLSelect::create("\"{$tableLive}\".\"ID\" AS RecordID", "\"{$tableLive}\"");
        $query
            ->addLeftJoin($tableStage, "\"{$tableLive}\".\"ID\" = \"{$tableStage}\".\"ID\"")
            ->addWhere("\"{$tableStage}\".\"ID\" IS NULL");

        $count = $query->count();

        if ($count > 0) {
            DB::alteration_message("Found {$count} orphaned tag relation records in Live stage.", 'obsolete');
            $ids = $query->execute()->column('RecordID');

            $deleted = $this->deleteTagRelationObjects($ids, Versioned::LIVE);

            DB::alteration_message("Removed {$deleted} out of {$count} records.", 'deleted');
        } else {
            DB::alteration_message('No orphaned tag relation records found in Live stage.', 'obsolete');
        }
    }


    /**
     * Remove tag relation objects orphaned after removing a term
     */
    private function removeTagRelationObjectsWithDeletedTerm()
    {
        $table      = $this->getTableName(DataObjectTaxonomyTerm::class);
        $jointTable = $this->getTableName(TaxonomyTerm::class);

        $query = SQLSelect::create("\"{$table}\".\"ID\" AS RecordID", "\"{$table}\"");
        $query
            ->addLeftJoin($jointTable, "\"{$table}\".\"JointObjectID\" = \"{$jointTable}\".\"ID\"")
            ->addWhere("\"{$jointTable}\".\"ID\" IS NULL");

        $count = $query->count();

        if ($count > 0) {
            DB::alteration_message("Found {$count} tag relation records without a taxonomy term.", 'obsolete');
            $ids = $query->execute()->column('RecordID');

            $deleted = $this->deleteTagRelationObjects($ids, Versioned::DRAFT);

            DB::alteration_message("Removed {$deleted} out of {$count} records.", 'deleted');
        } else {
            DB::alteration_message('No tag relation objects without a taxonomy term found,', 'obsolete');
        }
    }


    /**
     * Remove tag relation objects orphaned after removing the owner
     */
    private function removeTagRelationObjectsWithDeletedOwner()
    {
        try {
            $subClasses = ClassInfo::subclassesFor(DataObject::class, $includeBaseClass = false);
        } catch (\ReflectionException $e) {
            $subClasses = [];
        }

        foreach ($subClasses as $subClassKey => $subClassName) {
            if (!singleton($subClassName)->hasExtension(DataObjectTaxonomiesDataExtension::class)) {
                unset($subClasses[$subClassKey]);
            }
            if (!ClassInfo::hasTable($subClassName)) {
                unset($subClasses[$subClassKey]);
            }
        }

        if (!empty($subClasses)) {
            $table  = $this->getTableName(DataObjectTaxonomyTerm::class);
            $allIDs = [];

            foreach ($subClasses as $subClass) {
                $ownerTable = $this->getTableName($subClass);

                $query = SQLSelect::create("\"{$table}\".\"ID\" AS RecordID", "\"{$table}\"");
                $query
                    ->addLeftJoin($ownerTable, "\"{$table}\".\"OwnerObjectID\" = \"{$ownerTable}\".\"ID\"")
                    ->addWhere(["\"{$ownerTable}\".\"ID\" IS NULL", "\"{$table}\".\"OwnerObjectClass\" = ?" => $subClass]);

                $count = $query->count();

                if ($count > 0) {
                    DB::alteration_message("Found {$count} tag relation objects with deleted '{$subClass}' owner.", 'obsolete');
                    $ids    = $query->execute()->column('RecordID');
                    $allIDs = array_merge($allIDs, $ids);
                } else {
                    DB::alteration_message("No tag relation objects with deleted '{$subClass}' owner found.", 'obsolete');
                }
            }

            $allIDs = array_unique($allIDs);

            if (!empty($allIDs)) {
                $count = count($allIDs);

                $deleted = $this->deleteTagRelationObjects($allIDs, Versioned::DRAFT);

                DB::alteration_message("Removed {$deleted} out of {$count} records.", 'deleted');
            }
        }
    }


    /**
     * Delete tag relation objects by IDs from a given stage
     *
     * @param array $ids
     * @param string $stage
     * @return int
     */
    private function deleteTagRelationObjects(array $ids, string $stage): int
    {
        $deleted = 0;

        if (!empty($ids)) {
            $orig = Versioned::get_stage();
            Versioned::set_stage($stage);

            $objects = DataObjectTaxonomyTerm::get()->filterAny('ID', $ids);

            /** @var DataObject $item */
            foreach ($objects as $item) {
                $item->delete();
                $deleted++;
            }

            Versioned::set_stage($orig);
        }

        return $deleted;
    }


    /**
     * Get db table name for a class
     *
     * @param string $class
     * @return string
     */
    private function getTableName(string $class): string
    {
        return DataObjectSchema::create()->tableName($class);
    }
}
