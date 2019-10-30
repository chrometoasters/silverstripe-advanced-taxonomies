<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldAddTagsAutocompleter;
use Chrometoaster\AdvancedTaxonomies\Forms\GridFieldOrderableRows;
use Chrometoaster\AdvancedTaxonomies\Generators\PluralGenerator;
use Chrometoaster\AdvancedTaxonomies\Generators\URLSegmentGenerator;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Versioned\GridFieldArchiveAction;
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
        'DisplayPreference'        => 'Boolean(1)',
        'RequiredTypesInheritRoot' => 'Boolean(1)',
        'Sort'                     => 'Int',
    ];

    private static $indexes = [
        // The way we create the URLSegment make this value unique object-type-wisely, so ideally we should set it as:
        // 'URLSegment' => ['type'=>'unique', 'value'=>'URLSegment', 'ignoreNulls'=>true], but the setting makes lots of
        // issues due to duplicated Null value for MSSQL database engine.
        'URLSegment'        => true,
        'SingleSelect'      => true,
        'DisplayPreference' => true,
    ];

    private static $has_many = [
        'Children'                => self::class . '.Parent',
        'Terms'                   => self::class . '.Type',
        'DataObjectTaxonomyTerms' => DataObjectTaxonomyTerm::class,
    ];

    private static $has_one = [
        'Parent' => self::class,
        'Type'   => self::class, //it is supposed to be the root node of the taxonomy family tree
    ];

    private static $many_many = [
        'RequiredTypes' => self::class,
    ];

    private static $belongs_many_many = [
        'AsRequiredTypeBy' => self::class . '.RequiredTypes',
    ];

    private static $defaults = [
        'DisplayPreference'        => true,
        'RequiredTypesInheritRoot' => true,
    ];

    private static $field_labels = [
        'Title'                    => 'Display name singular',
        'TitlePlural'              => 'Display name plural',
        'SingleSelect'             => 'Single select?',
        'DisplayPreference'        => 'Show to end-users?',
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

    private static $casting = [
        'NameAsATag'              => 'HTMLFragment',
        'RequiredTypeNames'       => 'HTMLFragment',
        'DescriptionLimit15Words' => 'Text',
        'TagName'                 => 'HTMLFragment',
    ];

    private static $default_sort = 'Sort';

    private static $summary_fields = [
        'NameAsATag'                 => 'Name',
        'DescriptionLimit15Words'    => 'Description',
        'TypeNameWithFlagAttributes' => 'Type',
        'Title'                      => 'Singular',
        'TitlePlural'                => 'Plural',
        'RequiredTypeNames'          => 'Requires',
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

        // Remove reversed belongs_many_many GridField and its tab "AsRequiredTypeBy" not to confuse CMS users
        $fields->removeByName('AsRequiredTypeBy');

        // Remove the many_many_through data object DataObjectTaxonomyTerms
        $fields->removeByName('DataObjectTaxonomyTerms');

        // Begin to use _t(), first disable 'missing_default_warning', which allows _t() to be called without default
        i18n::config()->update('missing_default_warning', false);

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

        // Tweak DisplayPreference
        if ($this->ParentID) {
            $fields->removeByName('DisplayPreference');
        } else {
            // Field DisplayPreference
            $displayDescription = _t(static::class . '.DisplayPreference_Description');
            $fields->replaceField(
                'DisplayPreference',
                $displayPreferenceField = OptionsetField::create(
                    'DisplayPreference',
                    'Show to end-user?',
                    [1 => 'Yes', 0 => 'No']
                )
            );

            $displayPreferenceField->setDescription($displayDescription);
        }

        // Tweak SingleSelect
        if ($this->ParentID) {
            $fields->removeByName('SingleSelect');
        } else {
            // Convert SingleSelect to be read-only and give different description according to its logic
            $readonly                = false;
            $foundObjectsBeingTagged = $this->findOneDataObjectTagged();
            $objectName              = 'data object';
            $fields->replaceField(
                'SingleSelect',
                $singleSelectField = OptionsetField::create(
                    'SingleSelect',
                    'Single select?',
                    [1 => 'Yes', 0 => 'No']
                )
            );

            if (!empty($foundObjectsBeingTagged)) {
                $readonly = true;
                $fields->replaceField(
                    'SingleSelect',
                    $singleSelectField = $singleSelectField->performReadonlyTransformation()
                );
                $class      = $foundObjectsBeingTagged['objectType'];
                $objectName = mb_strtolower(singleton($class)->singular_name());
            }

            $checked                 = $this->SingleSelect;
            $singleSelectDescription = _t(
                static::class
                . '.SingleSelect' . ($checked ? '_Checked' : '') . ($readonly ? '_Readonly' : '') . '_Description'
            );

            $singleSelectField->setDescription(sprintf($singleSelectDescription, $objectName));
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

            // Set NameAsATag column casting
            $childrenGrid->getConfig()->getComponentByType(GridFieldDataColumns::class)
                ->setFieldCasting(['NameAsATag' => 'HTMLFragment->RAW']);
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
                    ->getComponentByType(GridFieldDataColumns::class)
                    // Cast NameAsATag to its HTML present
                    ->setFieldCasting(['NameAsATag' => 'HTMLFragment->RAW']);
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
            if (isset($displayColumns['RequiredTypeNames'])) {
                unset($displayColumns['RequiredTypeNames']);
            }
            $dataColumns->setDisplayFields($displayColumns)
                // Cast NameAsATag to its HTML present
                ->setFieldCasting(['NameAsATag' => 'HTMLFragment->RAW']);

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


        if (!$this->ParentID && isset($termsTab)) {
            // reorder Tabs so Terms tab appears at the last position
            $fields->removeFieldFromTab('Root', ['Terms']);
            $fields->fieldByName('Root')->push($termsTab);
        }

        return $fields;
    }


    /**
     * When any TaxonomyTerms from this type is used to tag any DataObjects, return the terms found being used and
     * the DataObject ClassName, the function will return when it found the first instance, rather then going through
     * all possible sub classes. It will return an exmpty array if no terms found to be tagging to any DataObjects
     *
     * This function is called on a root Term currently
     *
     * @return array
     */
    private function findOneDataObjectTagged()
    {
        // As $this is a root term, we could use $this->Terms() to get all terms from same tree here, but to make the
        // function could be use by any non-root terms we use $this->Type()->Terms();
        $terms = $this->Type()->Terms();

        if ($terms && $terms->exists()) {
            $termIDs = implode(',', $terms->column('ID'));

            $tableName = Config::inst()->get(DataObjectTaxonomyTerm::class, 'table_name');

            if (ClassInfo::hasTable($tableName)) {
                $sql = sprintf(
                    'SELECT count(1) as NumRecords, OwnerObjectClass FROM %s WHERE JointObjectID IN (%s)',
                    $tableName,
                    $termIDs
                );

                $queryResult = DB::query($sql)->map();
                $numRecords  = array_key_first($queryResult);
                $class       = $queryResult[$numRecords];

                if ($numRecords > 0) {
                    return [
                        'objectType' => $class,
                        'terms'      => implode('<br />', $terms->column('Name')),
                    ];
                }
            }
        }

        return [];
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
     * Set the "type" relationship for children to that of the parent (recursively)
     * This guarantees that all nodes in this 'family' branch have the same reference to its root TaxonomyTerm
     *
     * {@inheritDoc}
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Write the current term's type to all children
        foreach ($this->Children() as $term) {
            /** @var TaxonomyTerm $term */
            $term->TypeID            = $this->TypeID;
            $term->SingleSelect      = $this->SingleSelect;
            $term->DisplayPreference = $this->DisplayPreference;
            $term->write();
        }
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

        // Write the parent's type to the current term
        if ($this->Parent()->exists()) {
            $this->TypeID            = $this->Parent()->TypeID;
            $this->SingleSelect      = $this->Parent()->SingleSelect;
            $this->DisplayPreference = $this->Parent()->DisplayPreference;
        } else { // A root node, populate TypeID with its own ID
            if ($this->ID) {
                $this->TypeID = $this->ID;
            }
        }

        if ($this->Name) {
            if (!$this->Title) {
                $this->Title = $this->Name;
            }

            if (!$this->TitlePlural) {
                $this->TitlePlural = PluralGenerator::generate($this->Name);
            }

            if (!$this->URLSegment) {
                $this->URLSegment = URLSegmentGenerator::generate(
                    $this->Name,
                    static::class,
                    $this->ID
                );
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
     * Return formats:
     * 1. "parentName > childName > grantChildName" if it is non-root term
     * 2. "myName" if it is root term
     *
     * @param string $separator
     * @return string
     */
    public function getHierarchyDisplay($separator = ' ▸ ')
    {
        $crumbs = [];

        $ancestors = array_reverse($this->getAncestors()->toArray());
        foreach ($ancestors as $ancestor) {
            $crumbs[] = $ancestor->Name;
        }

        $crumbs[] = $this->Name;

        return implode($separator, $crumbs);
    }


    /**
     * @return string
     */
    public function getDescriptionLimit15Words()
    {
        $text = DBText::create('Description');
        $text->setValue($this->Description);

        return $text->LimitWordCount(15);
    }


    /**
     * @param $type
     * @param null $term
     * @return DataList || ArrayList
     */
    public static function getByType($type, $term = null)
    {
        $terms = self::get();

        $typeID = is_object($type) && $type->exists() ? $type->ID : (is_numeric($type) ? $type : null);
        if ($typeID) {
            $terms = $terms->filter('TypeID', $typeID);
        }

        $termID = is_object($term) && $term->exists() ? $term->ID : (is_numeric($term) ? $term : null);

        if ($termID) {
            $termList = ArrayList::create();
            foreach ($terms as $termItem) {
                if ($termItem->getTaxonomy()->ID === $termID) {
                    $termList->add($termItem);
                }
            }

            return $termList;
        }

        return $terms;
    }


    /**
     * The function is to provide this taxonomy term presented like a 'tag' which is wrapped by special HTML tags with
     * some special classes
     *
     * @return string
     */
    public function NameAsATag()
    {
        return '<span class="Select--multi"><span class="Select-value-label Select-value">'
            . $this->Name . '</span></span>';
    }


    /**
     * The function is to provide this taxonomy term presented in a well formatted way:
     * 1. The name are being formatted like a 'tag' which is wrapped by special HTML tags with some special classes
     * 2. It's other information is also added to this presentation but as tooltips, since the presentation is
     *    to be shown as GridField columns' value, i.e. inside a table cell, the space holding this presentation is
     *    limited.
     *
     * @return string
     */
    public function getTagName()
    {
        $lineBreaks = '<br><br>';
        $bTagOpen   = '<b>';
        $bTagClose  = '</b>';

        $authoringDefinition = $this->AuthorDefinition ? $this->AuthorDefinition : null;
        $hierarchy           = $this->getHierarchyDisplay();

        $typeInfo = $this->TypeNameWithFlagAttributes();

        // We want two line breaks if the term's AuthorDefinition is defined
        $tooltipText = '';
        if ($authoringDefinition) {
            $tooltipText .= $authoringDefinition . $lineBreaks;
        }

        // Show the term's hierarchy information
        $tooltipText .= $bTagOpen . 'Taxonomy' . $bTagClose . ': ' . $hierarchy;

        // Show the term's information on 'Display name singular'
        if ($this->Title) {
            $tooltipText .= $lineBreaks . $bTagOpen . 'Singular' . $bTagClose . ': ' . $this->Title;
        }

        // Show the term's information on 'Display name plural'
        if ($this->TitlePlural) {
            $tooltipText .= $lineBreaks . $bTagOpen . 'Plural' . $bTagClose . ': ' . $this->TitlePlural;
        }

        // Show the term's information on 'Type' and Type's logic attribute, i.e. which root term it belongs to,
        // it is Multi or Single
        if ($typeInfo) {
            $tooltipText .= $lineBreaks . $bTagOpen . 'Type' . $bTagClose . ': ' . $typeInfo;
        }

        // Overall required types, i.e. computed from tag's required types, the root term's required types
        // using the logic setting flag 'RequiredTypesInheritRoot'
        if ($types = $this->RequiredTypesOverall()) {
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

        return sprintf($tagNameFormat, $this->Name, $tooltipText);
    }


    /**
     * This is to render the type's Name with its SingleSelect and DisplayPreference attributes, used to give more
     * information in a GridField column for showing TaxonomyTerm
     *
     * @return string|null
     */
    public function TypeNameWithFlagAttributes()
    {
        if ($this->exists() && $this->Type()->exists()) {
            $type  = $this->Type();
            $title = $type->Name;

            return $title
                . ' ('
                . ($type->SingleSelect ? 'Single' : 'Multi')
                . ($type->DisplayPreference ? '; Shown' : '; Hidden')
                . ')';
        }

        return null;
    }


    /**
     * As RequiredTypesInheritRoot flag and RequiredTypes many_many relation exists in  any level of term nodes in a
     * hierarchical tree, the RequiredTypes can be customised per term node. This function is 'calculated' result as
     * RequiredTypes for each term node, the logic is:
     * If RequiredTypesInheritRoot is true (default value), the conjunction of both root's RequiredTypes and the current
     * node's RequiredTypes; if it is false, ignore the root's RequiredTypes.
     *
     * @return DataList
     */
    public function RequiredTypesOverall()
    {
        if (!$this->RequiredTypesInheritRoot) {
            return $this->RequiredTypes();
        }
        $rootRequiredTypesIDs = $this->Type()->RequiredTypes()->column('ID');
        $localRequiredTypes   = $this->RequiredTypes()->column('ID');
        if (!empty($rootRequiredTypesIDs) || !empty($localRequiredTypes)) {
            return self::get()
                    ->filterAny('ID', array_merge($rootRequiredTypesIDs, $localRequiredTypes));
        }
    }


    /**
     * The function is used to provide column 'Required types` in GridField a nice presentation of required types per
     * TaxonomyTerm
     *
     * @return DBHTMLText
     */
    public function RequiredTypeNames()
    {
        $names = [];
        if ($types = $this->RequiredTypesOverall()) {
            foreach ($this->RequiredTypesOverall() as $type) {
                $names[] = $type->Name;
            }
        }

        // Make the output as DBHTMLText to achieve better style by using HTMLFragment->RAW column casting
        $namesHTML = implode('<br />', $names);
        $output    = DBHTMLText::create();
        $output->setValue($namesHTML);

        return $output;
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
}
