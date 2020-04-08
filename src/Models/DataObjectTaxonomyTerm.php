<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class DataObjectTaxonomyTerm
 *
 * Joining object between a data model and a taxonomy term, allowing polymorphic many_many relations.
 */
class DataObjectTaxonomyTerm extends DataObject
{
    private static $table_name = 'AT_DataObject_TaxonomyTerm';

    private static $db = [
        'Sort' => 'Int',
    ];

    private static $has_one = [
        'OwnerObject' => DataObject::class, // polymorphic has_one to the tagged object
        'JointObject' => TaxonomyTerm::class, // the term being assigned to a relation
    ];

    private static $owns = [
        'JointObject',
    ];

    private static $default_sort = '"Sort" ASC';

    private static $extensions = [
        Versioned::class,
    ];


    /**
     * Though this object is used to link {@see DataObject} and {@see TaxonomyTerm} in a many-many relation, the
     * object itself is manipulated as a data object and is {@see Versioned} by default, it could be published by its
     * owner object through the static $owns {@see DataObjectTaxonomiesDataExtension::$owns} defined. On an operation
     * of deleting this object trigged by 'unlink' on its owner's interface, this object is removed only from its
     * Staging stage, whereas leaves its published record as an orphaned record. The function is to ensure the object's
     * published record is also removed to maintains data integrity.
     */
    public function onAfterDelete()
    {
        if ($this->hasExtension(Versioned::class)) {
            if ($this->isPublished()) {
                $this->doUnpublish();
            }
        }
        parent::onAfterDelete();
    }
}
