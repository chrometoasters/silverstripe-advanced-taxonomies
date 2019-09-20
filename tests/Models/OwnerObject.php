<?php

namespace Chrometoaster\AdvancedTaxonomies\Tests\Models;

use Chrometoaster\AdvancedTaxonomies\Extensions\DataObjectTaxonomiesDataExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class OwnerObject extends DataObject implements TestOnly
{
    private static $table_name = 'AT_UnitTests_OwnerObject';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        Versioned::class,
        DataObjectTaxonomiesDataExtension::class,
    ];
}
