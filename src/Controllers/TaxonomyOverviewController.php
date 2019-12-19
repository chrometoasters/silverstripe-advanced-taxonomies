<?php

namespace Chrometoaster\AdvancedTaxonomies\Controllers;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\View\ArrayData;

/**
 * Class TaxonomyDirectoryController
 *
 * Controller for returning a list of pages tagged with a specific Taxonomy Term
 */
class TaxonomyOverviewController extends Controller
{
    private static $url_handlers = [
        '$ParentID' => 'index',
    ];

    private static $allowed_actions = [
        'index',
    ];


    /**
     * Render a hierarchy
     *
     * @param HTTPRequest $request
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function index(HTTPRequest $request)
    {
        $parentID = (int) $request->param('ParentID'); // empty param is the same as 0 for the sake of this report

        $terms = TaxonomyTerm::get()->filter(['ParentID' => $parentID]);

        $parentTerm = null;
        if ($parentID) {
            $parentTerm = TaxonomyTerm::get()->byID($parentID);
        }

        return $this->customise(ArrayData::create([
            'Terms'      => $terms,
            'ParentTerm' => ($parentTerm && $parentTerm->exists()) ? $parentTerm : false,
        ]))->renderWith(self::class);
    }
}
