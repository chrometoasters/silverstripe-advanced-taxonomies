<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

/**
 * Class CMSMainTaxonomyExtension
 *
 * Applied to CMSMain, this extension adds a Tags column to the list view of Pages section.
 * The Tags column lists all taxonomy terms assigned to each page in the list, with extra information about the term
 * and its type (via a tooltip mechanism).
 */
class CMSMainTaxonomyExtension extends Extension
{
    /**
     * The names CMSMain::ListViewForm() has given its grid field: 'Record' since CMS 6, 'Page' on CMS 4 and 5.
     *
     * @var string[]
     */
    private const LIST_VIEW_GRID_NAMES = ['Record', 'Page'];


    public function updateListView(Form $listview): void
    {
        $pagesGrid = $this->findListViewGrid($listview);
        if (!$pagesGrid) {
            return;
        }

        $columns = $pagesGrid->getConfig()->getComponentByType(GridFieldDataColumns::class);
        if (!$columns) {
            return;
        }

        // Add a Tags column
        $fields = array_merge($columns->getDisplayFields($pagesGrid), [
            'getTagNamesWithExtraInfo' => 'Tags',
        ]);
        $columns->setDisplayFields($fields);
    }


    /**
     * The list view's grid field, whatever CMSMain has named it
     *
     * CMS 6 renamed it from 'Page' to 'Record'; a lookup by the old name alone returned null there and made the
     * whole list view fail. The known names are tried first, then any grid field in the form.
     */
    private function findListViewGrid(Form $listview): ?GridField
    {
        $fields = $listview->Fields();

        foreach (self::LIST_VIEW_GRID_NAMES as $name) {
            $field = $fields->dataFieldByName($name);
            if ($field instanceof GridField) {
                return $field;
            }
        }

        foreach ($fields->dataFields() as $field) {
            if ($field instanceof GridField) {
                return $field;
            }
        }

        return null;
    }
}
