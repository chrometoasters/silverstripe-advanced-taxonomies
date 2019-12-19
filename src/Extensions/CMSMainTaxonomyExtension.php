<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

/**
 * Class CMSMainTaxonomyExtension
 *
 * Applied to CMSMain, this extension adds a Tags column to the list view of Pages section.
 * The Tags column lists all taxonomy terms assigned to each page in the list, with extra information about the term
 * and its type (via a tooltip mechanism).
 */
class CMSMainTaxonomyExtension extends LeftAndMainExtension
{
    public function updateListView($listview)
    {
        $pagesGrid = $listview->Fields()->dataFieldByName('Page');
        $columns   = $pagesGrid->getConfig()->getComponentByType(GridFieldDataColumns::class);

        // Add a Tags column
        $fields = array_merge($columns->getDisplayFields($pagesGrid), [
            'getTagNamesWithExtraInfo' => 'Tags',
        ]);
        $columns->setDisplayFields($fields);
    }
}
