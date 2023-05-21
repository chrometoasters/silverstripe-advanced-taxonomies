<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldAddTagsAutocompleter;
use Chrometoaster\AdvancedTaxonomies\Generators\PluralGenerator;
use Chrometoaster\AdvancedTaxonomies\Generators\URLSegmentGenerator;
use Chrometoaster\AdvancedTaxonomies\ModelAdmins\TaxonomyModelAdmin;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Versioned\GridFieldArchiveAction;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

/**
 * Represents a single taxonomy term. Can be re-ordered in the CMS, and the default sorting is to use the order as
 * specified in the CMS.
 *
 * @method TaxonomyTerm Parent()
 */
class TaxonomyTerm extends DataObject implements PermissionProvider
{
    private static $table_name = 'AT_TaxonomyTerm';

    private static $singular_name = 'Taxonomy';

    private static $plural_name = 'Taxonomies';

    private static $db = [
        'Name'                     => 'Varchar(255)',
        'Title'                    => 'Varchar(255)',
        'TitlePlural'              => 'Varchar(255)',
        'URLSegment'               => 'Varchar(255)',
        'Description'              => 'Text',
        'AuthorDefinition'         => 'Text',
        'PublicDefinition'         => 'Text',
        'SingleSelect'             => 'Boolean(0)',
        'InternalOnly'             => 'Boolean(0)',
        'RequiredTypesInheritRoot' => 'Boolean(1)',
        'Sort'                     => 'Int',
    ];

    private static $indexes = [
        'URLSegment'   => true,
        'SingleSelect' => true,
        'InternalOnly' => true,
    ];

    private static $has_many = [
        'Children'                => self::class . '.Parent',
        'Terms'                   => self::class . '.Type',
        'DataObjectTaxonomyTerms' => DataObjectTaxonomyTerm::class, // used for the ManyManyThrough joining object
    ];

    private static $has_one = [
        'Parent' => self::class,
        'Type'   => self::class, // the root node of the particular taxonomy tree
    ];

    private static $many_many = [
        'RequiredTypes' => self::class,
    ];

    private static $defaults = [
        'InternalOnly'             => false,
        'RequiredTypesInheritRoot' => true,
    ];

    private static $field_labels = [
        'Title'                    => 'Display name singular',
        'TitlePlural'              => 'Display name plural',
        'SingleSelect'             => 'Single select?',
        'InternalOnly'             => 'Internal only, hide from end-users?',
        'RequiredTypesInheritRoot' => 'Inherit required types from the root term?',
    ];

    private static $searchable_fields = [
        'Name'        => ['filter' => 'PartialMatchFilter'],
        'Title'       => ['filter' => 'PartialMatchFilter'],
        'TitlePlural' => ['filter' => 'PartialMatchFilter'],
    ];

    private static $extensions = [
        Hierarchy::class,
    ];

    private static $default_sort = 'Sort';

    private static $summary_fields = [
        'getNameAsTag'             => 'Name',
        'getDescription15Words'    => 'Description',
        'getTypeNameWithFlags'     => 'Type',
        'Title'                    => 'Singular',
        'TitlePlural'              => 'Plural',
        'getAllRequiredTypesNames' => 'Requires',
    ];


    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Moving taxonomy terms is not supported
        $fields->removeByName('ParentID');
        $fields->removeByName('Sort');

        // TypeID is the ID of this term's root term which will be automatically populated by the hierarchy,
        // so it should be removed unconditionally
        $fields->removeByName('TypeID');

        // Remove the many_many_through data object DataObjectTaxonomyTerms
        $fields->removeByName('DataObjectTaxonomyTerms');

        // Begin to use _t(), first disable 'missing_default_warning', which allows _t() to be called without default
        i18n::config()->merge('missing_default_warning', false);

        // Define a literalField that presents empty line , and could be reused in many occasionz.
        $lineBreak = LiteralField::create('LineBreak', '<br />');

        // Add description to field Name
        $nameDescription = _t(static::class . '.Name_Description');
        $fields->datafieldByName('Name')->setDescription($nameDescription);

        // Add description to field Title
        $titleFieldDescription = _t(static::class . '.Title_Description');
        $fields->dataFieldByName('Title')->setDescription($titleFieldDescription);

        // Add description to field TitlePlural
        $titlePluralDescription = _t(static::class . '.TitlePlural_Description');
        $fields->dataFieldByName('TitlePlural')->setDescription($titlePluralDescription);

        // Add description to field Description
        $descriptionDescription = _t(static::class . '.Description_Description');
        $fields->dataFieldByName('Description')->setDescription($descriptionDescription)->setRows(2);

        // Add description to field AuthorDefinition
        $authorDefinitionDescription = _t(static::class . '.AuthorDefinition_Description');
        $fields->dataFieldByName('AuthorDefinition')->setDescription($authorDefinitionDescription)->setRows(2);

        // Add description to field PublicDefinition
        $publicDefinitionDescription = _t(static::class . '.PublicDefinition_Description');
        $fields->dataFieldByName('PublicDefinition')->setDescription($publicDefinitionDescription)->setRows(2);

        // Add description to field URLSegment
        $urlSegmentDescription = _t(static::class . '.URLSegment_Description');
        $fields->dataFieldByName('URLSegment')->setDescription($urlSegmentDescription);

        // Tweak InternalOnly
        if ($this->ParentID) {
            $fields->removeByName('InternalOnly');
        } else {
            // Field InternalOnly
            $internalOnlyFieldDescription = _t(static::class . '.InternalOnly_Description');
            $fields->replaceField(
                'InternalOnly',
                $publicTypeField = OptionsetField::create(
                    'InternalOnly',
                    'Internal only, hide from end-user?',
                    [1 => 'Yes', 0 => 'No']
                )
            );

            $publicTypeField->setDescription($internalOnlyFieldDescription);
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
            $singleSelectDescription = _t(
                static::class
                . '.SingleSelect' . ($checked ? '_Checked' : '') . ($readonly ? '_Readonly' : '') . '_Description',
                null,
                ['taggedClass' => $taggedClass]);
            $singleSelectField->setDescription($singleSelectDescription);
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

            // Setup sorting of TaxonomyTerm siblings, and fall back to a manual NumericField if no sorting is possible
            if (class_exists(GridFieldOrderableRows::class)) {
                $childrenGrid->getConfig()->addComponent(GridFieldOrderableRows::create('Sort'));
            } elseif (class_exists(GridFieldSortableRows::class)) {
                $childrenGrid->getConfig()->addComponent(new GridFieldSortableRows('Sort'));
            } else {
                $sortDescription = _t(static::class, 'Sort');
                $fields->addFieldToTab(
                    'Root.Main',
                    NumericField::create('Sort', 'Sort Order')
                        ->setDescription($sortDescription)
                );
            }
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
                '<p class="message good">' . _t(static::class . '.Terms_Description') . '</p>'
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

            $addExisting->setPlaceholderText('Add taxonomies by name')
                ->setButtonText('Add taxonomy');

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
            $requiredTypesDescription = _t(static::class . '.RequiredTypes_Description');
            $requiredTypesPromptField = LiteralField::create(
                'RequiredTypesExplanation',
                '<p class="message good">' . $requiredTypesDescription . '</p>'
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
                            'Title'            => 'Title',
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

        // Reorder
        if (!$this->ParentID && isset($termsTab)) {
            // reorder Tabs so Terms tab appears at the last position
            $fields->removeFieldFromTab('Root', ['Terms']);
            $fields->fieldByName('Root')->push($termsTab);
        }

        return $fields;
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

        if (!$this->URLSegment) {
            if ($this->Name) {
                $this->URLSegment = URLSegmentGenerator::generate(
                    $this->Name,
                    static::class,
                    $this->ID
                );
            }
        } elseif ($this->isChanged('URLSegment', 2)) {
            // Do a strict check on change level, to avoid double encoding caused by
            // bogus changes through forceChange()
            $this->URLSegment = URLSegmentGenerator::generate(
                $this->URLSegment,
                static::class,
                $this->ID
            );
        }
    }


    /**
     * @param null $member
     * @return bool
     */
    public function canView($member = null)
    {
        return true;
    }


    /**
     * @param null $member
     * @return bool|int|null
     */
    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('AT_TAXONOMYTERM_EDIT');
    }


    /**
     * @param null $member
     * @return bool|int|null
     */
    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('AT_TAXONOMYTERM_DELETE');
    }


    /**
     * @param null $member
     * @return bool
     */
    public function canArchive($member = null)
    {
        return $this->canDelete($member);
    }


    /**
     * @param null $member
     * @param array $context
     * @return bool|int|null
     */
    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('AT_TAXONOMYTERM_CREATE');
    }


    /**
     * @return array
     */
    public function providePermissions()
    {
        $category = 'Advanced taxonomies';

        return [
            'AT_TAXONOMYTERM_EDIT' => [
                'name' => _t(
                    self::class . '.EditPermissionLabel',
                    'Edit a taxonomy term'
                ),
                'category' => _t(
                    self::class . '.Category',
                    $category
                ),
            ],
            'AT_TAXONOMYTERM_DELETE' => [
                'name' => _t(
                    self::class . '.DeletePermissionLabel',
                    'Delete a taxonomy term and all nested terms'
                ),
                'category' => _t(
                    self::class . '.Category',
                    $category
                ),
            ],
            'AT_TAXONOMYTERM_CREATE' => [
                'name' => _t(
                    self::class . '.CreatePermissionLabel',
                    'Create a taxonomy term'
                ),
                'category' => _t(
                    self::class . '.Category',
                    $category
                ),
            ],
        ];
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
     * Find term by a url-like path, e.g. information-type/newsletter/highlights.
     *
     * @param string|array $slug
     * @param int $parentID
     * @return self|null
     */
    public static function getBySlug($slug, int $parentID = 0)
    {
        if (is_string($slug)) {
            $slug = array_filter(explode('/', $slug), function ($item) {
                return mb_strlen($item);
            });
        }
        if (!is_array($slug)) {
            throw new \RuntimeException('$slug must be a string or an array.');
        }

        $urlSegment = array_shift($slug);
        $term       = self::get()->filter(['ParentID' => $parentID, 'URLSegment' => $urlSegment])->first();

        if ($term && $term->exists()) {
            if (count($slug) === 0) {
                return $term;
            }

            return self::getBySlug($slug, $term->ID); // use of static intentional to allow override
        }

        return null;
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
     * This is the function that mimic the reversion relation for DataObject having many_many TaxonomyTerm as `Tags`
     * using polymorphic ManyManyThroughList. As such, in TaxonomyTerm side, we can't define 'TaggedObject` as a
     * $belongs_many_many relation to DataObject, instead we define a $has_many relation as "DataObjectTaxonomyTerms"
     * which is the set of DataObjectTaxonomyTerm "through" objects, and we use this $has_many relation to work out the
     * components of $belongs_many_many as "TaggedObjects"
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
     * The function in care module doesn't support polymorphic ManyManyThrough, this tweak is a solution to support
     * polymorphic ManyManyThrough
     *
     * TODO: an issue has been created, remove the overridden here once this issue is solved
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
