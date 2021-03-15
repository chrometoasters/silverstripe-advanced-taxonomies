<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class DefaultTermsDataExtension
 *
 * Add the capability to specify default taxonomy terms when an object is created.
 *
 * Example config for the default Tags field (there could be more custom tags fields for each object):
 *
 *     private static $init_default_terms = [
 *         'Tags' => [
 *             'information-type/news',
 *             'publication-type/case-study',
 *         ],
 *     ];
 *
 * The terms are added by their full-path-like url slugs.
 */
class DefaultTermsDataExtension extends DataExtension
{
    private static $db = [
        'DefaultTermsInitialised' => 'Boolean(0)',
    ];


    /**
     * Hook into onBeforeWrite
     *
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->linkDefaultTaxonomyTerms();
    }


    /**
     * Link taxonomy terms if they can be found
     *
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function linkDefaultTaxonomyTerms()
    {
        /** @var DataObject|Versioned|self $owner */
        $owner = $this->getOwner();

        if (!$owner->DefaultTermsInitialised) {
            $defaultTerms = Config::inst()->get($owner->getClassName(), 'init_default_terms', Config::UNINHERITED);

            if (is_array($defaultTerms)) {
                foreach ($defaultTerms as $termsRelation => $termSlugs) {
                    if (count($termSlugs)) {
                        // has_one — uses the FIRST term (in case there are multiple)
                        if (DataObject::getSchema()->hasOneComponent($owner, $termsRelation)) {
                            $term = TaxonomyTerm::getBySlug($termSlugs[0]);
                            if ($term) {
                                $owner->{$termsRelation . 'ID'} = $term->ID;
                            }
                            // has_many or many_many
                        } elseif (DataObject::getSchema()->hasManyComponent($owner, $termsRelation) || DataObject::getSchema()->manyManyComponent($owner, $termsRelation)) {
                            foreach ($termSlugs as $termSlug) {
                                $term = TaxonomyTerm::getBySlug($termSlug);
                                if ($term) {
                                    $owner->{$termsRelation}()->add($term);
                                }
                            }
                        }
                    }
                }

                $owner->setField('DefaultTermsInitialised', true);
            }
        }
    }
}
