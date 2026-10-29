<?php

namespace Chrometoaster\AdvancedTaxonomies\Forms;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\DataList;

/**
 * Class FieldsProvider
 *
 * Providing reusable fields and field configurations.
 */
class FieldsProvider
{
    /**
     * Provide a reusable grid field config for tagging dataobjects with terms
     *
     * @param DataList|null $searchList
     * @param array $extraDisplayFields
     * @param string $sortField
     * @return GridFieldConfig
     */
    public static function getTaggingGridFieldConfig(
        ?DataList $searchList = null,
        array $extraDisplayFields = [],
        string $sortField = 'Sort'
    ): GridFieldConfig {
        $gfc = GridFieldConfig_TagsRelationEditor::create($sortField);

        $gfc->getComponentByType(GridFieldDataColumns::class)->setDisplayFields(array_merge(
            [
                'getNameAsTagWithExtraInfo' => 'Name',
                'getDescription15Words'     => 'Description',
            ],
            $extraDisplayFields
        ));

        $addExisting = $gfc->getComponentByType(GridFieldAddExistingAutocompleter::class);
        $addExisting->setResultsFormat('&nbsp;{$getTermHierarchy}&nbsp;');
        $addExisting->setPlaceholderText('Add tags by name');
        $addExisting->setSearchList($searchList ?? TaxonomyTerm::get());

        return $gfc;
    }
}
