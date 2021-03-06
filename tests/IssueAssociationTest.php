<?php

/*
 * This file is part of the Eventum (Issue Tracking System) package.
 *
 * @copyright (c) Eventum Team
 * @license GNU General Public License, version 2 or later (GPL-2+)
 *
 * For the full copyright and license information,
 * please see the COPYING and AUTHORS files
 * that were distributed with this source code.
 */

namespace Eventum\Test;

use Eventum\Db\Doctrine;
use Eventum\Model\Repository\IssueAssociationRepository;
use Eventum\Test\Traits\DoctrineTrait;
use InvalidArgumentException;
use IssueSeeder;
use Setup;

/**
 * @group db
 */
class IssueAssociationTest extends TestCase
{
    use DoctrineTrait;

    /** @var int */
    private $system_user_id;

    /** @var IssueAssociationRepository */
    private $repo;

    public function setUp(): void
    {
        $this->system_user_id = Setup::get()['system_user_id'];
        $this->repo = Doctrine::getIssueAssociationRepository();

        $issues = [IssueSeeder::ISSUE_1, IssueSeeder::ISSUE_2, 13, 14, 15];
        $this->repo->deleteAllRelations($issues);
    }

    public function testAssociateIssue(): void
    {
        $usr_id = $this->system_user_id;
        $iss1_id = IssueSeeder::ISSUE_1;
        $iss2_id = IssueSeeder::ISSUE_1;

        $this->repo->addIssueAssociation($usr_id, $iss1_id, $iss2_id);
        $assoc1 = $this->repo->getAssociatedIssues($iss1_id);
        $assoc2 = $this->repo->getAssociatedIssues($iss2_id);

        // the association exists both ways
        $this->assertEquals([$iss2_id], $assoc1);
        $this->assertEquals([$iss1_id], $assoc2);

        // adding association again throws
        try {
            $this->repo->addIssueAssociation($usr_id, $iss1_id, $iss2_id);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Issue $iss1_id already associated to $iss2_id", $e->getMessage());
        }
        try {
            $this->repo->addIssueAssociation($usr_id, $iss2_id, $iss1_id);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Issue $iss2_id already associated to $iss1_id", $e->getMessage());
        }

        // now remove the association
        $this->repo->removeAssociation($usr_id, $iss1_id, $iss2_id);
        // second remove should fail both sides
        try {
            $this->repo->removeAssociation($usr_id, $iss1_id, $iss2_id);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Issue $iss1_id not associated to $iss2_id", $e->getMessage());
        }
        try {
            $this->repo->removeAssociation($usr_id, $iss2_id, $iss1_id);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Issue $iss2_id not associated to $iss1_id", $e->getMessage());
        }
    }

    public function testBulkUpdate(): void
    {
        $usr_id = $this->system_user_id;
        $issue_id = IssueSeeder::ISSUE_1;

        $associated_issues = [$issue_id, '13', '14', 15, $issue_id, 13, 'lol', -1, null, '', false];
        $res = $this->repo->updateAssociations($usr_id, $issue_id, $associated_issues);

        $this->assertNotEmpty($res);
        $this->assertEquals('"lol" was not valid Issue Id and was removed.', $res[0]);
        $this->assertEquals('"-1" was not valid Issue Id and was removed.', $res[1]);

        $res = $this->repo->getAssociatedIssues($issue_id);
        $exp = [13, 14, 15];
        $this->assertEquals($exp, $res);

        // test that removing also works
        $associated_issues = ['13'];
        $res = $this->repo->updateAssociations($usr_id, $issue_id, $associated_issues);
        $this->assertEmpty($res);
        $res = $this->repo->getAssociatedIssues($issue_id);
        $exp = [13];
        $this->assertEquals($exp, $res);
    }

    /**
     * @see \Issue::getAssociatedIssuesDetails();
     * @see \Issue::getAssociatedIssues();
     */
    public function testGetDetails(): void
    {
        $usr_id = $this->system_user_id;
        $iss1_id = IssueSeeder::ISSUE_1;
        $iss2_id = IssueSeeder::ISSUE_2;

        $this->repo->addIssueAssociation($usr_id, $iss1_id, $iss2_id);

        // direct view
        $associated_issues = $this->repo->getAssociatedIssues($iss1_id);
        $associated_issues_details = $this->repo->getIssueDetails($associated_issues);

        $this->assertEquals([$iss2_id], $associated_issues);
        // array(
        //  'associated_issue' => '13',
        //  'associated_title' => '',
        //  'current_status' => 'discovery',
        // 'is_closed' => '0',
        // ),

        $this->assertEquals($iss2_id, $associated_issues_details[0]['associated_issue']);

        // reverse view
        $associated_issues = $this->repo->getAssociatedIssues($iss2_id);
        $associated_issues_details = $this->repo->getIssueDetails($associated_issues);

        $this->assertEquals([$iss1_id], $associated_issues);
        // array(
        //  'associated_issue' => '13',
        //  'associated_title' => '',
        //  'current_status' => 'discovery',
        // 'is_closed' => '0',
        // ),
        $this->assertEquals($iss1_id, $associated_issues_details[0]['associated_issue']);
    }
}
