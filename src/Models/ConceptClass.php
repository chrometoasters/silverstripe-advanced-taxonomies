<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

class ConceptClass extends BaseObject
{
    private static $table_name = 'AT_ConceptClass';

    private static $singular_name = 'Concept class';

    private static $plural_name = 'Concept classes';

    /**
     * @var array
     */
    private static $default_concept_classes = [];

    /**
     * @var bool
     */
    private static $publish_default_concept_classes = true;


    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        if ($this->isInDB()) {
            $this->i18nDisableWarning();

            // Add info text about assigning terms to concept classes
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create('Info', '<p class="message notice">' . $this->_t('Info') . '</p>')
            );

            $this->i18nRestoreWarningConfig();
        }

        return $fields;
    }


    /**
     * Get term IDs where this ConceptClass is assigned as primary (either directly or through the type)
     *
     * @return array
     */
    private function getTermIDsWherePrimary(): array
    {
        $termIDs = [];

        if ($this->exists()) {
            // Get all terms where this concept class is assigned as primary
            $termsWherePrimary = TaxonomyTerm::get()->filter(['PrimaryConceptClassID' => $this->ID]);
            $termIDs[]         = $termsWherePrimary->column('ID');

            // Get all terms where this concept class is assigned as primary to their type
            $typeIDsWherePrimary = (clone $termsWherePrimary)->filter(['ParentID' => 0])->column('ID');
            if (count($typeIDsWherePrimary)) {
                $termsWherePrimaryForType = TaxonomyTerm::get()
                    ->filter(['ParentID:not' => 0, 'PrimaryConceptClassID' => 0, 'TypeID' => $typeIDsWherePrimary]);

                $termIDs[] = $termsWherePrimaryForType->column('ID');
            }
        }

        return array_unique(array_merge(...$termIDs));
    }


    /**
     * Get term IDs where this ConceptClass is assigned as OtherConceptClasses
     *
     * @return array
     */
    private function getTermIDsWhereOther(): array
    {
        if ($this->exists()) {
            return TaxonomyTerm::get()->filter(['OtherConceptClasses.ID' => $this->ID])->column('ID');
        }

        return [];
    }


    /**
     * Get terms where this concept class is the primary concept class assigned either
     * directly or through the taxonomy type.
     *
     * @return SS_List
     */
    public function getTerms(): SS_List
    {
        $termIDs = $this->getTermIDsWherePrimary();
        if (count($termIDs)) {
            return TaxonomyTerm::get()->byIDs($termIDs);
        }

        return ArrayList::create();
    }


    /**
     * Get all terms for this concept class
     *
     * This returns all terms where the concept class is either assigned as the primary concept class
     * (both directly or through the taxonomy type), or as a secondary (other) concept class.
     *
     * @return SS_List
     */
    public function getAllTerms(): SS_List
    {
        if ($this->exists()) {
            $termIDsWherePrimary = $this->getTermIDsWherePrimary();
            $termIDsWhereOther   = $this->getTermIDsWhereOther();

            $allIDs = array_unique(array_merge($termIDsWherePrimary, $termIDsWhereOther));
            if (count($allIDs)) {
                return TaxonomyTerm::get()->byIDs($allIDs);
            }
        }

        return ArrayList::create();
    }


    /**
     * Don't allow a default ConceptClass instance to be deleted.
     *
     * @param mixed $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        return !$this->isDefaultConceptClass() && parent::canDelete($member);
    }


    /**
     * Get the default concept class names configured under ConceptClass.default_concept_classes config
     *
     * @return array
     */
    protected function getDefaultConceptClasses(): array
    {
        return $this->config()->get('default_concept_classes') ?: [];
    }


    /**
     * Check if this concept class appears in the default_concept_classes config
     *
     * @return bool
     */
    public function isDefaultConceptClass(): bool
    {
        $defaults = $this->getDefaultConceptClasses();

        return in_array($this->Name, $defaults);
    }


    /**
     * Set up default records based on the yaml config ConceptClass.default_concept_classes
     *
     * @throws ValidationException
     */
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        foreach ($this->getDefaultConceptClasses() as $name) {
            if ($name) {
                $existingRecord = self::get()
                    ->filter('Name', $name)
                    ->first();

                if (!$existingRecord) {
                    /** @var DataObject|Versioned $conceptClass */
                    $conceptClass       = self::create();
                    $conceptClass->Name = $name;
                    $conceptClass->write();

                    if ($this->config()->get('publish_default_concept_classes')) {
                        $conceptClass->publishSingle();
                    }

                    DB::alteration_message("Concept class '$name' created", 'created');
                }
            }
        }
    }
}
