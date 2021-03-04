<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use SilverStripe\Forms\FieldList;

class EquivalentTerm extends AlternativeTerm
{
    private static $table_name = 'AT_EquivalentTerm';

    private static $singular_name = 'Equivalent term';

    private static $plural_name = 'Equivalent terms';

    /**
     * This is inverse relation of has_many from TaxonomyTerm
     *
     * @var string[]
     */
    private static $has_one = [
        'PreferredTerm' => TaxonomyTerm::class,
    ];

    /**
     * @var string[]
     */
    private static $db = [
        'EquivalentType' => "Enum('acronym, abbreviation, synonym, concatenation, shortened version, extended version, regional variation, lexical variation, alternative spelling, colloquialism, slang, jargon, shorthand', 'synonym')",
    ];

    /**
     * @var string[]
     */
    private static $summary_fields = [
        'EquivalentType',
    ];


    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Remove PreferredTerm
        $fields->removeByName('PreferredTermID');

        // Move EquivalentType right after Name field
        $fields->insertAfter('Name', $fields->dataFieldByName('EquivalentType'));

        return $fields;
    }


    /**
     * @return string
     */
    public function getAltTermTitle(): string
    {
        return parent::getAltTermTitle() . ($this->EquivalentType ? ' (' . $this->EquivalentType . ')' : '');
    }
}
