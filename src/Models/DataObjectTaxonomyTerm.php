<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class DataObjectTaxonomyTerm extends DataObject
{
    private static $table_name = 'AT_DataObject_TaxonomyTerm';

    private static $db = [
        'Sort' => 'Int',
    ];

    private static $has_one = [
        'OwnerObject' => DataObject::class, // Polymorphic has_one
        'JointObject' => TaxonomyTerm::class,
    ];

    private static $default_sort = '"Sort" ASC';

    private static $extensions = [
        Versioned::class,
    ];
}
