<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\View\Requirements;

class LeftAndMainTaxonomyExtension extends LeftAndMainExtension
{
    public function init()
    {
        Requirements::css('chrometoaster/silverstripe-advanced-taxonomies:client/style.css');
    }
}
