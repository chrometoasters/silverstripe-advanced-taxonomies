<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\i18n\i18n;

class LanguageTerm extends AlternativeTerm
{
    private static $table_name = 'AT_LanguageTerm';

    private static $singular_name = 'Language term';

    private static $plural_name = 'Language terms';

    /**
     * @var string[]
     */
    private static $db = [
        'Locale'    => 'Varchar(10)', // Store a locale code
        'IsPrimary' => 'Boolean(0)',
    ];

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
    private static $summary_fields = [
        'Language'       => 'Language',
        'Locale'         => 'Locale',
        'IsPrimary.Nice' => 'Is primary?',
    ];


    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->replaceField(
            'Locale',
            $localeField = DropdownField::create('Locale', 'Locale', i18n::getData()->getLocales())
        );

        // Remove PreferredTerm
        $fields->removeByName('PreferredTermID');

        // Move Locale and IsPrimary right after Name field
        $fields->insertAfter('Name', $localeField);
        $fields->insertAfter('Name', $fields->dataFieldByName('IsPrimary'));

        return $fields;
    }


    /**
     * @return string
     */
    public function getLanguage(): string
    {
        if ($this->Locale) {
            $locales = i18n::getData()->getLocales();
            if (array_key_exists($this->Locale, $locales)) {
                return $locales[$this->Locale];
            }
        }

        return '';
    }


    /**
     * @return string
     */
    public function getAltTermTitle(): string
    {
        $language = $this->getLanguage();

        return parent::getAltTermTitle() . ($language ? ' (' . $language . ')' : '');
    }
}
