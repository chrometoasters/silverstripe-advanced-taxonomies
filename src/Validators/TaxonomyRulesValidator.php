<?php

namespace Chrometoaster\AdvancedTaxonomies\Validators;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationResult;

class TaxonomyRulesValidator extends RequiredFields
{
    /**
     * Instance variable to indicate if output message allows containing HTML
     *
     * Defaults to true, can be disabled per instance when instantiating the validator.
     *
     * @var bool
     */
    private $enableHTMLOutput = true;


    /**
     * Enable HTML output setter
     *
     * @param $val
     * @return $this
     */
    public function setHTMLOutput($val)
    {
        $this->enableHTMLOutput = $val;

        return $this;
    }


    /**
     * Main validation method
     *
     * @param array $data
     * @return bool
     */
    public function php($data)
    {
        // validate the required fields attached to the Validator
        $valid = parent::php($data);

        // list of validations errors for taxonomy terms
        $errors = [];

        // use line break as a line separator for html outputs
        $lineSeparator = $this->enableHTMLOutput ? '<br>' : ' ';

        // validate taxonomies' rules if there are any terms to be assigned
        if (isset($data['Tags']) && !empty($data['Tags'])) {
            $tags = TaxonomyTerm::get()->filterAny('ID', $data['Tags']);

            // validate SingleSelect logic — see method's doc block for the possible output formats
            $singleSelectValidationError = $this->validateSingleSelectTypes($tags, ...$this->getValidationMessagesDecorators());
            if ($singleSelectValidationError) {
                $errors[] = $singleSelectValidationError;
            }

            // validate RequiredTypes logic — see method's doc block for the possible output formats
            $requiredTypesValidationError = $this->validateRequiredTypes($tags, ...$this->getValidationMessagesDecorators());
            if ($requiredTypesValidationError) {
                $errors[] = $requiredTypesValidationError;
            }
        }

        /**
         * TODO: the 4th parameter passed should be ValidationResult::CAST_HTML, so the error message is rendered as
         * HTML, but currently it is not working. An issue already submitted to SilverStripe, the issue's tracking URL is
         * https://github.com/silverstripe/silverstripe-framework/issues/9155,
         * Change back to ValidationResult::CAST_HTML once the issue is solved.
         */
        if (count($errors)) {
            $this->validationError(
                'Tags',
                implode($lineSeparator, $errors),
                ValidationResult::TYPE_ERROR,
                ValidationResult::CAST_TEXT
            );
        }

        return $valid;
    }


    /**
     * Validate a list of taxonomy terms against the SingleSelect rule that can be set per taxonomy type
     *
     * - The method returns true if there are no terms violating the rule.
     * - The method returns an array of 'single select taxonomy types' with a list of terms (from the validated list)
     *   for each type.
     *
     * @param SS_List $tags
     * @return array|bool
     * @internal
     */
    private function _validateSingleSelectTypes(SS_List $tags)
    {
        // output array in the format of 'single select taxonomy type' => 'tags of this type from the validated list'
        $singleSelectTypesWithMultipleTerms = [];

        $singleSelectTypeIDs = TaxonomyTerm::getSingleSelectOnlyTypes($tags)->column('ID');

        if (count($singleSelectTypeIDs)) {
            foreach ($singleSelectTypeIDs as $typeID) {
                $terms = $tags->filter('TypeID', $typeID);
                if ($terms->count() > 1) {
                    // only add if there's more than one term, only then it offends the single selection logic
                    $singleSelectTypesWithMultipleTerms[$typeID] = $terms;
                }
            }
        }

        if (count($singleSelectTypesWithMultipleTerms)) {
            return $singleSelectTypesWithMultipleTerms;
        }

        return true;
    }


    /**
     * Validate a list of taxonomy terms against the SingleSelect rule that can be set per taxonomy type
     *
     * Return error message describing the terms involved, or an empty string where there are no errors.
     *
     * @param SS_List $tags
     * @return string
     */
    public function validateSingleSelectTypes(SS_List $tags, callable $termDecorator = null): string
    {
        // default decorator if none is provided
        $quotedNameDecorator = function (TaxonomyTerm $term) {
            return sprintf('"%s"', $term->Name);
        };

        $termDecorator = $termDecorator ?: $quotedNameDecorator;

        // see the methods docblock for its output format
        $singleSelectValidation = $this->_validateSingleSelectTypes($tags);
        if ($singleSelectValidation !== true) {
            $typeTermNames = [];
            foreach ($singleSelectValidation as $typeID => $typeTerms) {
                $typeTermNames[] = ' either ' . implode(', or ', array_map($termDecorator, $typeTerms->toArray()));
            }

            $msg[] = 'Some tags have been added from a single select taxonomy. Only one of the following tags can be used.';
            $msg[] = 'Please keep ' . implode(', and ', $typeTermNames) . ', and try again.';

            return implode(' ', $msg);
        }

        return '';
    }


    /**
     * Validate a list of taxonomy terms against the RequiredTypes rule that can be set per taxonomy type and/or per term
     *
     * - The method returns true if there are no terms violating the rule.
     * - The method returns an array of two data lists — 'further required types' and 'terms with required types not met'.
     *
     * @param SS_List $tags
     * @return array|bool
     * @internal
     */
    private function _validateRequiredTypes(SS_List $tags)
    {
        $requiredTypeIDs               = [];
        $termsWithRequiredTypesMissing = [];

        // get IDs of all required types for each tag in the list
        // record each tag that requires types that were not satisfied
        foreach ($tags as $tag) {
            $tagRequiredTypeIDs = $tag->getAllRequiredTypes()->column('ID');
            if (count($tagRequiredTypeIDs) && !empty(array_diff($tagRequiredTypeIDs, $tags->column('TypeID')))) {
                $termsWithRequiredTypesMissing[] = $tag->ID;
                $requiredTypeIDs                 = array_merge($tagRequiredTypeIDs, $requiredTypeIDs);
            }
        }

        // get a list of required type IDs that we don't have in the list yet
        $requiredTypeIDs = array_diff(array_unique($requiredTypeIDs), $tags->column('TypeID'));

        if (count($requiredTypeIDs) || count($termsWithRequiredTypesMissing)) {
            return [
                'furtherRequiredTypes'         => TaxonomyTerm::get()->filterAny('ID', $requiredTypeIDs),
                'termsWithRequiredTypesNotMet' => $tags->filterAny('ID', array_unique($termsWithRequiredTypesMissing)),
            ];
        }

        return true;
    }


    /**
     * Validate a list of taxonomy terms against the RequiredTypes rule that can be set per taxonomy type and/or per term
     *
     * Return error message describing the terms and types involved, or an empty string where there are no errors.
     * Optional callbacks can be provided to decorate the further required types and the terms with unmet required type dependencies.
     *
     * @param SS_List $tags
     * @param callable|null $typesDecorator
     * @param callable|null $termsDecorator
     * @return string
     */
    public function validateRequiredTypes(SS_List $tags, callable $typesDecorator = null, callable $termsDecorator = null): string
    {
        // default decorators if none is provided
        $quotedNameDecorator = function (TaxonomyTerm $term) {
            return sprintf('"%s"', $term->Name);
        };

        $typesDecorator = $typesDecorator ?: $quotedNameDecorator;
        $termsDecorator = $termsDecorator ?: $quotedNameDecorator;

        $requiredTypesValidation = $this->_validateRequiredTypes($tags); // see method's docblock for its output format
        if ($requiredTypesValidation !== true) {

            /** @var DataList $requiredTypes */
            $requiredTypes = $requiredTypesValidation['furtherRequiredTypes'];

            $msg[] = sprintf(
                'Please also add one or more tags from the %s%s %s.',
                (($requiredTypes->count() === 1) ? '' : 'related '),
                self::joinStrings(array_map($typesDecorator, $requiredTypes->toArray())),
                (($requiredTypes->count() === 1) ? 'taxonomy' : 'taxonomies')
            );

            /** @var DataList $termsWithUnmetTypes */
            $termsWithUnmetTypes = $requiredTypesValidation['termsWithRequiredTypesNotMet'];

            $msg[] = sprintf(
                'The required taxonomies settings of the %s %s mean you now need to add at least one tag from related taxonomies, too.',
                self::joinStrings(array_map($termsDecorator, $termsWithUnmetTypes->toArray())),
                (($termsWithUnmetTypes->count() === 1) ? ' term' : ' terms')
            );

            return implode(' ', $msg);
        }

        return '';
    }


    /**
     * Get a list of specific term decorators for formatting validation messages
     *
     * The output should be used via a spread operator, so the order is important.
     *
     * The interface is public to allow the use of the validation methods from getCmsFields and updateCmsFields
     * dynamic validations where this is used mainly to guide CMS users, though shouldn't be used without
     * understanding the context.
     *
     * @return array
     */
    public function getValidationMessagesDecorators(): array
    {
        // create term decorators for HTML-enabled validation output
        if ($this->enableHTMLOutput) {
            $termsDecorator = function (TaxonomyTerm $term) {
                return sprintf('<b>%s</b>', $term->getModelAdminEditLink());
            };
            $termsDecoratorRequired = function (TaxonomyTerm $term) {
                return sprintf('<b>%s</b>', $term->getModelAdminEditLink('Root_RequiredTypes'));
            };
        } else {
            $termsDecorator = $termsDecoratorRequired = null;
        }

        return [$termsDecorator, $termsDecoratorRequired];
    }


    /**
     * Helper method allowing joining of strings with a custom conjunction after the first and before the last element
     *
     * Credit for inspiration to https://stackoverflow.com/a/25057951 — thanks!
     *
     * @param array $list
     * @param string $glue
     * @param string $lastGlue
     * @return mixed|string
     */
    private static function joinStrings(array $list, string $firstGlue = ', ', string $glue = ', ', string $lastGlue = ' and ')
    {
        if (count($list)) {
            $first = array_shift($list);

            if ($first && count($list)) {
                $last = array_pop($list);
                if (count($list)) {
                    return $first . ($firstGlue ?: $glue) . implode($glue, $list) . $lastGlue . $last;
                }

                return $first . $lastGlue . $last;
            }

            return $first;
        }

        return '';
    }
}
