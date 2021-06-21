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
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        if ($this->hasExtension(Versioned::class)) {
            if (Versioned::get_stage() === Versioned::DRAFT) {
                if ($this->canUnpublish()) {
                    $this->doUnpublish();
                }
            }
        }
    }


    /**
     * Ensure the linking object is deleted from Draft when it's deleted from Live for a non-versioned owner object
     */
    public function onAfterDelete()
    {
        if ($this->OwnerObject() && $this->OwnerObject()->hasExtension(Versioned::class) === false) {
            if (Versioned::get_stage() === Versioned::LIVE) {
                Versioned::withVersionedMode(function () {
                    Versioned::set_stage(Versioned::DRAFT);
                    $this->delete();
                });
            }
        }
    }


    /**
     * Ensure the linking object is published to Live stage after writing a non-versioned owner object
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // explicit comparison to false as using ! may not be obvious enough in this case
        if ($this->OwnerObject() && $this->OwnerObject()->hasExtension(Versioned::class) === false) {
            if (Versioned::get_stage() === Versioned::DRAFT) {
                if ($this->canPublish()) {
                    $this->doPublish();
                }
            }
        }
    }
}
