<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;

class AssociativeRelationType extends DataObject
{
    private static $table_name = 'AT_AssociativeRelationType';

    private static $singular_name = 'Associative relation type';

    private static $plural_name = 'Associative relation types';

    /**
     * This should be multiple dimension array with each entries as a single array of string, string (optional),
     * boolean (1 or 0, optional), eg.
     *   [
     *      ['has source', 'is source of', 1],
     *      ['is related to', '', 0],
     *      ['is produced by', 'has producer']
     *      ['is part of']
     *   ]
     *
     * @var array
     */
    private static $default_relation_types = [];

    /**
     * @var string[]
     */
    private static $db = [
        'LabelLeft'   => 'Varchar',
        'LabelRight'  => 'Varchar',
        'IsSymmetric' => 'Boolean(1)',
    ];

    private static $has_many = [
        'RelationInstances' => AssociativeRelation::class,
    ];

    private static $summary_fields = [
        'ID'               => 'ID',
        'LabelLeft'        => 'Label left',
        'LabelRight'       => 'Label right',
        'IsSymmetric.Nice' => 'Symmetric relation type',
    ];


    /**
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Remove AssociativeRelation Tab and GridFiled with the name AssociativeRelations
        $fields->removeByName(['AssociativeRelations', 'RelationInstances']);

        return $fields;
    }


    /**
     * @return RequiredFields
     */
    public function getCMSValidator(): RequiredFields
    {
        return RequiredFields::create(['LabelLeft']);
    }


    /**
     * Don't allow a default AssociativeRelationType instance to be deleted.
     *
     * @param mixed $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        return !$this->isDefaultAssociativeRelationType() && parent::canDelete($member);
    }


    /**
     * Get the default AssociativeRelationType records configured under AssociativeRelationType.default_relation_types
     *
     * @return array
     */
    protected function getDefaultAssociativeRelationType(): array
    {
        return $this->config()->get('default_relation_types') ?: [];
    }


    /**
     * Check if this AssociativeRelationType appears in the default_relation_types config
     *
     * @return bool
     */
    public function isDefaultAssociativeRelationType(): bool
    {
        $defaultAssociativeRelationTypes = $this->getDefaultAssociativeRelationType();

        if (count($defaultAssociativeRelationTypes)) {
            foreach ($defaultAssociativeRelationTypes as $defaultAssociativeRelationType) {
                // Check starts from most right to most left of each entry
                if (!isset($defaultAssociativeRelationType[2])) {
                    if (!isset($defaultAssociativeRelationType[1])) {
                        if ($this->LabelLeft === $defaultAssociativeRelationType[0]) {
                            return true;
                        }
                    } else {
                        if ($this->LabelRight === $defaultAssociativeRelationType[1]
                            && $this->LabelLeft === $defaultAssociativeRelationType[0]
                        ) {
                            return true;
                        }
                    }
                } else {
                    if (isset($defaultAssociativeRelationType[1]) && $defaultAssociativeRelationType[1]) {
                        if ($this->LabelRight === $defaultAssociativeRelationType[1]
                            && $this->LabelLeft === $defaultAssociativeRelationType[0]
                            && (int) $this->IsSymmetric === (int) $defaultAssociativeRelationType[2]
                        ) {
                            return true;
                        }
                    } else {
                        if ($this->LabelLeft === $defaultAssociativeRelationType[0]
                            && (int) $this->IsSymmetric === (int) $defaultAssociativeRelationType[2]
                        ) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }


    /**
     * Set up default records based on the yaml config AssociativeRelationType.default_relation_types
     *
     * @throws ValidationException
     */
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        foreach ($this->getDefaultAssociativeRelationType() as $defaultAssociativeRelationType) {
            $filter = [
                'LabelLeft'   => $defaultAssociativeRelationType[0],
                'IsSymmetric' => $defaultAssociativeRelationType[2] ?? 1,
            ];

            if (isset($defaultAssociativeRelationType[1]) && $defaultAssociativeRelationType[1]) {
                $filter['LabelRight'] = $defaultAssociativeRelationType[1];
            }

            $existingRecord = self::get()->filter($filter)->first();

            if (!$existingRecord) {
                $relationType              = self::create();
                $relationType->LabelLeft   = $defaultAssociativeRelationType[0];
                $relationType->LabelRight  = $defaultAssociativeRelationType[1] ?? '';
                $relationType->IsSymmetric = $defaultAssociativeRelationType[2] ?? 1;
                $relationType->write();

                DB::alteration_message(
                    sprintf(
                        "Associative type '%s:%s:%s' created",
                        $defaultAssociativeRelationType[0],
                        $defaultAssociativeRelationType[1] ?? '',
                        (string) $defaultAssociativeRelationType[2] ?? 1
                    ),
                    'created'
                );
            }
        }
    }


    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->LabelRight ? sprintf('%s ⟷ %s', $this->LabelLeft, $this->LabelRight) : $this->LabelLeft;
    }
}
