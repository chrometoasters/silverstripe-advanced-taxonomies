<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use Chrometoaster\AdvancedTaxonomies\Generators\PluralGenerator;
use SilverStripe\Forms\FieldList;

/**
 * Base term that other term classes can expand on
 */
class BaseTerm extends BaseObject
{
    private static $table_name = 'AT_BaseTerm';

    private static $singular_name = 'Base term';

    private static $plural_name = 'Base terms';

    /**
     * @var string[]
     */
    private static $db = [
        'Title'            => 'Varchar(255)',
        'TitlePlural'      => 'Varchar(255)',
        'TitleCustom'      => 'Varchar(255)', // used from TaxonomyTerm, but data-wise belongs here
        'Description'      => 'Text',
        'AuthorDefinition' => 'Text',
        'PublicDefinition' => 'Text',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Title' => true,
    ];

    /**
     * @var string[]
     */
    private static $summary_fields = [
        'Name'        => 'Name',
        'Title'       => 'Singular',
        'TitlePlural' => 'Plural',
    ];

    /**
     * @var string[]
     */
    private static $field_labels = [
        'Title'       => 'Display name singular',
        'TitlePlural' => 'Display name plural',
    ];

    private static $searchable_fields = [
        'Name'        => ['filter' => 'PartialMatchFilter'],
        'Title'       => ['filter' => 'PartialMatchFilter'],
        'TitlePlural' => ['filter' => 'PartialMatchFilter'],
    ];


    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->i18nDisableWarning();

        $fields = parent::getCMSFields();
        $fields->removeByName(['TitleCustom']); // field is used from TaxonomyTerm level only

        // Add description to fields
        $fields->datafieldByName('Title')->setDescription($this->_t('Title'));
        $fields->datafieldByName('TitlePlural')->setDescription($this->_t('TitlePlural'));
        $fields->datafieldByName('Description')->setDescription($this->_t('Description'));
        $fields->datafieldByName('AuthorDefinition')->setDescription($this->_t('AuthorDefinition'));
        $fields->datafieldByName('PublicDefinition')->setDescription($this->_t('PublicDefinition'));

        $this->i18nRestoreWarningConfig();

        return $fields;
    }


    /**
     * {@inheritDoc}
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->Title && $this->Name) {
            $this->Title = $this->Name;
        }

        if (!$this->TitlePlural && $this->Title) {
            $this->TitlePlural = PluralGenerator::generate($this->Title);
        }
    }
}
