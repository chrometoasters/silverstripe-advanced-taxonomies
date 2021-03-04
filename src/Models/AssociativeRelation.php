<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use SilverStripe\ORM\DataObject;

class AssociativeRelation extends DataObject
{
    private static $table_name = 'AT_AssociativeRelation';

    private static $singular_name = 'Associative relation';

    private static $plural_name = 'Associative relations';

    /**
     * @var string[]
     */
    private static $db = [
        'Sort'              => 'Int',
        'IsInverseRelation' => 'Boolean',
    ];

    /**
     * @var string[]
     */
    private static $has_one = [
        'Source'                  => TaxonomyTerm::class,
        'Destination'             => TaxonomyTerm::class,
        'AssociativeRelationType' => AssociativeRelationType::class,
    ];


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Remove Sort field from the EditForm
        $fields->removeByName('Sort');

        return $fields;
    }
}
