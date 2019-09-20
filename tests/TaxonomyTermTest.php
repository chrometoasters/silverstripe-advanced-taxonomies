<?php

namespace Chrometoaster\AdvancedTaxonomies\Tests;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use Chrometoaster\AdvancedTaxonomies\Tests\Models\OwnerObject;
use Chrometoaster\AdvancedTaxonomies\Validators\ModelTagLogicValidator;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

class TaxonomyTermTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        OwnerObject::class,
    ];

    protected static $fixture_file = 'TaxonomyTerm.yml';


    /**
     * Test a root TaxonomyTerm is acted as a 'type' for the taxonomy tree rooted from it. Its foreign key TypeID, flags
     * SingleSelect and DisplayPreference are correctly spread to the non-root terms
     */
    public function testRootTermAsType()
    {
        $rootTerm3         = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm3');
        $childTerm31       = $this->objFromFixture(TaxonomyTerm::class, 'level1Term3p1');
        $grandChildTerm322 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term3p2p2');


        // Test TypeID are shared on the entire Taxonomy tree
        $typeID = $rootTerm3->ID;
        $this->assertEquals(
            $rootTerm3->TypeID,
            $typeID,
            'A root term has TypeID as its own ID'
        );
        $this->assertEquals(
            $childTerm31->TypeID,
            $typeID,
            'A non-root term has TypeID populated from its root'
        );
        $this->assertEquals(
            $grandChildTerm322->TypeID,
            $typeID,
            'A leaf term has TypeID populated from its root'
        );


        // Test a term knows the entire taxonomy tree through its TypeID
        $this->assertEquals(
            $grandChildTerm322->Type()->Terms()->count(),
            7,
            'A leaf term can access entire terms in the same taxonomy tree by its TypeID'
        );


        // Test SingleSelect and DisplayPreference are inherited, using default values
        $this->assertEquals(
            $rootTerm3->SingleSelect,
            0,
            'Root term\'s SingleSelect is populated by its default'
        );
        $this->assertEquals(
            $childTerm31->SingleSelect,
            0,
            'Child term\'s SingleSelect inherits from the root term'
        );
        $this->assertEquals(
            $grandChildTerm322->SingleSelect,
            0,
            'Deeply nested term\'s SingleSelect inherits from the root term'
        );
        $this->assertEquals(
            $rootTerm3->DisplayPreference,
            1,
            'Root term\'s DisplayPreference is populated by its default'
        );
        $this->assertEquals(
            $childTerm31->DisplayPreference,
            1,
            'Child term\'s DisplayPreference inherits from the root term'
        );
        $this->assertEquals(
            $grandChildTerm322->DisplayPreference,
            1,
            'Deeply nested term\'s SingleSelect inherits from the root term'
        );


        // Test SingleSelect and DisplayPreference are inherited, using negation to the default values
        $rootTerm4   = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm4');
        $leafTerm422 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term4p2p2');
        $this->assertEquals(
            $rootTerm4->SingleSelect,
            1,
            'Root term\'s SingleSelect is populated as "true", i.e. negation of default value'
        );
        $this->assertEquals(
            $leafTerm422->SingleSelect,
            1,
            'Deeply nested term\'s SingleSelect inherits from the root term no matter what the value is'
        );
        $this->assertEquals(
            $rootTerm4->DisplayPreference,
            0,
            'Root term\'s DisplayPreference is populated as "false", i.e. negation of default value'
        );
        $this->assertEquals(
            $leafTerm422->DisplayPreference,
            0,
            'Deeply nested term\'s DisplayPreference inherits from the root term no matter what the value is'
        );
    }


    /**
     * Test a when a root TaxonomyTerm is altered, the altered flag attributes SingleSelect and DisplayPreference are
     * correctly spread to the non-root terms in the same taxonomy tree
     * @throws ValidationException
     */
    public function testAttributesKeptInheritedWhenRootTermAltered()
    {
        $rootTerm3         = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm3');
        $grandChildTerm322 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term3p2p2');

        // Test SingleSelect and DisplayPreference are inherited, using default values
        $this->assertEquals(
            $grandChildTerm322->SingleSelect,
            0,
            'Deeply nested term\'s SingleSelect initial from the root term\'s default value'
        );
        $this->assertEquals(
            $grandChildTerm322->DisplayPreference,
            1,
            'Deeply nested term\'s DisplayPreference initial from the root term\'s default value'
        );

        // Now change root attributes SingleSelect and DisplayPreference
        $rootTerm3->SingleSelect      = true;
        $rootTerm3->DisplayPreference = false;
        $rootTerm3->write();

        // Retrieve leaf term $grandChildTerm322
        $grandChildTerm322 = TaxonomyTerm::get()->byID($grandChildTerm322->ID);
        $this->assertEquals(
            $grandChildTerm322->SingleSelect,
            1,
            'Deeply nested term\'s SingleSelect is altered when its root term is altered'
        );
        $this->assertEquals(
            $grandChildTerm322->DisplayPreference,
            0,
            'Deeply nested term\'s DisplayPreference is altered when its root term is altered'
        );
    }


    /**
     * Test a non-root TaxonomyTerm's RequiredTypes can be either inherited (as conjunction) from its root term, or
     * customise locally
     */
    public function testRequiredTypesInherited()
    {
        $rootTerm1       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm1');
        $rootTerm2       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm2');
        $rootTerm3       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm3');
        $level2Term3p1p1 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term3p1p1');

        $this->assertCount(
            0,
            $rootTerm3->RequiredTypes(),
            'rootTerm3 is not assigned RequiredTypes'
        );
        $this->assertCount(
            2,
            $level2Term3p1p1->RequiredTypes(),
            'A leaf level2Term3p1p1 from the rootTerm3 is assigned with two RequiredTypes'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $level2Term3p1p1->RequiredTypes()->column('ID'),
            'Leaf level2Term3p1p1\'s RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm2->ID,
            $level2Term3p1p1->RequiredTypes()->column('ID'),
            'Leaf level2Term3p1p1\'s RequiredTypes contain rootTerm2'
        );


        // Test inherit flag RequiredTypesInheritRoot doesn't effect on calculated RequiredTypes on 'level2Term3p1p1'
        // due to the rootTerm has not been given RequiredTypes
        $this->assertEquals(
            $level2Term3p1p1->RequiredTypesInheritRoot,
            1,
            'Leaf term level2Term3p1p1\'s RequiredTypesInheritRoot is initialised as "true" by default'
        );
        $this->assertCount(
            2,
            $level2Term3p1p1->RequiredTypesOverall(),
            'calculated RequiredTypes for leaf level2Term3p1p1 from the rootTerm3 have not changed'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $level2Term3p1p1->RequiredTypesOverall()->column('ID'),
            'Leaf level2Term3p1p1\'s calculated RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm2->ID,
            $level2Term3p1p1->RequiredTypesOverall()->column('ID'),
            'Leaf level2Term3p1p1\'s calculated RequiredTypes contain rootTerm2'
        );


        // Test another taxonomy tree presented by rootTerm4
        $rootTerm4       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm4');
        $level1Term4p1   = $this->objFromFixture(TaxonomyTerm::class, 'level1Term4p1');
        $level2Term4p2p1 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term4p2p1');
        $level2Term4p2p2 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term4p2p2');


        // Test the root term rootTerm4
        $this->assertCount(
            2,
            $rootTerm4->RequiredTypes(),
            'rootTerm4 is assigned two RequiredTypes'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $rootTerm4->RequiredTypesOverall()->column('ID'),
            'rootTerm4\'s RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm3->ID,
            $rootTerm4->RequiredTypesOverall()->column('ID'),
            'rootTerm4\'s RequiredTypes contain rootTerm3'
        );



        // Test the middle-level term level1Term4p1
        $this->assertCount(
            2,
            $level1Term4p1->RequiredTypes(),
            'level1Term4p1 is assigned two RequiredTypes'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $level1Term4p1->RequiredTypesOverall()->column('ID'),
            'level1Term4p1\'s RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm2->ID,
            $level1Term4p1->RequiredTypesOverall()->column('ID'),
            'level1Term4p1\'s RequiredTypes contain rootTerm2'
        );
        $this->assertEquals(
            $level1Term4p1->RequiredTypesInheritRoot,
            0,
            'level1Term4p1\'s RequiredTypesInheritRoot is initialised as "false" from fixture'
        );
        $this->assertCount(
            2,
            $level1Term4p1->RequiredTypesOverall(),
            'calculated RequiredTypes for level1Term4p1 from the rootTerm4 have not changed'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $level1Term4p1->RequiredTypesOverall()->column('ID'),
            'level1Term4p1\'s calculated RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm2->ID,
            $level1Term4p1->RequiredTypesOverall()->column('ID'),
            'level1Term4p1\'s calculated RequiredTypes contain rootTerm2'
        );


        // Test the leaf term level2Term4p2p1
        $this->assertCount(
            2,
            $level2Term4p2p1->RequiredTypes(),
            'level2Term4p2p1 is assigned two RequiredTypes'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $level2Term4p2p1->RequiredTypesOverall()->column('ID'),
            'level2Term4p2p1\'s RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm2->ID,
            $level2Term4p2p1->RequiredTypesOverall()->column('ID'),
            'level2Term4p2p1\'s RequiredTypes contain rootTerm2'
        );
        $this->assertEquals(
            $level2Term4p2p1->RequiredTypesInheritRoot,
            1,
            'level2Term4p2p1\'s RequiredTypesInheritRoot is initialised as "true" by default'
        );
        $this->assertCount(
            3,
            $level2Term4p2p1->RequiredTypesOverall(),
            'Overall RequiredTypes are conjunction of the RequiredTypes from both rootTerm4 and level2Term4p2p1'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $level2Term4p2p1->RequiredTypesOverall()->column('ID'),
            'level2Term4p2p1\'s overall RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm2->ID,
            $level2Term4p2p1->RequiredTypesOverall()->column('ID'),
            'level2Term4p2p1\'s overall RequiredTypes contain rootTerm2'
        );
        $this->assertContains(
            $rootTerm3->ID,
            $level2Term4p2p1->RequiredTypesOverall()->column('ID'),
            'level2Term4p2p1\'s overall RequiredTypes contain rootTerm3'
        );


        // Test the leaf term level2Term4p2p2
        $this->assertCount(
            0,
            $level2Term4p2p2->RequiredTypes(),
            'level2Term4p2p2 is not assigned any RequiredTypes'
        );
        $this->assertEquals(
            $level2Term4p2p2->RequiredTypesInheritRoot,
            1,
            'level2Term4p2p2\'s RequiredTypesInheritRoot is initialised as "true" by default'
        );
        $this->assertCount(
            2,
            $level2Term4p2p2->RequiredTypesOverall(),
            'Overall RequiredTypes for level2Term4p2p2 are inherited from both rootTerm4'
        );
        $this->assertContains(
            $rootTerm1->ID,
            $level2Term4p2p2->RequiredTypesOverall()->column('ID'),
            'level2Term4p2p2\'s overall RequiredTypes contain rootTerm1'
        );
        $this->assertContains(
            $rootTerm3->ID,
            $level2Term4p2p2->RequiredTypesOverall()->column('ID'),
            'level2Term4p2p2\'s overall RequiredTypes contain rootTerm3'
        );
    }


    /**
     * Test a root TaxonomyTerm's SingleSelect is not editable once a term from the same taxonomy tree is found being
     * used for tagging to any data objects
     */
    public function testSingleSelectIsLocked()
    {
        $object1         = $this->objFromFixture(OwnerObject::class, 'object1');
        $object3         = $this->objFromFixture(OwnerObject::class, 'object3');
        $rootTerm4       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm4');
        $level2Term4p2p2 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term4p2p2');

        // Test rootTerm4 SingleSelect field is readonly
        $this->assertContains(
            $rootTerm4->ID,
            $object1->Tags()->column('ID'),
            'object1 has been tagged with rootTerm4'
        );
        $this->assertTrue(
            $rootTerm4->getCMSFields()->dataFieldByName('SingleSelect')->isReadonly(),
            'rootTerm4 SingleSelect field is readonly due to being tagged to object1'
        );

        // Test rootTerm4 SingleSelect field is still readonly after rootTerm4 is untagged from object1
        $object1->Tags()->remove($rootTerm4);
        $this->assertContains(
            $level2Term4p2p2->ID,
            $object3->Tags()->column('ID'),
            'object3 has been tagged with level2Term4p2p2'
        );
        $this->assertTrue(
            $rootTerm4->getCMSFields()->dataFieldByName('SingleSelect')->isReadonly(),
            'rootTerm4 SingleSelect field is readonly due to level2Term4p2p2 tagged to object3'
        );


        $object3->Tags()->remove($level2Term4p2p2);
        $this->assertFalse(
            $rootTerm4->getCMSFields()->dataFieldByName('SingleSelect')->isReadonly(),
            'rootTerm4 SingleSelect field is editable'
        );
    }


    /**
     * Test logical rules of SingleSelect defined as a flag of root TaxonomyTerm and RequiredTypes defined as Term to
     * Type many_many relation are correctly validated.
     */
    public function testTaggingLogic()
    {
        $object1         = $this->objFromFixture(OwnerObject::class, 'object1');
        $rootTerm1       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm1');
        $rootTerm3       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm3');
        $level2Term3p2p2 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term3p2p2');
        $rootTerm4       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm4');

        $this->assertCount(4, $object1->Tags(), 'object1 has been tagged with 4 taxonomy terms');
        $this->assertContains($rootTerm1->ID, $object1->Tags()->column('ID'), 'object1 has tag rootTerm1');
        $this->assertContains($rootTerm3->ID, $object1->Tags()->column('ID'), 'object1 has tag rootTerm3');
        $this->assertContains($level2Term3p2p2->ID, $object1->Tags()->column('ID'), 'object1 has tag level2Term3p2p2');
        $this->assertContains($rootTerm4->ID, $object1->Tags()->column('ID'), 'object1 has tag rootTerm4');

        // Test no breaking logic for object1
        $this->assertEquals(
            $rootTerm4->SingleSelect,
            1,
            'rootTerm4 is a root for taxonomy tree of "single select"'
        );
        $this->assertCount(2, $rootTerm4->RequiredTypesOverall(), 'rootTerm4 required 2 types');
        $this->assertContains(
            $rootTerm1->ID,
            $rootTerm4->RequiredTypesOverall()->column('ID'),
            'rootTerm4 require one tag from rootTerm1'
        );
        $this->assertContains(
            $rootTerm3->ID,
            $rootTerm4->RequiredTypesOverall()->column('ID'),
            'rootTerm4 require one tag from rootTerm3'
        );


        $this->assertEquals(
            $rootTerm3->SingleSelect,
            0,
            'rootTerm3 is a root for taxonomy tree of "multi select"'
        );
        $this->assertEquals(
            $rootTerm3->TypeID,
            $level2Term3p2p2->TypeID,
            'rootTerm3 and level2Term3p2p2 have same type'
        );

        // Test object1 tags satisfy all the logic
        $requiredValid = ModelTagLogicValidator::requiredTypesValidate($object1->Tags());
        $this->assertTrue($requiredValid, 'Tags on object1 satisfy logic defined by RequiredTypes relation');

        $singleSelectValid = ModelTagLogicValidator::singleSelectValidate($object1->Tags());
        $this->assertTrue($singleSelectValid, 'Tags on object1 satisfy logic defined by SingleSelect flag');
    }


    /**
     * Test the Tags relation from DataObject to TaxonomyTerm is sorted by the "through" object's `Sort` attribute
     */
    public function testTaggingRelationIsOrdered()
    {
        $object1         = $this->objFromFixture(OwnerObject::class, 'object1');
        $rootTerm1       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm1');
        $rootTerm3       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm3');
        $level2Term3p2p2 = $this->objFromFixture(TaxonomyTerm::class, 'level2Term3p2p2');
        $rootTerm4       = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm4');

        // Test the "Tags" components for $object1 are sorted by the `Sort` attributes of "through" object populated from
        // fixtures, i.e. the `Sort' values of DataObjectTaxonomyTerm from fixture file
        $tags        = [$rootTerm3, $level2Term3p2p2, $rootTerm1, $rootTerm4];
        $identifiers = ['rootTerm3', 'level2Term3p2p2', 'rootTerm1', 'rootTerm4'];
        foreach ($object1->Tags() as $index => $tag) {
            $this->assertEquals(
                $tag->ID,
                $tags[$index]->ID,
                $identifiers[$index] . ' being tagged for object1 is sorted as number: ' . ($index + 1)
            );
        }
    }


    /**
     * Test the OwnerObject owns TaxonomyTerm by defining $owns static variable by checking the existences of a
     * TaxonomyTerm being tagged to a owner object, after the object being drafted, published, unpublished and archived.
     */
    public function testOwnerObjectOwnsTaxonomyTerm()
    {
        $origStage = Versioned::get_stage();

        $object1   = $this->objFromFixture(OwnerObject::class, 'object1');
        $rootTerm1 = $this->objFromFixture(TaxonomyTerm::class, 'rootTerm1');
        $this->assertContains($rootTerm1->ID, $object1->Tags()->column('ID'), 'object1 has tag rootTerm1');

        // Test DataObject as Owner owns TaxonomyTerm through $owns defined
        Versioned::set_stage(Versioned::LIVE);

        $object1FromLive = OwnerObject::get()->byID($object1->ID);
        $this->assertNull($object1FromLive, 'object1 doesn\'t exist on Live table');
        $rootTerm1FromLive = TaxonomyTerm::get()->byID($rootTerm1->ID);
        $this->assertNull($rootTerm1FromLive, 'rootTerm1 doesn\'t exist on Live table');

        $object1->doPublish();
        $object1FromLive = OwnerObject::get()->byID($object1->ID);
        $this->assertNotNull($object1FromLive, 'object1 exists on Live table after being published');
        $rootTerm1FromLive = TaxonomyTerm::get()->byID($rootTerm1->ID);
        $this->assertNotNull($rootTerm1FromLive, 'rootTerm1 exists on Live table after object1 being published');

        $object1->doUnpublish();
        $object1FromLive = OwnerObject::get()->byID($object1->ID);
        $this->assertNull($object1FromLive, 'object1 doesn\'t exist on Live table after being unpublished');
        $rootTerm1FromLive = TaxonomyTerm::get()->byID($rootTerm1->ID);
        $this->assertNull(
            $rootTerm1FromLive,
            'rootTerm1 doesn\'t exist on Live table after object1 being unpublished'
        );

        $object1->doArchive();
        Versioned::set_stage(Versioned::DRAFT);
        $object1FromStage = OwnerObject::get()->byID($object1->ID);
        $this->assertNull($object1FromStage, 'object1 doesn\'t exist on Stage table after being archived');
        $rootTerm1FromStage = TaxonomyTerm::get()->byID($rootTerm1->ID);
        $this->assertNull(
            $rootTerm1FromStage,
            'rootTerm1 doesn\'t exist on Stage table after object1 being archived'
        );

        // Restore the orig archived stage
        if ($origStage) {
            Versioned::set_stage($origStage);
        }
    }


    /**
     * Test when a RequiredTypes loop exists, e.g
     * 1. TypeZ requires TypeY,
     * 2. TypeY requires TypeX,
     * 3. TypeX requires TypeZ,
     * A object tagged with all three types above doesn't run into a deadlock situation
     */
    public function testNoDeadlockUnderLoopRequiredTypes()
    {
        $typeX   = $this->objFromFixture(TaxonomyTerm::class, 'typeX');
        $typeY   = $this->objFromFixture(TaxonomyTerm::class, 'typeY');
        $typeZ   = $this->objFromFixture(TaxonomyTerm::class, 'typeZ');
        $object2 = $this->objFromFixture(OwnerObject::class, 'object2');

        // Assert the settings are correct
        $this->assertContains($typeY->ID, $typeZ->RequiredTypes()->column('ID'), 'typeZ requires typeY');
        $this->assertContains($typeX->ID, $typeY->RequiredTypes()->column('ID'), 'typeY requires typeX');
        $this->assertEquals(
            0,
            $typeX->RequiredTypes()->count(),
            'No required types are assigned to typeX from fixture'
        );
        $this->assertEquals(
            3,
            $object2->Tags()->count(),
            'The object for testing the deadlock has all 3 types assigned'
        );


        // Make Type X to require Type Z so as to complete the loop: Z -> Y -> X -> Z
        $typeX->RequiredTypes()->add($typeZ);
        // Assert the loop completed
        $this->assertContains($typeZ->ID, $typeX->RequiredTypes()->column('ID'), 'typeX requires typeZ');


        // Assert no validation errors
        $valid = ModelTagLogicValidator::requiredTypesValidate($object2->Tags());
        $this->assertTrue(
            $valid,
            'No deadlock situation occurs when validating RequiredTypes logic for object2'
        );
    }
}
