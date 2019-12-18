<?php

namespace Chrometoaster\AdvancedTaxonomies\Forms;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\ManyManyThroughList;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows as OriginalGridFieldOrderableRows;

if (!class_exists(OriginalGridFieldOrderableRows::class)) {
    return;
}

/**
 * Class GridFieldOrderableRows
 *
 * This is to fix of the original GridFieldOrderableRows for supporting ManyManyThroughList with 'from' being
 * polymorphic, ie. the 'from' of  the "through data object" is DataObject. The fixes are replacing two calling
 * statements on ManyManyThroughQueryManipulator::getForeignKey() with ManyManyThroughQueryManipulator::getForeignIDKey()
 * without any other changes. The code is changed right after being copied from
 * symbiote/silverstripe-gridfieldextensions module v3.2.1
 *
 * TODO: Remove the class and its usage once the issue addressed by
 * https://github.com/symbiote/silverstripe-gridfieldextensions/issues/295
 * has been solved
 */
class GridFieldOrderableRows extends OriginalGridFieldOrderableRows
{
    /**
     * Forms a WHERE clause for the table the sort column is defined on.
     * e.g. ID = 5
     * e.g. ID IN(5, 8, 10)
     * e.g. SortOrder = 5 AND RelatedThing.ID = 3
     * e.g. SortOrder IN(5, 8, 10) AND RelatedThing.ID = 3
     *
     * @param DataList $list
     * @param int|string|array $ids a single number, or array of numbers
     *
     * @return string
     */
    protected function getSortTableClauseForIds(DataList $list, $ids)
    {
        if (is_array($ids)) {
            $value = 'IN (' . implode(', ', array_map('intval', $ids)) . ')';
        } else {
            $value = '= ' . (int) $ids;
        }

        if ($this->isManyMany($list)) {
            $introspector = $this->getManyManyInspector($list);
            $extra        = $list instanceof ManyManyList ?
                $introspector->getExtraFields() :
                DataObjectSchema::create()->fieldSpecs($introspector->getJoinClass(), DataObjectSchema::DB_ONLY);
            $key        = $introspector->getLocalKey();
            $foreignKey = $introspector->getForeignIDKey();
            $foreignID  = (int) $list->getForeignID();

            if ($extra && array_key_exists($this->getSortField(), $extra)) {
                return sprintf(
                    '"%s" %s AND "%s" = %d',
                    $key,
                    $value,
                    $foreignKey,
                    $foreignID
                );
            }
        }

        return "\"ID\" $value";
    }


    /**
     * Used to get sort orders from a many many through list relationship record, rather than the current
     * record itself.
     *
     * @param ManyManyList|ManyManyThroughList $list
     * @param mixed $sortField
     * @return array|int[] Sort orders for the
     */
    protected function getSortValuesFromManyManyThroughList($list, $sortField)
    {
        $manipulator = $this->getManyManyInspector($list);

        // Find the foreign key name, ID and class to look up
        $joinClass        = $manipulator->getJoinClass();
        $fromRelationName = $manipulator->getForeignIDKey();
        $toRelationName   = $manipulator->getLocalKey();

        // Create a list of the MMTL relations
        $sortlist = DataList::create($joinClass)->filter([
            $toRelationName => $list->column('ID'),
            // first() is safe as there are earlier checks to ensure our list to sort is valid
            $fromRelationName => $list->first()->getJoin()->{$fromRelationName},
        ]);

        return $sortlist->map($toRelationName, $sortField)->toArray();
    }
}
