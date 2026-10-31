<?php

namespace Chrometoaster\AdvancedTaxonomies\Tests\Extensions;

use Chrometoaster\AdvancedTaxonomies\Extensions\CMSMainTaxonomyExtension;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\List\ArrayList;

/**
 * The Tags column reaches the Pages list view whatever CMSMain has named its grid field.
 *
 * CMS 6's CMSMain::ListViewForm() names the grid 'Record'; CMS 4 and 5 named it 'Page'. Looking the old name up
 * alone returned null on CMS 6 and the list view answered 500 ("Call to a member function getConfig() on null").
 */
class CMSMainTaxonomyExtensionTest extends SapphireTest
{
    protected $usesDatabase = false;


    public function testTheTagsColumnIsAddedToTheGridNamedRecord(): void
    {
        $form = $this->listViewForm('Record');

        $this->extension()->updateListView($form);

        $this->assertSame('Tags', $this->displayFields($form, 'Record')['getTagNamesWithExtraInfo'] ?? null);
    }


    public function testTheTagsColumnIsAddedToTheGridNamedPage(): void
    {
        $form = $this->listViewForm('Page');

        $this->extension()->updateListView($form);

        $this->assertSame('Tags', $this->displayFields($form, 'Page')['getTagNamesWithExtraInfo'] ?? null);
    }


    public function testTheExistingColumnsAreKept(): void
    {
        $form   = $this->listViewForm('Record');
        $before = $this->displayFields($form, 'Record');

        $this->extension()->updateListView($form);

        $after = $this->displayFields($form, 'Record');
        foreach ($before as $name => $title) {
            $this->assertSame($title, $after[$name] ?? null, "The $name column is kept");
        }
    }


    public function testAFormWithoutAGridFieldIsLeftAlone(): void
    {
        $form = Form::create(CMSMain::create(), 'ListViewForm', FieldList::create(TextField::create('Search')), FieldList::create());

        $this->extension()->updateListView($form);

        $this->assertNull($form->Fields()->dataFieldByName('Record'));
    }


    private function extension(): CMSMainTaxonomyExtension
    {
        $extension = new CMSMainTaxonomyExtension();
        $extension->setOwner(CMSMain::create());

        return $extension;
    }


    private function listViewForm(string $gridName): Form
    {
        $config = GridFieldConfig_Base::create();
        $grid   = GridField::create($gridName, ucfirst($gridName) . 's', ArrayList::create(), $config);
        // The data-columns component reads the model's summary fields, as CMSMain's grid over SiteTree does
        $grid->setModelClass(SiteTree::class);

        return Form::create(CMSMain::create(), 'ListViewForm', FieldList::create($grid), FieldList::create());
    }


    /**
     * @return array<string, string>
     */
    private function displayFields(Form $form, string $gridName): array
    {
        /** @var GridField $grid */
        $grid = $form->Fields()->dataFieldByName($gridName);
        /** @var GridFieldDataColumns $columns */
        $columns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);

        return $columns->getDisplayFields($grid);
    }
}
