<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldAddTagsAutocompleter;
use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldOrderableRows;
use Chrometoaster\AdvancedTaxonomies\Models\DataObjectTaxonomyTerm;
use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use Chrometoaster\AdvancedTaxonomies\Validators\TaxonomyRulesValidator;
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
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\GridFieldArchiveAction;

/**
 * Class DataObjectTaxonomiesDataExtension
 *
 * This extension will be applied to classes like SiteTree, BaseElement, File and any other models that extend
 * from DataObject. It adds a many-many relation (labelled 'Tags') between the model and the TaxonomyTerm.
 * It provides a generic CMS user interface for adding tags to the models.
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

    // Publish the joining object and the joined TaxonomyTerm object whenever the owner gets published
    private static $owns = [
        'Tags',
    ];

    // Delete the joining objects when the owner gets deleted
    private static $cascade_deletes = [
        'Tags',
    ];

    // Duplicate the joining objects when the owner gets duplicated
    private static $cascade_duplicates = [
        'Tags',
    ];


    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Hide all Tags related fields for unsaved dataobjects
        if (!$this->getOwner()->exists()) {
            return;
        }

        // Remove config components from the Tags gridfield to disallow adding/deleting/archiving taxonomy terms from here
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
                'getNameAsTagWithExtraInfo' => 'Name',
                'getDescription15Words'     => 'Description',
                'getAllRequiredTypesNames'  => 'Requires',
            ]
        );

        $components->addComponent(GridFieldOrderableRows::create('Sort'));

        $autoResultFormat = '&nbsp;{$getTermHierarchy}&nbsp;';
        $addExisting->setResultsFormat($autoResultFormat);
        $addExisting->setPlaceholderText('Add tags by name');

        // create a list of term candidates, excluding single select types which already have a tag in the list
        // - this works in real time as adding a tag adds it to the list, even when unsaved
        $searchList          = DataList::create(TaxonomyTerm::class);
        $singleSelectTypeIDs = TaxonomyTerm::getSingleSelectOnlyTypes($this->getOwner()->Tags());
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
                $this->getOwner()->Tags(),
                $components
            )
        );

        $validator                    = TaxonomyRulesValidator::create();
        $requiredTypesValidationError = $validator->validateRequiredTypes(
            $this->getOwner()->Tags(),
            ...$validator->getValidationMessagesDecorators()
        );

        if ($requiredTypesValidationError) {
            $fields->addFieldToTab(
                'Root.Tags',
                LiteralField::create('MissingTagsWarning', sprintf('<p class="bad message">%s</p>', $requiredTypesValidationError)),
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
     * Place the tags summary column to the end of the list of summary fields
     *
     * @param mixed $fields
     * @return array
     */
    public function updateSummaryFields(&$fields)
    {
        $tagNamesSummary = null;
        foreach ($fields as $key => $summary) {
            if ($key === 'getNamesAsTagWithExtraInfo') {
                $tagNamesSummary = $fields[$key];
                unset($fields[$key]);
            }
        }

        if ($tagNamesSummary) {
            $fields['getNamesAsTagWithExtraInfo'] = $tagNamesSummary;
        }
    }


    /**
     * Provide model's associated terms' names as tags with additional term info
     *
     * Additional information is added to the presentation as tooltip, allowing for the information to be
     * presented also e.g. in GridField columns, where the space is limited.
     *
     * @param string $delimiter
     * @return DBHTMLText|DBField
     */
    public function getTagNamesWithExtraInfo(string $delimiter = ' '): DBHTMLText
    {
        $names = [];

        /** @var TaxonomyTerm $tag */
        foreach ($this->getOwner()->Tags() as $tag) {
            $names[] = (string) $tag->getNameAsTagWithExtraInfo();
        }

        return DBField::create_field(DBHTMLText::class, implode($delimiter, $names));
    }


    /**
     * All terms with InternalOnly being true for the given owner object
     *
     * @return mixed
     */
    public function getDisplayableTags()
    {
        return $this->getOwner()->Tags()->filter('InternalOnly', false);
    }
}
