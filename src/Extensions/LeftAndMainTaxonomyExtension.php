<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

class LeftAndMainTaxonomyExtension extends Extension
{
    public function init()
    {
        Requirements::css('chrometoaster/silverstripe-advanced-taxonomies:client/style.css');
    }
}
