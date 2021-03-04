<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

use Chrometoaster\AdvancedTaxonomies\Generators\URLSegmentGenerator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Versioned\Versioned;

/**
 * Base object that other classes can expand on, such as terms, concept classes etc.
 */
class BaseObject extends DataObject implements PermissionProvider
{
    private static $table_name = 'AT_BaseObject';

    private static $singular_name = 'AT base object';

    private static $plural_name = 'AT base objects';

    /**
     * @var string[]
     */
    private static $db = [
        'Name'       => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'Sort'       => 'Int',
    ];

    private static $default_sort = '"Sort" ASC';

    /**
     * @var array
     */
    private static $indexes = [
        'Name'       => true,
        'URLSegment' => true,
    ];

    private static $extensions = [
        Versioned::class,
    ];

    /**
     * @var string[]
     */
    private static $summary_fields = [
        'Name'       => 'Name',
        'URLSegment' => 'URL Segment',
    ];

    private static $searchable_fields = [
        'Name' => ['filter' => 'PartialMatchFilter'],
    ];

    /**
     * @var bool
     */
    private $i18nMissingDefaultWarning = true;


    /**
     * Get a text from the translations system for a given identifier
     *
     * Looks up the string for the extending/current class and uses this class strings as a fallback.
     * Supports sprintf evaluation of extra params to replace in the text.
     *
     * @param string $identifier
     * @return string
     */
    protected function _t(string $identifier, ...$params): string
    {
        $text = trim(trim(_t(static::class . '.' . $identifier)) ?: _t(self::class . '.' . $identifier));

        return sprintf($text, ...$params);
    }


    /**
     * Disable warnings for missing default text when using _t()
     */
    protected function i18nDisableWarning(): void
    {
        $this->i18nMissingDefaultWarning = i18n::config()->get('missing_default_warning');
        i18n::config()->update('missing_default_warning', false);
    }


    /**
     * Restore teh config for missing default text warnings
     */
    protected function i18nRestoreWarningConfig(): void
    {
        i18n::config()->update('missing_default_warning', $this->i18nMissingDefaultWarning);
    }


    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->i18nDisableWarning();

        $fields = parent::getCMSFields();
        $fields->removeByName('Sort'); // using orderable rows in gridfields

        // Add description to fields
        $fields->datafieldByName('Name')->setDescription($this->_t('Name'));
        $fields->dataFieldByName('URLSegment')->setDescription($this->_t('URLSegment'));

        $this->i18nRestoreWarningConfig();

        return $fields;
    }


    /**
     * @return RequiredFields
     */
    public function getCMSValidator()
    {
        return RequiredFields::create(['Name']);
    }


    /**
     * Find the object/term by a url-like path, e.g. information-type/newsletter/highlights.
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

            return self::getBySlug($slug, $term->ID);
        }

        return null;
    }


    /**
     * {@inheritDoc}
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->URLSegment) {
            if ($this->Name) {
                $this->URLSegment = URLSegmentGenerator::generate(
                    $this->Name,
                    static::class,
                    $this->ID
                );
            }
        } elseif ($this->isChanged('URLSegment', DataObject::CHANGE_VALUE)) {
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

        return Permission::check('ADVANCED_TAXONOMIES_EDIT');
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

        return Permission::check('ADVANCED_TAXONOMIES_DELETE');
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

        return Permission::check('ADVANCED_TAXONOMIES_CREATE');
    }


    /**
     * @return array
     */
    public function providePermissions()
    {
        $category = _t(self::class . '.Category', 'Advanced taxonomies');

        return [
            'ADVANCED_TAXONOMIES_CREATE' => [
                'name' => _t(
                    self::class . '.CreatePermissionLabel',
                    'Create'
                ),
                'category' => $category,
            ],
            'ADVANCED_TAXONOMIES_EDIT' => [
                'name' => _t(
                    self::class . '.EditPermissionLabel',
                    'Edit'
                ),
                'category' => $category,
            ],
            'ADVANCED_TAXONOMIES_DELETE' => [
                'name' => _t(
                    self::class . '.DeletePermissionLabel',
                    'Delete'
                ),
                'category' => $category,
            ],
        ];
    }
}
