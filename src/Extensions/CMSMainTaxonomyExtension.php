<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

/**
 * Class CMSMainTaxonomyExtension
 *
 * This class is an extension supposed to be applied to CMSMain, it add a Tags column in listview of pages. The values
 * populated to the Tags column will be the taxonomy terms tagged to that page, with more metadata / information, such
 * as the term's Title, TitlePlural, Description, its Type with the type's logic attributes: `SingleSelect` and/or
 * `RequiredTypes`, etc. As the information will took a much bigger space to display in a GridField cell then normal
 * text, we turned them into tooltip-styled HTML, so here we need to set the column casting as: `HTMLFragment->RAW`
 */
class CMSMainTaxonomyExtension extends LeftAndMainExtension
{
    public function updateListView($listview)
    {
        $pagesGrid = $listview->Fields()->dataFieldByName('Page');
        $columns   = $pagesGrid->getConfig()->getComponentByType(GridFieldDataColumns::class);

        // Add a Tags column
        $fields = array_merge($columns->getDisplayFields($pagesGrid), [
            'getTagNames' => 'Tags',
        ]);
        $columns->setDisplayFields($fields);

        $casting = array_merge($columns->getFieldCasting(), [
            'getTagNames' => 'HTMLFragment->RAW',
        ]);
        $columns->setFieldCasting($casting);
    }
}
