<?php

namespace Chrometoaster\AdvancedTaxonomies\Generators;

use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Class URLSegmentGenerator
 */
class URLSegmentGenerator
{
    /**
     * @param $rawCandidate
     * @param $modelClass
     * @param $modelID
     * @return string
     */
    public static function generate($rawCandidate, $modelClass, $modelID)
    {
        $filter       = URLSegmentFilter::create();
        $urlCandidate = $filter->filter($rawCandidate);

        /**
         * Fallback to generic TaxonomyTerm's ClassName-ID combination if the candidate url after
         * filtering is empty
         */
        if (!$urlCandidate || $urlCandidate == '-' || $urlCandidate == '-1') {
            $urlCandidate = "$modelClass-$modelID";
            //make sure it is sanitised again
            $urlCandidate = $filter->filter($urlCandidate);
        }

        // Ensure that this object has a non-conflicting URL value.
        $count = 2;
        while (!static::validate($urlCandidate, $modelClass, $modelID)) {
            $urlCandidate = preg_replace('/-[0-9]+$/', null, $urlCandidate) . '-' . $count;
            $count++;
        }

        return $urlCandidate;
    }


    /**
     * @param $url
     * @param $modelClass
     * @param $modelID
     * @return bool
     */
    private static function validate($url, $modelClass, $modelID)
    {
        // Check for clashing model by url, id
        $source = $modelClass::get()->filter('URLSegment', $url);
        if ($modelID) {
            $source = $source->exclude('ID', $modelID);
        }

        return !$source->exists();
    }
}
