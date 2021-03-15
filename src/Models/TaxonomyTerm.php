<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldAddTagsAutocompleter;
use Chrometoaster\AdvancedTaxonomies\Generators\PluralGenerator;
use Chrometoaster\AdvancedTaxonomies\ModelAdmins\TaxonomyModelAdmin;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\GridFieldArchiveAction;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Represents a single taxonomy term.
 * Can be re-ordered in the CMS, and the default sorting is to use the order as specified in the CMS.
 */
class TaxonomyTerm extends BaseTerm
{
    private static $table_name = 'AT_TaxonomyTerm';

    private static $singular_name = 'Taxonomy';

    private static $plural_name = 'Taxonomies';

    private static $db = [
        'SingleSelect'             => 'Boolean(0)',
        'InternalOnly'             => 'Boolean(0)',
        'RequiredTypesInheritRoot' => 'Boolean(1)',
    ];

    private static $indexes = [
        'SingleSelect' => true,
        'InternalOnly' => true,
    ];

    private static $has_many = [
        'Children' => self::class . '.Parent',
        'Terms'    => self::class . '.Type',
        // inverse relation of Tags to the ManyManyThrough joining object: DataObjectTaxonomyTerm
        'DataObjectTaxonomyTerms' => DataObjectTaxonomyTerm::class,
        'EquivalentAltTerms'      => EquivalentTerm::class,
        'LanguageAltTerms'        => LanguageTerm::class,
        // inverse relation of AssociatedTerms to the ManyManyThrough joining object: AssociativeRelation
        'AssociativeRelations' => AssociativeRelation::class,
    ];

    private static $has_one = [
        'Parent'              => self::class,
        'Type'                => self::class, // the root node of the particular taxonomy tree
        'PrimaryConceptClass' => ConceptClass::class,
    ];

    private static $many_many = [
        'RequiredTypes'       => self::class,
        'OtherConceptClasses' => ConceptClass::class,
        'AssociatedTerms'     => [
            'through' => AssociativeRelation::class,
            'from'    => 'Source',
            'to'      => 'Destination',
        ],
    ];

    private static $owns = [
        'EquivalentAltTerms',
        'LanguageAltTerms',
    ];

    private static $many_many_extraFields = [
        'OtherConceptClasses' => [
            'Sort' => 'Int',
        ],
    ];

    private static $defaults = [
        'InternalOnly'             => 0,
        'RequiredTypesInheritRoot' => 1,
    ];

    private static $field_labels = [
        'SingleSelect'             => 'Single select?',
        'InternalOnly'             => 'Internal only, hide from end-users?',
        'RequiredTypesInheritRoot' => 'Inherit required types from the root term?',
    ];

    private static $searchable_fields = [
        'EquivalentAltTerms.Name'  => ['filter' => 'PartialMatchFilter'],
        'EquivalentAltTerms.Title' => ['filter' => 'PartialMatchFilter'],
        'LanguageAltTerms.Name'    => ['filter' => 'PartialMatchFilter'],
        'LanguageAltTerms.Title'   => ['filter' => 'PartialMatchFilter'],
    ];

    private static $extensions = [
        Hierarchy::class,
    ];

    private static $summary_fields = [
        'getNameAsTag'                => 'Name',
        'getDescription15Words'       => 'Description',
        'getTypeNameWithFlags'        => 'Type',
        'getAllRequiredTypesNames'    => 'Requires',
        'getAllAlternativeTermsNames' => 'Alternative terms',
    ];


    /**
     * Helper method to determine if equivalent terms feature should be enabled
     *
     * @return bool
     */
    public static function equivalentTermsEnabled(): bool
    {
        $setting = (bool) Config::forClass(self::class)->get('enable_equivalent_terms');
        singleton(self::class)->extend('updateEquivalentTermsEnabled', $setting);

        return $setting;
    }


    /**
     * Helper method to determine if language terms feature should be enabled
     *
     * @return bool
     */
    public static function languageTermsEnabled(): bool
    {
        $setting = (bool) Config::forClass(self::class)->get('enable_language_terms');
        singleton(self::class)->extend('updateLanguageTermsEnabled', $setting);

        return $setting;
    }


    /**
     * Helper method to determine if equivalent terms feature should be enabled
     *
     * @return bool
     */
    public static function associatedTermsEnabled(): bool
    {
        $setting = (bool) Config::forClass(self::class)->get('enable_associated_terms');
        singleton(self::class)->extend('updateAssociatedTermsEnabled', $setting);

        return $setting;
    }


    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->i18nDisableWarning();

        $fields = parent::getCMSFields();

        // Moving taxonomy terms is not supported
        $fields->removeByName('ParentID');

        // TypeID is the ID of this term's root term which will be automatically populated by the hierarchy,
        // so it should be removed unconditionally
        $fields->removeByName('TypeID');

        // Remove the many_many_through data object DataObjectTaxonomyTerms
        $fields->removeByName('DataObjectTaxonomyTerms');

        // Define a reusable literal field that represents an empty line
        $lineBreak = LiteralField::create('LineBreak', '<br />');

        // Tweak InternalOnly
        if ($this->ParentID) {
            $fields->removeByName('InternalOnly');
        } else {
            // Field InternalOnly
            $fields->replaceField(
                'InternalOnly',
                $publicTypeField = OptionsetField::create(
                    'InternalOnly',
                    'Internal only, hide from end-user?',
                    [1 => 'Yes', 0 => 'No']
                )
            );

            $publicTypeField->setDescription($this->_t('InternalOnly'));
        }

        // Tweak SingleSelect
        if ($this->ParentID) {
            $fields->removeByName('SingleSelect');
        } else {

            // SingleSelect field definition, may be transformed to read only below
            $singleSelectField = OptionsetField::create(
                'SingleSelect',
                'Single select?',
                [1 => 'Yes', 0 => 'No']
            );

            // list of classes that are tagged with a term from this type
            $typeTaggedClasses = $this->getTypeTaggedClasses();

            // when there are objects of any class tagged, the SingleSelect option should become read only
            $readonly = (count($typeTaggedClasses) !== 0);

            // is the option selected?
            $checked = (bool) $this->SingleSelect;

            // generic object pseudo-class to be used in the description text
            $taggedClass = 'data object';

            if ($readonly) {
                $singleSelectField = $singleSelectField->performReadonlyTransformation();

                // first tagged class to be used in the description text
                $taggedClass = mb_strtolower(singleton($typeTaggedClasses[0])->singular_name());
            }

            // replace the scaffolded field with the new one
            $fields->replaceField('SingleSelect', $singleSelectField);

            // provide description for different combination of states from the lang file
            $singleSelectDescriptionKey = 'SingleSelect' . ($checked ? '_Checked' : '') . ($readonly ? '_Readonly' : '');
            $singleSelectField->setDescription($this->_t($singleSelectDescriptionKey, $taggedClass));
        }


        // Tweak the Children GridField
        $childrenGrid = $fields->dataFieldByName('Children');
        if ($childrenGrid) {
            $deleteAction             = $childrenGrid->getConfig()->getComponentByType(GridFieldDeleteAction::class);
            $addExistingAutocompleter = $childrenGrid
                ->getConfig()
                ->getComponentByType(GridFieldAddExistingAutocompleter::class);

            $childrenGrid->getConfig()->removeComponent($addExistingAutocompleter);
            $childrenGrid->getConfig()->removeComponent($deleteAction);
            $childrenGrid->getConfig()->addComponent(new GridFieldDeleteAction(false));

            // Vary the button name of a GridFieldAddNewButton in Children GridField from the default
            $childrenGrid->getConfig()->getComponentByType(GridFieldAddNewButton::class)
                ->setButtonName('Add taxonomy term');

            // Setup sorting
            $childrenGrid->getConfig()->addComponent(GridFieldOrderableRows::create('Sort'));
        }

        // Tweak the OtherConceptClasses GridField
        $otherConceptClassesGrid = $fields->dataFieldByName('OtherConceptClasses');
        if ($otherConceptClassesGrid) {
            $otherClassesConfig = $otherConceptClassesGrid->getConfig();
            $otherClassesConfig->removeComponentsByType([
                GridFieldAddNewButton::class,
                GridFieldFilterHeader::class,
            ]);
            $otherClassesConfig->addComponent(GridFieldOrderableRows::create('Sort'));
        }

        // Adjust PrimaryConceptClass dropdown field
        /** @var DropdownField $primaryConceptClass */
        $primaryConceptClass = $fields->dataFieldByName('PrimaryConceptClassID');
        $primaryConceptClass->setEmptyString('--- select one ---');
        if ($this->ParentID) {
            $primaryConceptClass->setDescription($this->_t('PrimaryConceptClass'));
        }

        // Change the OtherConceptClasses tab to ConceptClasses and move the PrimaryConceptClass dropdown to this tab
        $otherConceptClassTab = $fields->findOrMakeTab('Root.OtherConceptClasses')->setTitle('Concept classes');
        $otherConceptClassTab->insertBefore('OtherConceptClasses', $primaryConceptClass);

        // Move EquivalentAltTerms and LanguageAltTerms gridfields to AlternativeTerms tab
        // and remove the LinkExisting buttons from the two grid fields, and make them orderable
        // Only applies to existing terms that are not types
        if (!$this->ParentID || !$this->exists()) {
            $fields->removeByName([
                'AlternativeTerms',
                'EquivalentAltTerms',
                'LanguageAltTerms',
            ]);
        } else {
            if (self::equivalentTermsEnabled()) {
                $fields->findOrMakeTab('Root.AlternativeTerms', 'Alternative terms');

                $gf_EquivalentAltTerms = $fields->dataFieldByName('EquivalentAltTerms');
                // remove search and add sort
                $gfc_EquivalentAltTerms = $gf_EquivalentAltTerms->getConfig();
                $gfc_EquivalentAltTerms->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
                $gfc_EquivalentAltTerms->addComponent(GridFieldOrderableRows::create('Sort'));

                $fields->addFieldToTab('Root.AlternativeTerms', $gf_EquivalentAltTerms);
            }

            if (self::languageTermsEnabled()) {
                $fields->findOrMakeTab('Root.AlternativeTerms', 'Alternative terms');

                $gf_LanguageAltTerms = $fields->dataFieldByName('LanguageAltTerms');
                // remove search and add sort
                $gfc_LanguageAltTerms = $gf_LanguageAltTerms->getConfig();
                $gfc_LanguageAltTerms->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
                $gfc_LanguageAltTerms->addComponent(GridFieldOrderableRows::create('Sort'));

                $fields->addFieldToTab('Root.AlternativeTerms', $gf_LanguageAltTerms);
            }

            // remove scaffolded tabs regardless
            $fields->removeByName(['EquivalentAltTerms', 'LanguageAltTerms']);
        }

        // The "Terms" is a reversion (hence has_many) of the has_one 'Type' relation, so the GridField of 'Terms' is only
        // shown on root terms.
        if ($this->ParentID) {
            $fields->removeByName('Terms');
        } else {
            // As a TypeID is auto-populated for all Term objects, we should not manipulate any Term
            // objects through this GridField "Terms" which is a flatten view of the taxonomy tree
            $gridTerms = $fields->dataFieldByName('Terms');
            if ($gridTerms) {
                // Remove this GridField's label
                $gridTerms->setTitle('');
                // Remove all buttons/actions that might add / delete / alter records in database
                $config = $gridTerms->getConfig();
                $config->removeComponentsByType(GridFieldArchiveAction::class)
                    ->removeComponentsByType(GridFieldEditButton::class)
                    ->removeComponentsByType(GridFieldDeleteAction::class)
                    ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
                    ->removeComponentsByType(GridFieldAddNewButton::class)
                    ->getComponentByType(GridFieldDataColumns::class);
            }

            // Rename Tab Root.Terms, add description to this Terms GridField
            $termsTab         = $fields->findOrMakeTab('Root.Terms')->setTitle('Descendants list');
            $termsDescription = LiteralField::create(
                'TermsDescription',
                '<p class="message good">' . $this->_t('Terms') . '</p>'
            );
            $termsTab->insertBefore('Terms', $termsDescription);
            $termsTab->insertBefore('Terms', $lineBreak);
        }

        // RequiredType Grid Tweaks
        $gridRequiredTypes = $fields->dataFieldByName('RequiredTypes');
        // A new record before saving to DB doesn't have the grid field, so check its existence first
        if ($gridRequiredTypes) {
            // Remove RequiredTypes' GridField
            $gridRequiredTypes->setTitle('');
            $config = $gridRequiredTypes->getConfig();
            $config
                ->removeComponentsByType(GridFieldAddNewButton::class)
                ->removeComponentsByType(GridFieldArchiveAction::class)
                ->removeComponentsByType(GridFieldEditButton::class)
                ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
                ->addComponent(
                    $addExisting = new GridFieldAddTagsAutocompleter('buttons-before-left')
                );

            $addExisting->setPlaceholderText('Add taxonomies by name')->setButtonText('Add taxonomy');

            // Not to confuse user, we are not going to show the RequiredTypes in this GridField, which is to show all
            // the RequiredTypes for a TaxonomyTerm
            $dataColumns    = $config->getComponentByType(GridFieldDataColumns::class);
            $displayColumns = $dataColumns->getDisplayFields($gridRequiredTypes);
            if (isset($displayColumns['getAllRequiredTypesNames'])) {
                unset($displayColumns['getAllRequiredTypesNames']);
            }

            // Only make root terms available as RequiredTypes, disable to link the type itself as the RequiredTypes
            $searchList = self::get()->filter('ParentID', 0)->exclude('ID', $this->ID);
            $addExisting->setSearchList($searchList);
        }

        // Change the Tab RequiredTypes' label
        $requiredTypesTab = $fields->findOrMakeTab('Root.RequiredTypes')->setTitle('Required taxonomies');

        // Tweaks the RequiredTypesInheritRoot flag
        $showInheritFlag = false;

        // Only show the flag on Non-root term
        if ($this->ParentID) {
            // Only show the flag when its root has set required types
            $type = $this->Type();
            if ($type && $type->exists()) {
                $rootRequiredTypes = $type->RequiredTypes();
                if ($rootRequiredTypes && $rootRequiredTypes->exists()) {
                    $showInheritFlag           = true;
                    $requiredTypesInheritField = OptionsetField::create(
                        'RequiredTypesInheritRoot',
                        $this->fieldLabel('RequiredTypesInheritRoot'),
                        [1 => 'Yes', 0 => 'No']
                    );
                    $requiredTypesTab->insertBefore('RequiredTypes', $requiredTypesInheritField);
                    $requiredTypesTab->insertBefore('RequiredTypes', $lineBreak);
                }
            }
        }

        if (!$showInheritFlag) {
            $fields->removeByName('RequiredTypesInheritRoot');
        }

        // Add description of RequiredTypes
        if ($gridRequiredTypes) {
            $requiredTypesPromptField = LiteralField::create(
                'RequiredTypesExplanation',
                '<p class="message good">' . $this->_t('RequiredTypes') . '</p>'
            );
            $requiredTypesTab->insertBefore('RequiredTypes', $requiredTypesPromptField);
            $requiredTypesTab->insertBefore('RequiredTypes', $lineBreak);
        }

        // Add a list of tagged data objects
        if ($this->exists()) {
            $taggedObjects = $this->getTaggedDataObjects();
            if ($taggedObjects->count()) {
                //$taggedTab = $fields->findOrMakeTab('Root.Tagged');
                $fields->addFieldToTab(
                    'Root.Tagged',
                    GridField::create(
                        'TaggedObjects',
                        'Objects',
                        $taggedObjects,
                        $taggedGridConf = GridFieldConfig_Base::create()
                    )
                );

                // Customise the GridField's data columns and field castings
                $taggedGridConf->getComponentByType(GridFieldDataColumns::class)
                    ->setDisplayFields(
                        [
                            'ID'               => 'ID',
                            'singular_name'    => 'Data model (object type)',
                            'Name'             => 'Name',
                            'AT_LinkedThrough' => 'Relation',
                            'AT_CMSLink'       => 'Edit',
                        ]
                    )
                    ->setFieldCasting(
                        [
                            'AT_CMSLink' => 'HTMLFragment->RAW',
                        ]
                    );

                // Increase the amount of items shown per page to 100
                $taggedGridConf->getComponentByType(GridFieldPaginator::class)
                    ->setItemsPerPage(100);
            }
        }

        // Remove AssociativeRelations GridField as it's a reverse relation
        $fields->removeByName('AssociativeRelations');

        // Only add/configure Associated terms gridfield for existing terms that are not types, when enabled
        if (!$this->ParentID || !$this->exists() || !self::associatedTermsEnabled()) {
            $fields->removeByName('AssociatedTerms');
        } else {
            $associatedGridField = $fields->dataFieldByName('AssociatedTerms');

            if (AssociativeRelationType::get()->count()) {
                $associatedGridConfig = $associatedGridField->getConfig();
                $associatedGridConfig->removeComponentsByType([
                    GridFieldAddNewButton::class,
                    GridFieldDataColumns::class,
                    GridFieldEditButton::class,
                    GridFieldArchiveAction::class,
                    GridFieldAddExistingAutocompleter::class,
                ]);
                $associatedGridConfig->addComponents([
                    new GridFieldAddExistingAutocompleter('buttons-before-left'),
                    GridFieldOrderableRows::create('Sort'),
                ]);

                // Make the GridField editable inline per row by GridFieldEditableColumns
                $associatedGridConfig->addComponent($editableColumns = new GridFieldEditableColumns());
                $editableColumns->setDisplayFields(
                    [
                        'ComponentAssociativeRelationTypeID' => [
                            'callback' => function ($record, $col, $grid) {
                                return DropdownField::create(
                                    $col,
                                    'Associative type',
                                    AssociativeRelationType::get()->map(),
                                )->setEmptyString('--- select an associative type ---');
                            },
                            'title' => 'Associative type',
                        ],
                        'ComponentAssociativeIsInverseRelation' => [
                            'callback' => function ($record, $col, $grid) {
                                return CheckboxField::create(
                                    $col,
                                    'Inverse relation (right to left)?',
                                );
                            },
                            'title' => 'Inverse relation (right to left)?',
                        ],
                        'getNameAsTag' => 'Associated taxonomy term',
                    ]
                );
            } else {
                $fields->removeFieldFromTab('Root.AssociatedTerms', 'AssociatedTerms');

                $noTypesOfAssociationDefined = 'Please define at least one associative relation type first.';
                $fields->addFieldToTab(
                    'Root.AssociatedTerms',
                    LiteralField::create(
                        'NoTypesOfAssociationDefined',
                        '<p class="message warning">' . $noTypesOfAssociationDefined . '</p>'
                    )
                );
            }
        }

        // Reorder
        if (!$this->ParentID && isset($termsTab)) {
            // reorder Tabs so Terms tab appears at the last position
            $fields->removeFieldFromTab('Root', ['Terms']);
            $fields->fieldByName('Root')->push($termsTab);
        }

        $this->i18nRestoreWarningConfig();

        return $fields;
    }


    /**
     * This is used in GridFieldEditableColumns where the item as the Destination side TaxonomyTerm per row contains
     * its component AssociativeRelation data object. The AssociativeRelation will be updated with the given
     * AssociativeRelationTypeID
     *
     * @param $typeID
     */
    public function saveComponentAssociativeRelationTypeID($typeID): void
    {
        $associativeRelation = $this->getComponentAssociative();
        if ($associativeRelation && $associativeRelation->exists()) {
            $associativeRelation->AssociativeRelationTypeID = $typeID;
        }
        $associativeRelation->write();
    }


    /**
     * @return int
     */
    public function getComponentAssociativeRelationTypeID(): int
    {
        $associativeRelation = $this->getComponentAssociative();
        if ($associativeRelation && $associativeRelation->exists()) {
            return $associativeRelation->AssociativeRelationTypeID;
        }

        return 0;
    }


    /**
     * @param $isInverseRelation
     */
    public function saveComponentAssociativeIsInverseRelation($isInverseRelation): void
    {
        $associativeRelation = $this->getComponentAssociative();
        if ($associativeRelation && $associativeRelation->exists()) {
            $associativeRelation->IsInverseRelation = (bool) $isInverseRelation;
        }
        $associativeRelation->write();
    }


    /**
     * @return bool
     */
    public function getComponentAssociativeIsInverseRelation(): bool
    {
        $associativeRelation = $this->getComponentAssociative();
        if ($associativeRelation && $associativeRelation->exists()) {
            return (bool) $associativeRelation->IsInverseRelation;
        }

        return false;
    }


    /**
     * @return DataObjectInterface|null
     */
    private function getComponentAssociative(): ?DataObjectInterface
    {
        $componentName = AssociativeRelation::config()->get('table_name');

        return $this->{$componentName};
    }


    /**
     * Find a list of data classes that have at least one instance tagged with a term of the current type
     *
     * This method is mostly used to determine if at least one term of the type has been used to tag an object,
     * primarily driving the logic around SingleSelect — whether that attribute of a type can be still changed or not.
     *
     * @return array
     */
    public function getTypeTaggedClasses(bool $includeTypeItself = false): array
    {
        $typeTaggedClasses = [];

        // Get all terms for this type, any level in the hierarchy
        $typeTerms = self::get()->filter(['TypeID' => $this->TypeID]);

        // include the type itself?
        if ($includeTypeItself === false) {
            $typeTerms = $typeTerms->exclude(['ID' => $this->TypeID]);
        }

        if (count($typeTerms)) {
            // ManyManyThrough joining table
            $tableName = Config::inst()->get(DataObjectTaxonomyTerm::class, 'table_name');

            if (ClassInfo::hasTable($tableName)) {
                // table exists
                $termIDs            = $typeTerms->column('ID');
                $termIDPlaceholders = DB::placeholders($termIDs);

                $typeTaggedClasses = SQLSelect::create(
                    '"OwnerObjectClass", COUNT(1) as NumRecords',
                    "\"{$tableName}\"",
                    ["\"JointObjectID\" IN ($termIDPlaceholders)" => $termIDs], // where
                    [], // order by
                    ['"OwnerObjectClass"'], // group by
                    ['COUNT("OwnerObjectClass") > 0'] // having
                )->execute()->column('OwnerObjectClass');
            }
        }

        // extension point to provide the same kind of functionality for custom tagging fields
        // that are not based on the ManyManyThrough class this module provides
        $this->extend('updateTypeTaggedClassesList', $typeTaggedClasses);

        return $typeTaggedClasses;
    }


    /**
     * Delete all associated children when a taxonomy term is deleted
     *
     * {@inheritDoc}
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        foreach ($this->Children() as $term) {
            /** @var TaxonomyTerm $term */
            $term->delete();
        }
    }


    /**
     * Provide the SingleSelect flag for the taxonomy type
     *
     * @return bool
     */
    public function getIsSingleSelect(): bool
    {
        return (bool) $this->Type()->getField('SingleSelect');
    }


    /**
     * Provide the InternalOnly flag for the taxonomy type
     *
     * @return bool
     */
    public function getIsInternalOnly(): bool
    {
        return (bool) $this->Type()->getField('InternalOnly');
    }


    /**
     * Set the "type" relationship for this to that of the parent (recursively)
     * This guarantees that all nodes in this 'family' branch have the same reference to root TaxonomyTerm
     *
     * {@inheritDoc}
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->Parent()->exists()) {
            // Write the parent's type to the current term (assumes the parent to be already written in the db)
            $this->TypeID = $this->Parent()->TypeID;
        } elseif ($this->ID) {
            // A root node, populate TypeID with its own ID
            $this->TypeID = $this->ID;
        }

        if (!$this->Title && $this->Name) {
            $this->Title = $this->Name;
        }

        if (!$this->TitlePlural && $this->Title) {
            $this->TitlePlural = PluralGenerator::generate($this->Title);
        }
    }


    /**
     * Provide a legible term hierarchy with a customisable levels separator
     *
     * Optionally, a callback can be provided to be applied to each term before
     * it's added to the list to be merged into a string.
     *
     * Return formats:
     * 1. "parentName ▸ childName ▸ grantChildName" if it is not a root term
     * 2. "myName" if it is a root term
     *
     * @param string $separator
     * @return string
     */
    public function getTermHierarchy(string $separator = ' ▸ ', callable $termsDecorator = null)
    {
        // default decorator if none is provided
        $plaintextDecorator = function (TaxonomyTerm $term) {
            return sprintf('%s', $term->Name);
        };

        $termsDecorator = $termsDecorator ?: $plaintextDecorator;

        // hierarchy parts that will be joined together with the levels separator, decorated via a callback if defined
        $parts = array_map($termsDecorator, array_reverse($this->getAncestors()->toArray()));

        // add self as the last item
        $parts[] = $termsDecorator($this);

        return implode($separator, $parts);
    }


    /**
     * Get term depth in the hierarchy
     *
     * @param bool $asArrayList
     * @return int
     */
    public function getTermLevel(): int
    {
        $level = 0;
        $term  = $this;

        while ($term->ParentID) {
            $level++;
            $term = $term->Parent();
        }

        return $level;
    }


    /**
     * Get a list of dummy position indicators for use in templates
     * to e.g. indent terms or so
     *
     * @return ArrayList
     */
    public function getTermLevelList(): ArrayList
    {
        $list = ArrayList::create([]);

        for ($i = 0; $i < $this->getTermLevel(); $i++) {
            $list->push(ArrayData::create(['Pos' => $i]));
        }

        return $list;
    }


    /**
     * Description limited to 15 words for limited spaces such as gridfields
     *
     * @return string
     */
    public function getDescription15Words()
    {
        $text = DBText::create('Description');
        $text->setValue($this->Description);

        return $text->LimitWordCount(15);
    }


    /**
     * The function is to provide this taxonomy term presented like a 'tag' which is wrapped by special HTML tags with
     * some special classes
     *
     * @return DBHTMLText
     */
    public function getNameAsTag()
    {
        return DBField::create_field(
            DBHTMLText::class,
            (string) $this->renderWith('Chrometoaster\\AdvancedTaxonomies\\Term_Tag')
        );
    }


    /**
     * Provide term name as a tag with additional term info
     *
     * Additional information is added to the presentation as tooltip, allowing for the information to be
     * presented also e.g. in GridField columns, where the space is limited.
     *
     * @return DBHTMLText
     */
    public function getNameAsTagWithExtraInfo(): DBHTMLText
    {
        return DBField::create_field(
            DBHTMLText::class,
            (string) $this->customise(['ShowTermExtraInfo' => true])->renderWith('Chrometoaster\\AdvancedTaxonomies\\Term_Tag')
        );
    }


    /**
     * Get taxonomy type name with its SingleSelect and InternalOnly attributes
     *
     * @return string
     */
    public function getTypeNameWithFlags(): string
    {
        if ($this->exists() && $this->Type()->exists()) {
            $type  = $this->Type();
            $title = $type->Name;

            return $title
                . ' ('
                . ($type->SingleSelect ? 'Single' : 'Multi')
                . ($type->InternalOnly ? '; Internal only' : '; Public')
                . ')';
        }

        return '';
    }


    /**
     * Collate all required types for a term
     *
     * As RequiredTypesInheritRoot flag and RequiredTypes many_many relation exist on any level of the hierarchy,
     * the RequiredTypes can be customised per term.
     *
     * This function calculating the result (a list of required types) for each term following this logic:
     * - if the RequiredTypesInheritRoot flag is true (default value), it produces the conjunction of both the root
     *   term's RequiredTypes and the current term's RequiredTypes
     * - if the RequiredTypesInheritRoot flag is false, it ignores the root term's RequiredTypes value.
     *
     * @return DataList
     */
    public function getAllRequiredTypes(): DataList
    {
        if (!$this->RequiredTypesInheritRoot) {
            return $this->RequiredTypes();
        }

        // get a unique list of required type IDs
        $termRequiredTypeIDs = array_unique(
            array_merge(
                $this->Type()->RequiredTypes()->column('ID'),
                $this->RequiredTypes()->column('ID')
            )
        );

        if (count($termRequiredTypeIDs)) {
            return self::get()->filterAny('ID', $termRequiredTypeIDs);
        }

        return self::get()->filter('ID', -9999); // arbitrary ID of a non-existing term to return an empty DataList
    }


    /**
     * Get a list of names for all term's required types
     *
     * The output is used in a grio field for a nicer presentation of all the required types per taxonomy term.
     * Optional delimiter can be specified, default is a line break tag.
     *
     * DBHTMLText class is used to produce a nicely formatted message leveraging the HTMLFragment->RAW column casting.
     *
     * @param string $delimiter
     * @return DBHTMLText
     */
    public function getAllRequiredTypesNames(string $delimiter = '<br />'): DBHTMLText
    {
        $names = $this->getAllRequiredTypes()->column('Name');

        return DBField::create_field(DBHTMLText::class, implode($delimiter, $names));
    }


    /**
     * Get a list of lists of alternative terms associated to this term
     *
     * The list is grouped by the alt term class, such as (e.g. EquivalentAltTerm, LanguageAltTerm etc.)
     *
     * @throws \ReflectionException
     * @return array
     */
    public function getAllAlternativeTermsGroupedByType(): array
    {
        $groups = [];

        foreach (ClassInfo::subclassesFor(AlternativeTerm::class, false) as $altTermClass) {

            // find the first has_one field as each alternative term needs a reverse relation to this class
            foreach (Config::inst()->get($altTermClass, 'has_one') as $field => $taxonomyTermClass) {
                if (is_a($taxonomyTermClass, self::class, true)) {
                    if (!array_key_exists($altTermClass, $groups)) {
                        $groups[$altTermClass] = [];
                    }

                    $group = [];
                    foreach (DataObject::get($altTermClass)->filter($field . 'ID', $this->ID) as $altTerm) {
                        if ($altTerm && $altTerm->exists()) {
                            $group[] = $altTerm;
                        }
                    }

                    if (count($group)) {
                        $groups[$altTermClass] += $group;
                    }

                    // finish once we've found the first reverse relation
                    break;
                }
            }
        }

        return $groups;
    }


    /**
     * Get a field from the most relevant Language Alternative term (either by the locale, the primary flag)
     *
     * When no suitable alt term is found this term is used as the fallback
     *
     * Note: No return type is specified due to the nature of how Silverstripe works with db fields.
     *
     * @param string $field
     * @param string $locale
     * @return mixed|DataObject|DBField|null
     */
    public function LanguageAltTermField(string $field, string $locale = '')
    {
        $langTerms = $this->LanguageAltTerms();

        if ($locale) {
            $filter = ['Locale' => $locale];
        } else {
            $filter = ['IsPrimary' => true];
        }
        $term = $langTerms->filter($filter)->first();

        if (!$term) {
            $term = $this;
        }

        return $term && $term->hasField($field) ? $term->getField($field) : null;
    }


    /**
     * Get a field from the relevant Equivalent Alternative term (by the type)
     *
     * Note: No return type is specified due to the nature of how Silverstripe works with db fields.
     *
     * @param string $field
     * @param string $locale
     * @return mixed|DataObject|DBField|null
     */
    public function EquivalentAltTermField(string $field, string $type)
    {
        $term = $this->EquivalentAltTerms()->filter(['EquivalentType' => $type])->first();

        return $term && $term->hasField($field) ? $term->getField($field) : null;
    }


    /**
     * Get a flat list of all alt terms associated to this taxonomy term
     *
     * @return SS_List
     */
    public function getAllAlternativeTerms(): SS_List
    {
        $ids = [];

        foreach ($this->getAllAlternativeTermsGroupedByType() as $type => $altTerms) {
            foreach ($altTerms as $altTerm) {
                $id       = $altTerm->ID;
                $ids[$id] = $id; // using the ID as the key as well as the value to keep only unique values
            }
        }

        if (count($ids)) {
            return AlternativeTerm::get()->byIDs(array_values($ids));
        }

        return ArrayList::create();
    }


    /**
     * Get a list of lists of alternative terms associated to this term for a term rich-info overview
     *
     * @param string $delimiter
     * @throws \ReflectionException
     * @return DBHTMLText
     */
    public function getAllAlternativeTermsNames(string $delimiter = '<br />'): DBHTMLText
    {
        $names = [];

        foreach ($this->getAllAlternativeTermsGroupedByType() as $type => $altTerms) {
            if (!count($altTerms)) {
                continue; // skip groups with no terms
            }

            $altTermTitles = [];

            foreach ($altTerms as $altTerm) {
                $altTermTitles[] = $altTerm->getAltTermTitle();
            }

            $names[] = sprintf('<b>%s</b>: %s', singleton($type)->plural_name(), implode(', ', $altTermTitles));
        }

        return DBField::create_field(DBHTMLText::class, implode($delimiter, $names));
    }


    /**
     * Get a primarily assigned concept class, taxonomy type inheritence is used if not set.
     *
     * @return ConceptClass|null
     */
    public function getConceptClass(): ?ConceptClass
    {
        $primaryConceptClass = $this->PrimaryConceptClass();
        if ($primaryConceptClass && $primaryConceptClass->exists()) {
            return $primaryConceptClass;
        }

        $type = $this->Type();
        if ($type && $type->exists()) {
            $typePrimaryConceptClass = $type->PrimaryConceptClass();
            if ($typePrimaryConceptClass && $typePrimaryConceptClass->exists()) {
                return $typePrimaryConceptClass;
            }
        }

        return null;
    }


    /**
     * Get all concept classes associated with the term
     *
     * This covers both primary (either assigned explicitly or through taxonomy type) and other.
     *
     * @return ArrayData
     */
    public function getAllConceptClasses(): ArrayData
    {
        $all            = [];
        $all['Primary'] = $this->getConceptClass();
        $all['Other']   = $this->OtherConceptClasses()->sort('Sort');

        return ArrayData::create($all);
    }


    /**
     * Get a list of types with the single select flag on, optionally based on a list of terms
     *
     * @param SS_List|null $terms
     * @return DataList
     */
    public static function getSingleSelectOnlyTypes(SS_List $terms = null): DataList
    {
        $singleSelectTypes = self::get()->filter(['ParentID' => 0, 'SingleSelect' => true]);

        if ($terms) {
            $termsTypeIDs = array_unique($terms->column('TypeID'));
            if (count($termsTypeIDs)) {
                // a narrow down list of types based on the list of terms provided
                return $singleSelectTypes->filterAny('ID', $termsTypeIDs);
            }

            // filtered list with no candidates
            return self::get()->filter('ID', -9999); // arbitrary ID of a non-existing term to return an empty DataList
        }

        // all single select types non-filtered
        return $singleSelectTypes;
    }


    /**
     * Generator method mimicing the reverse relation for DataObject having many_many TaxonomyTerm as `Tags`
     * using polymorphic ManyManyThroughList.
     *
     * On the TaxonomyTerm side, we can't define 'TaggedObject' as $belongs_many_many relation to DataObject,
     * instead we define a $has_many relation 'DataObjectTaxonomyTerms' which is the set of
     * DataObjectTaxonomyTerm "through" objects, and we use this $has_many relation to work out the components of
     * $belongs_many_many as "TaggedObjects".
     *
     * @return \Generator
     */
    public function TaggedObjects()
    {
        foreach ($this->DataObjectTaxonomyTerms() as $throughObject) {
            yield $throughObject->OwnerObject();
        }
    }


    /**
     * The function in core module doesn't support polymorphic ManyManyThrough, this tweak is a solution to support
     * polymorphic ManyManyThrough
     *
     * TODO: an issue has been created, remove the override here once this issue is resolved
     * https://github.com/silverstripe/silverstripe-framework/issues/9227
     *
     * @param string $remoteClass
     * @param string $remoteRelation
     * @return DataList|DataObject|null
     */
    public function inferReciprocalComponent($remoteClass, $remoteRelation)
    {
        $remote = DataObject::singleton($remoteClass);

        // Check the relation type to mock
        $relationType = $remote->getRelationType($remoteRelation);

        switch ($relationType) {
            case 'many_many':
            case 'belongs_many_many':
                $manyMany = $remote->getSchema()->manyManyComponent($remoteClass, $remoteRelation);
                if (isset($manyMany['parentClass']) && $manyMany['parentClass'] == DataObject::class) {
                    return null;
                }

                break;
            default:
                return parent::inferReciprocalComponent($remoteClass, $remoteRelation);
        }

        return parent::inferReciprocalComponent($remoteClass, $remoteRelation);
    }


    /**
     * Add extra info to a tagged object item for display purposes in the listing gridfield
     *
     * @param DataObject $item
     * @param string $field
     * @param string $relation
     * @return ViewableData
     */
    private static function decorateTaggedDataObject(DataObject $item, string $field, string $relation): ViewableData
    {
        $cmsLink = '';
        if ($item->hasMethod('CMSEditLink')) {
            $cmsLink = sprintf('<a href="%s" target="_blank" class="at-link-external">Edit</a>', $item->CMSEditLink());
        }

        $item->AT_UniqueID      = sprintf('%s-%s-%s-%s', $item->ClassName, $item->ID, $field, $relation);
        $item->AT_LinkedThrough = sprintf('%s (%s)', $field, $relation);
        $item->AT_CMSLink       = $cmsLink;

        return $item;
    }


    /**
     * Get a list of many_many_through mapping objects for classes where the 'to' relation is TaxonomyTerm
     *
     * The output is in the form of
     *
     * list of mapping classes -> list of classes using this mapping -> mapping details
     *
     * 'Chrometoaster\AdvancedTaxonomies\Models\DataObjectTaxonomyTerm' => [
     *     'Page.Tags' => [
     *          'RelationName'      => 'Tags',
     *          'BridgingClassName' => 'Chrometoaster\AdvancedTaxonomies\Models\DataObjectTaxonomyTerm',
     *          'GetOwnerFuncName'  => 'OwnerObject',
     *     ],
     *     'SilverStripe\Assets\File.Tags' => [
     *        'RelationName'      => 'Tags',
     *        'BridgingClassName' => 'Chrometoaster\AdvancedTaxonomies\Models\DataObjectTaxonomyTerm',
     *        'GetOwnerFuncName'  => 'OwnerObject',
     *     ],
     * ],
     * 'SomeNameSpace\MyObjectClassName' => [
     *     'Page.AnotherManyManyRelation' => [
     *          'RelationName'      => 'AnotherManyManyRelation',
     *          'BridgingClassName' => 'SomeNameSpace\MyObjectClassName',
     *          'GetOwnerFuncName'  => 'Origin',
     *     ],
     * ],
     *
     * TODO: consider local caching
     */
    private static function getManyManyThroughMappingClasses()
    {
        $mapping = [];

        // consider all subclasses of DataObject
        $classes = ClassInfo::subclassesFor(DataObject::class);

        /** @var DataObjectSchema $dbSchema */
        $dbSchema = DataObject::getSchema();

        foreach ($classes as $class) {
            $manyMany = (array) Config::inst()->get($class, 'many_many');

            foreach ($manyMany as $field => $fieldType) {
                if (
                    is_array($fieldType)
                    && array_key_exists('through', $fieldType)
                    && array_key_exists('from', $fieldType)
                    && array_key_exists('to', $fieldType)
                    // to TaxonomyTerm class?
                    && $dbSchema->hasOneComponent($fieldType['through'], $fieldType['to']) === self::class
                ) {
                    $throughClass = $fieldType['through'];
                    if (!array_key_exists($throughClass, $mapping)) {
                        $mapping[$throughClass] = [];
                    }

                    $mapping[$throughClass][$class . '.' . $field] = [
                        'relation'      => $field,
                        'ownerClass'    => $class,
                        'ownerAccessor' => $fieldType['from'],
                    ];
                }
            }
        }

        return $mapping;
    }


    /**
     * Get a list of all objects tagged with this taxonomy term
     *
     * @throws \ReflectionException
     * @return ArrayList
     */
    public function getTaggedDataObjects(): ArrayList
    {
        $list       = ArrayList::create();
        $classes    = ClassInfo::subclassesFor(DataObject::class);
        $mmtMapping = self::getManyManyThroughMappingClasses();

        foreach ($classes as $class) {
            // Exclude TaxonomyTerm and its subclasses from being the candidates to checked ralations, for the cases
            // such as, terms are assigned to terms via hierarchy, etc
            if (is_a($class, self::class, true)) {
                continue;
            }

            foreach (['has_one', 'has_many', 'many_many'] as $relation) {
                $relationCandidates = (array) Config::inst()->get($class, $relation);

                foreach ($relationCandidates as $field => $fieldType) {
                    if ($fieldType === self::class) {

                        // db field to filter on — FieldNameID for has_one, or FieldName.ID for composite relations
                        $filterField = $field . ($relation === 'has_one' ? '' : '.') . 'ID';

                        $items = DataObject::get($class)->filter($filterField, $this->ID);

                        // TODO: find out how to get the mapped class to match
                        $items->each(function ($item) use ($list, $field, $relation, $mmtMapping) {
                            // special treatment of relations that form many_many_through mappings
                            if (array_key_exists($item->ClassName, $mmtMapping)) {
                                $mmtMaps = $mmtMapping[$item->ClassName]; //[sprintf('%s.%s', $item->class, $field)];

                                foreach ($mmtMaps as $mmtMap) {
                                    $owner = $item->{$mmtMap['ownerAccessor']}();
                                    if ($owner && $owner->exists() && $owner->ClassName === $mmtMap['ownerClass']) {
                                        $fieldName = $mmtMap['relation'];
                                        $relationName = 'many_many_through';

                                        break;
                                    }
                                    $owner = null;
                                }
                            } else {
                                $owner = $item;
                                $fieldName = $field;
                                $relationName = $relation;
                            }

                            if ($owner && $owner->exists()) {
                                $list->push(self::decorateTaggedDataObject($owner, $fieldName, $relationName));
                            }
                        });
                    }
                }
            }
        }

        // Remove duplicates
        $list->removeDuplicates('AT_UniqueID');

        return $list;
    }


    /*
     * Get a ModelAdmin edit link with an optional landing tab name
     *
     * Creates a link to {@link Chrometoaster\AdvancedTaxonomies\ModelAdmins\TaxonomyModelAdmin} with a landing tab
     * name attached to the link as a hash, to select that particular tab. This may not always work due to bugs in SS.
     *
     * @param TaxonomyTerm $tag
     * @param string $landingTab
     * @return mixed|string
     */
    public function getModelAdminEditLink(string $landingTab = 'Root_Main')
    {
        $admin             = singleton(TaxonomyModelAdmin::class);
        $admin->modelClass = self::class;
        $admin->init();
        $gridFieldName = str_replace('\\', '-', self::class);
        $gridField     = $admin->getEditForm()->Fields()->dataFieldByName($gridFieldName);
        $linkedURL     = $gridField->Link();

        $subURL = [];
        $node   = $this;
        while ($node->ParentID) {
            $subURL[] = 'ItemEditForm/field/Children/item/' . $node->ID . '/';
            $node     = $node->Parent();
        }
        $subURL[] = '/item/' . $node->ID . '/';

        $termEditURL = $linkedURL . rtrim(implode('', array_reverse($subURL)), '/') . '/edit?#' . $landingTab;

        return sprintf('<a href="%s" target="_blank" class="at-link-external">%s</a>', $termEditURL, $this->Name);
    }
}
