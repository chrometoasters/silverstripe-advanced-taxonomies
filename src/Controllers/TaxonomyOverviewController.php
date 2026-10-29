<?php

namespace Chrometoaster\AdvancedTaxonomies\Controllers;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Model\ArrayData;

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
     */
    public function index(HTTPRequest $request)
    {
        $parentID = (int) $request->param('ParentID'); // empty param is the same as 0 for the sake of this report

        $terms = TaxonomyTerm::get()->setUseCache(true)->filter(['ParentID' => $parentID]);

        $parentTerm = null;
        if ($parentID) {
            $parentTerm = TaxonomyTerm::get()->setUseCache(true)->byID($parentID);
        }

        return $this->customise(ArrayData::create([
            'Terms'      => $terms,
            'ParentTerm' => ($parentTerm && $parentTerm->exists()) ? $parentTerm : false,
        ]))->renderWith(self::class);
    }
}
