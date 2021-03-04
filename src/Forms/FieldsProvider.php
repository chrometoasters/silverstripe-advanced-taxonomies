<?php

namespace Chrometoaster\AdvancedTaxonomies\Forms;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\ORM\DataList;
use SilverStripe\Versioned\GridFieldArchiveAction;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class FieldsProvider
 *
 * Providing reusable fields and field configurations.
 */
class FieldsProvider
{
    /**
     * Provide a reusable gridfield config for tagging a dataobject with some terms
     *
     * @param DataList|null $searchList
     * @param array $extraDisplayFields
     * @param string $sortField
     * @return GridFieldConfig
     */
    public static function getTaggingGridFieldConfig(DataList $searchList = null, array $extraDisplayFields = [], string $sortField = 'Sort'): GridFieldConfig
    {
        $gfc = GridFieldConfig_RelationEditor::create();

        // Remove config components from the Tags gridfield to disallow adding/deleting/archiving taxonomy terms from here
        $gfc->removeComponentsByType([
            GridFieldAddNewButton::class,
            GridFieldEditButton::class,
            GridFieldArchiveAction::class,
            GridFieldFilterHeader::class,
            GridFieldAddExistingAutocompleter::class,
        ]);

        $gfc->getComponentByType(GridFieldDataColumns::class)->setDisplayFields(array_merge(
            [
                'getNameAsTagWithExtraInfo' => 'Name',
                'getDescription15Words'     => 'Description',
            ],
            $extraDisplayFields
        ));

        $gfc->addComponents([
            GridFieldOrderableRows::create($sortField),
            $addExisting = new GridFieldAddTagsAutocompleter('buttons-before-left'),
            new GridFieldInfoLink('buttons-before-left', '/at-taxonomy-overview', "Open 'All taxonomies' overview"),
        ]);

        $autoResultFormat = '&nbsp;{$getTermHierarchy}&nbsp;';
        $addExisting->setResultsFormat($autoResultFormat);
        $addExisting->setPlaceholderText('Add tags by name');
        $addExisting->setSearchList($searchList ?? TaxonomyTerm::get());

        return $gfc;
    }
}
