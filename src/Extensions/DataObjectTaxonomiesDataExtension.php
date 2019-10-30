<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldAddTagsAutocompleter;
use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldOrderableRows;
use Chrometoaster\AdvancedTaxonomies\Models\DataObjectTaxonomyTerm;
use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use Chrometoaster\AdvancedTaxonomies\Validators\ModelTagLogicValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\Versioned\GridFieldArchiveAction;

/**
 * Class DataObjectTaxonomiesDataExtension
 *
 * This extension will be applied to DataObject covering SiteTree, BaseElement, File and any other models that extended
 * from DataObject, it add a many-many relation (labelled 'Tags') between the model and TaxonomyTerm. It provides a
 * generic CMS user interface for adding tags to the models that is applied by this extension.
 */
class DataObjectTaxonomiesDataExtension extends DataExtension
{
    private static $many_many = [
        'Tags' => [
            'through' => DataObjectTaxonomyTerm::class,
            'from'    => 'OwnerObject',
            'to'      => 'JointObject',
        ],
    ];

    // Automatically publish tags (TaxonomyTerm) whenever the owner (DataObject) get published.
    private static $owns = [
        'Tags',
    ];

    // Perform deletions on tags (TaxonomyTerm) once the owner (DataObject) get deleted
    private static $cascade_deletes = [
        'Tags',
    ];

    private static $cascade_duplicates = [
        'Tags'
    ];

    private static $summary_fields = [
        'TagNames' => 'Tags',
    ];

    private static $casting = [
        'TagNames'    => 'HTMLFragment',
        'getTagNames' => 'HTMLFragment',
    ];


    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // For a new DataObject that has not saved, no Tags related fields should be added.
        if (!$this->owner->exists()) {
            return;
        }
        // Tags GridField is tweaked so as not to allow adding/deleting/archiving TaxonomyTerm from here
        $components = GridFieldConfig_RelationEditor::create();
        $components->removeComponentsByType(GridFieldAddNewButton::class);
        $components->removeComponentsByType(GridFieldEditButton::class);
        $components->removeComponentsByType(GridFieldArchiveAction::class);
        $components->removeComponentsByType(GridFieldFilterHeader::class);


        // Shift the GridFieldAddExistingAutocompleter component to left
        $components->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
        $components->addComponent(
            $addExisting = new GridFieldAddTagsAutocompleter('buttons-before-left')
        );

        $components->getComponentByType(GridFieldDataColumns::class)->setDisplayFields(
            [
                'TagName'                 => 'Name',
                'DescriptionLimit15Words' => 'Description',
                'RequiredTypeNames'       => 'Requires',
            ]
        );

        $components->addComponent(GridFieldOrderableRows::create('Sort'));
        $components->getComponentByType(GridFieldDataColumns::class)->setFieldCasting(
            [
                'TagName' => 'HTMLFragment->RAW',
            ]
        );

        $autoResultFormat = '&nbsp;{$getHierarchyDisplay}&nbsp;';
        $addExisting->setResultsFormat($autoResultFormat);
        $addExisting->setPlaceholderText('Add tags by name');

        $searchList          = DataList::create(TaxonomyTerm::class);
        $singleSelectTypeIDs = $this->getTypeIDsWithSingleSelectOn();
        if (!empty($singleSelectTypeIDs)) {
            $searchList = $searchList->exclude('TypeID', $singleSelectTypeIDs);
        }

        // TODO: find-out why the following sorting doesn't effect in the real time.
        //        $searchList = $searchList->sort(['TypeID'=>'ASC', 'Name' => 'ASC']);
        //        TaxonomyTerm::config()->update('default_sort', ['TypeID'=>'ASC', 'Name' => 'ASC']);

        $addExisting->setSearchList($searchList);

        $fields->findOrMakeTab('Root.Tags', _t(self::class . '.TagsTabTitle', 'Tags'));
        $fields->addFieldToTab(
            'Root.Tags',
            $gridField = GridField::create(
                'Tags',
                _t(self::class . '.ManyManyTags', 'Tags'),
                $this->owner->Tags(),
                $components
            )
        );

        // Give warning message if RequireTypes' terms are not tagged
        $requiredTypesOffended = ModelTagLogicValidator::requiredTypesValidate($this->owner->Tags());

        // The $requiredTypesOffended is either a boolean of 'true', indicating it is validated against RequiredTypes
        // or holding two set of Terms as DataList:
        // one set is 'offending' terms, one set is 'requiring' types (root terms)
        if ($requiredTypesOffended !== true) {
            $origHTML = ModelTagLogicValidator::config()->get('output_html_enabled');
            ModelTagLogicValidator::config()->update('output_html_enabled', true);
            $typesMissed = ModelTagLogicValidator::getConcatTitlesNiceByTerms(
                $requiredTypesOffended['requiring'],
                true,
                'Root_Terms'
            );
            $termsMissedBy = ModelTagLogicValidator::getConcatTitlesNiceByTerms(
                $requiredTypesOffended['offending'],
                false,
                'Root_RequiredTypes'
            );
            ModelTagLogicValidator::config()->update('output_html_enabled', $origHTML);

            $errorMessage = 'Please also add one or more tags from the '
                . (($requiredTypesOffended['requiring']->count() === 1) ? '' : 'related ') . $typesMissed
                . '. The required taxonomies settings of the ' . $termsMissedBy
                . (($requiredTypesOffended['offending']->count() === 1) ? ' term' : ' terms')
                . ', mean you now need to add at least one tag from related taxonomies, too.';
            $fields->addFieldToTab(
                'Root.Tags',
                LiteralField::create('MissingTagsWarning', '<p class="bad message">' . $errorMessage . '</p>'),
                'Tags'
            );
        }

        // Re-order the tab "Tags" right after the tab "Main"
        // Depending on which sub-classes this extension is applied to, and which CMS interface is used for the owner
        // class, the above findOrMakeTab('Root.Tags') calls might act as 'find' (e.g ModelAdmin), or as 'make' (e.g
        // CMSMain), so the order of tabs in 'Root' TabSet may varied a lot, we here unify it to be the second Tab right
        // after 'Main' tab.
        $tagsTab = $fields->fieldByName('Root.Tags');
        if ($tagsTab) {
            $fields->removeFieldFromTab('Root', 'Tags');
            $fields->fieldByName('Root')->insertAfter('Main', $tagsTab);
        }
    }


    /**
     * @param mixed $fields
     * @return array
     */
    public function updateSummaryFields(&$fields)
    {
        // Reorder the summary fields to make Tags column to be the last column
        $tagNamesSummary = null;
        foreach ($fields as $key => $summary) {
            if ($key == 'TagNames') {
                $tagNamesSummary = $fields[$key];
                unset($fields[$key]);
            }
        }

        if ($tagNamesSummary) {
            $fields['TagNames'] = $tagNamesSummary;
        }
    }


    /**
     * @return array
     */
    public function getTypeIDsWithSingleSelectOn()
    {
        $candidateTypeIDs = array_unique($this->owner->Tags()->column('TypeID'));
        if (!empty($candidateTypeIDs)) {
            return $singleSelectedTypeIDs = TaxonomyTerm::get()->filter('ParentID', 0)
                ->filterAny('ID', $candidateTypeIDs)->filter('SingleSelect', true)->column('ID');
        }
    }


    /**
     * The function is to provide a model's associated tags presented in a well formatted way:
     * 1. The tags names are being formatted like a 'tag' which is wrapped by special HTML tags with some special
     *    classes
     * 2. The tags other information is also added to this presentation but as tooltips, since this presentation is
     *    to be shown as GridField columns' value, i.e. inside a table cell, the space holding this presentation is
     *    limited.
     *
     * @return string
     */
    public function getTagNames()
    {
        $names = [];

        $lineBreaks = '<br><br>';
        $bTagOpen   = '<b>';
        $bTagClose  = '</b>';

        foreach ($this->owner->Tags() as $tag) {
            $authoringDefinition = $tag->AuthorDefinition ? $tag->AuthorDefinition : null;
            $hierarchy           = $tag->getHierarchyDisplay();

            $typeInfo = $tag->TypeNameWithFlagAttributes();

            // We want two line breaks if the term's AuthorDefinition is defined
            $tooltipText = '';
            if ($authoringDefinition) {
                $tooltipText .= $authoringDefinition . $lineBreaks;
            }

            // Show the term's hierarchy information
            $tooltipText .= $bTagOpen . 'Taxonomy' . $bTagClose . ': ' . $hierarchy;

            // Show the term's information on 'Display name singular'
            if ($tag->Title) {
                $tooltipText .= $lineBreaks . $bTagOpen . 'Singular' . $bTagClose . ': ' . $tag->Title;
            }

            // Show the term's information on 'Display name plural'
            if ($tag->TitlePlural) {
                $tooltipText .= $lineBreaks . $bTagOpen . 'Plural' . $bTagClose . ': ' . $tag->TitlePlural;
            }

            // Show the term's information on 'Type' and Type's logic attribule, i.e. which root term it belongs to,
            // it is Multi or Single
            if ($typeInfo) {
                $tooltipText .= $lineBreaks . $bTagOpen . 'Type' . $bTagClose . ': ' . $typeInfo;
            }

            // Overall required types, i.e. computed from tag's required types, the root term's required types
            // using the logic setting flag 'RequiredTypesInheritRoot'
            if ($types = $tag->RequiredTypesOverall()) {
                if ($types && $types->exists()) {
                    $tooltipText .= $lineBreaks . $bTagOpen . 'Required taxonomies' . $bTagClose . ': '
                        . implode(', ', $types->column('Name'));
                }
            }

            $tagNameFormat = <<<HTML
<span class="Select--multi">
    <span class="Select-value-label Select-value with-tooltip">
        %s<span class="at-tooltip">%s</span>
     </span>
</span>
HTML;
            $names[] = sprintf($tagNameFormat, $tag->Name, $tooltipText);
        }

        return implode(' ', $names);
    }


    /**
     * All Tags with DisplayPreference being true, give a owner object
     *
     * @return mixed
     */
    public function getDisplayableTags()
    {
        return $this->owner->Tags()->filter('DisplayPreference', true);
    }
}
