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
     * Make sure the linking object is unpublished from Live stage before deleting it from Draft stage.
     */
    public function onAfterDelete()
    {
        if ($this->hasExtension(Versioned::class)) {
            if ($this->canUnpublish()) {
                $this->doUnpublish();
            }
        }
        parent::onAfterDelete();
    }
}
