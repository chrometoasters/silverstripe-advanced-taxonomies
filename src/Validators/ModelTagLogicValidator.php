<?php

namespace Chrometoaster\AdvancedTaxonomies\Validators;

use Chrometoaster\AdvancedTaxonomies\ModelAdmins\TaxonomyModelAdmin;
use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationResult;

class ModelTagLogicValidator extends RequiredFields
{
    // A static flag to indicate if output message allows containing HTML, default as false
    private static $output_html_enabled = false;


    public function php($data)
    {
        // Need to validate the required fields attached to the Validator
        $valid        = parent::php($data);
        $errorMessage = '';

        $useHTML   = self::config()->get('output_html_enabled');
        $bOpen     = '<b>';
        $bClose    = '</b>';
        $lineBreak = '<br>';
        if (!$useHTML) {
            $bOpen     = $bClose     = '"';
            $lineBreak = ' ';
        }

        if (isset($data['Tags']) && !empty($data['Tags'])) {
            $tags = TaxonomyTerm::get()->filterAny('ID', $data['Tags']);

            // Validate SingleSelect logic, given by TaxonomyTerm's SingleSelect attribute
            // The $singleSelectOffended is either a boolean of 'true', indicating it is validated against SingleSelect
            // or holding offended Terms grouped by their TypeIDs
            $singleSelectOffended = static::singleSelectValidate($tags);

            if ($singleSelectOffended !== true) {
                $valid     = false;
                $termNames = [];
                foreach ($singleSelectOffended as $typeID => $terms) {
                    $termName = [];
                    foreach ($terms as $index => $term) {
                        $eitherOr   = $index === 0 ? 'either' : 'or';
                        $termName[] = $eitherOr . ' ' . $bOpen . $term->Name . $bClose;
                    }
                    $termNames[] = implode(', ', $termName);
                }
                $errorMessage .= 'Some tags have been added from a single select taxonomy. Only one of the '
                    . 'following tags can be used. Please keep ' . implode(' and ', $termNames) . ' and try '
                    . 'again.';
            }


            // Validate RequiredTypes logic, given by TaxonomyTerm's many_many relation to Other TaxonomyTypes
            // The $requiredTypesOffended is either a boolean of 'true', indicating it is validated against RequiredTypes
            // or holding two set of Terms as DataList:
            // one set is 'offending' terms, one set is 'requiring' types (root terms)
            $requiredTypesOffended = static::requiredTypesValidate($tags);

            if ($requiredTypesOffended !== true) {
                $valid       = false;
                $typesMissed = self::getConcatTitlesNiceByTerms(
                    $requiredTypesOffended['requiring'],
                    true,
                    'Root_Terms'
                );
                $termsMissedBy = self::getConcatTitlesNiceByTerms(
                    $requiredTypesOffended['offending'],
                    false,
                    'Root_RequiredTypes'
                );
                if ($errorMessage !== '') {
                    $errorMessage .= $lineBreak;
                }
                $errorMessage .= 'Please also add one or more tags from the '
                    . (($requiredTypesOffended['requiring']->count() === 1) ? '' : 'related ') . $typesMissed
                    . '. The required taxonomies settings of the ' . $termsMissedBy
                    . (($requiredTypesOffended['offending']->count() === 1) ? ' term' : ' terms')
                    . ', mean you now need to add at least one tag from related taxonomies, too.';
            }
        }


        /**
         * TODO: the 4th parameter passed should be ValidationResult::CAST_HTML, so the error message is rendered as
         * HTML, but currently it is not working. An issue already submitted to SilverStripe, the issue's tracking URL is
         * https://github.com/silverstripe/silverstripe-framework/issues/9155,
         * Change back to ValidationResult::CAST_HTML once the issue is solved.
         */
        if (!$valid) {
            $this->validationError(
                'Tags',
                $errorMessage,
                ValidationResult::TYPE_ERROR,
                ValidationResult::CAST_TEXT
            );
        }

        return $valid;
    }


    /**
     * @param SS_List $tags
     * @return array|bool either offending Terms grouped by their TypeIDs or 'true' indicating no offending terms found
     */
    public static function singleSelectValidate(SS_List $tags)
    {
        $offendedTerms = [];
        $valid         = true;

        // Validate SingleSelect logic, given by root TaxonomyTerm's attribute
        $candidateTypeIDs = array_unique($tags->column('TypeID'));
        if (!empty($candidateTypeIDs)) {
            $singleSelectedTypeIDs = TaxonomyTerm::get()->filter('ParentID', 0)
                ->filterAny('ID', $candidateTypeIDs)->filter('SingleSelect', true)->column('ID');

            if (!empty($singleSelectedTypeIDs)) {
                foreach ($singleSelectedTypeIDs as $typeID) {
                    $terms = $tags->filter('TypeID', $typeID);
                    if ($terms->count() > 1) {
                        $valid                  = false;
                        $offendedTerms[$typeID] = $terms;
                    }
                }
            }
        }

        if (!$valid) {
            return $offendedTerms;
        }

        // only value true;
        return $valid;
    }


    /**
     * @param SS_List $tags
     * @return array|bool
     *
     * The function will return either two sets of DataList, one set is requiring Types, i.e. root Terms
     * another set is Terms that are offending the logic
     * or a boolean of 'true' indicating no offending terms are found, hence it is valid
     */
    public static function requiredTypesValidate(SS_List $tags)
    {
        $requiringTypeIDs = [];
        $offendingTagIDs  = [];
        $valid            = true;

        foreach ($tags as $tag) {
            if ($types = $tag->RequiredTypesOverall()) {
                $requiringTypeIDsByLocalTag = $types->column('ID');
                if (!empty($requiringTypeIDsByLocalTag)
                    && !empty(array_diff($requiringTypeIDsByLocalTag, $tags->column('TypeID')))) {
                    $valid             = false;
                    $offendingTagIDs[] = $tag->ID;
                    $requiringTypeIDs  = array_merge($requiringTypeIDsByLocalTag, $requiringTypeIDs);
                }
            }
        }
        $requiringTypeIDs = array_diff(array_unique($requiringTypeIDs), $tags->column('TypeID'));

        if (!$valid) {
            return [
                'requiring' => TaxonomyTerm::get()->filterAny('ID', $requiringTypeIDs),
                'offending' => $tags->filterAny('ID', array_unique($offendingTagIDs)),
            ];
        }

        return $valid;
    }


    /**
     * @param SS_List $terms
     * @param bool $havingSuffix
     * @param string $landingTabName
     * @return string
     */
    public static function getConcatTitlesNiceByTerms(
        SS_List $terms,
        $havingSuffix = true,
        string $landingTabName = 'Root_Main'
    ) {
        $termNames = '';

        $useHTML = self::config()->get('output_html_enabled');
        $bOpen   = '<b>';
        $bClose  = '</b>';

        // If not using HTML as the output, we make each term in a double quotation so that users could be noticed that
        // a TaxonomyTerm "not a single word" is one term
        if (!$useHTML) {
            $bOpen = $bClose = '"';
        }

        if ($terms->count() === 1) {
            $term      = $terms->first();
            $termNames = $bOpen . self::getTermEditLink($term, $landingTabName) . $bClose
                . ($havingSuffix ? ' taxonomy' : '');
        } elseif ($terms->count() > 1) {
            foreach ($terms as $index => $term) {
                $termEditLink = self::getTermEditLink($term, $landingTabName);
                if ($index === 0) {
                    $termNames .= $bOpen . $termEditLink . $bClose;
                } elseif ($index === ($terms->count() - 1)) {
                    $termNames .= ' and ' . $bOpen . $termEditLink . $bClose;
                } else {
                    $termNames .= ', ' . $bOpen . $termEditLink . $bClose;
                }
            }
            $termNames .= ($havingSuffix ? ' taxonomies' : '');
        }

        return $termNames;
    }


    /**
     * The function is to provide a unique CMS editing link in
     * {@link Chrometoaster\AdvancedTaxonomies\ModelAdmins\TaxonomyModelAdmin}, given a TaxonomyTerm object,
     * with a landingTabName that attached to the link as a hash
     *
     * @param TaxonomyTerm $tag
     * @param string $landingTabName
     * @return mixed|string
     */
    private static function getTermEditLink(TaxonomyTerm $tag, string $landingTabName)
    {
        if (!self::config()->get('output_html_enabled')) {
            return $tag->Name;
        }

        $admin             = singleton(TaxonomyModelAdmin::class);
        $admin->modelClass = TaxonomyTerm::class;
        $admin->init();
        $gridFieldName = str_replace('\\', '-', TaxonomyTerm::class);
        $gridField     = $admin->getEditForm()->Fields()->dataFieldByName($gridFieldName);
        $linkedURL     = $gridField->Link();

        $subURL = [];
        $node   = $tag;
        while ($node->ParentID) {
            $subURL[] = 'ItemEditForm/field/Children/item/' . $node->ID . '/';
            $node     = $node->Parent();
        }
        $subURL[] = '/item/' . $node->ID . '/';

        $termEditURL = $linkedURL . implode('', array_reverse($subURL)) . '/edit?#' . $landingTabName;

        return sprintf('<a href="%s" target="_blank" class="at-link-external">%s</a>', $termEditURL, $tag->Name);
    }
}
