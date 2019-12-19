<?php

namespace Chrometoaster\AdvancedTaxonomies\Forms;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\View\ArrayData;

/**
 * A button that contains a link to additional information
 */
class GridFieldInfoLink implements GridField_HTMLProvider
{
    /**
     * Gridfield fragment
     *
     * @var string
     */
    protected $targetFragment;

    /**
     * Destination URL
     *
     * @var string
     */
    protected $url;

    /**
     * Button label
     *
     * @var string
     */
    protected $caption;


    /**
     * GridFieldInfoLink constructor.
     *
     * @param string $targetFragment
     * @param string $url
     * @param string $label
     */
    public function __construct(string $targetFragment, string $url, string $label)
    {
        $this->targetFragment = $targetFragment;
        $this->url            = $url;
        $this->label          = $label;
    }


    /**
     * @param GridField $gridField
     * @return array
     */
    public function getHTMLFragments($gridField)
    {
        $fragment = ArrayData::create([
            'Url'   => $this->url,
            'Label' => $this->label,
        ])->renderWith(self::class);

        return [$this->targetFragment => $fragment];
    }
}
