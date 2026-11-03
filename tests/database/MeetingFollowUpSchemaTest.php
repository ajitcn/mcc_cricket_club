<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class MeetingFollowUpSchemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $user = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        if (! is_array($branch) || ! is_array($user)) {
            self::markTestSkipped('Meeting Follow-up fixtures are unavailable.');
        }
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => (int) $user['id']],
            'session_version' => 1,
            'authorizedBranches' => [(string) $branch['id']],
            'permissions' => ['admission.view', 'admission.create', 'admission.update'],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    public function testMeetingFollowUpSchemaKeepsAdmissionTestLineage(): void
    {
        self::assertTrue($this->db->tableExists('admission_test_followups'));
        self::assertTrue($this->db->fieldExists('admission_test_id', 'admission_test_followups'));
        self::assertTrue($this->db->fieldExists('trial_id', 'admission_test_followups'));
        self::assertTrue($this->db->fieldExists('branch_id', 'admission_test_followups'));
        self::assertTrue($this->db->fieldExists('start_at', 'admission_test_followups'));
        self::assertTrue($this->db->fieldExists('end_at', 'admission_test_followups'));
        self::assertTrue($this->db->fieldExists('version', 'admission_test_followups'));
        self::assertFalse($this->db->fieldExists('admission_id', 'admission_test_followups'));
    }

    public function testCrudPreservesTestTrialBranchLinkageVersionAndAudit(): void
    {
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $test = $this->db->query(
            'SELECT at.* FROM admission_tests at WHERE at.branch_id = ? '
            . 'AND NOT EXISTS (SELECT 1 FROM admission_test_followups f WHERE f.admission_test_id = at.id) '
            . 'ORDER BY at.id LIMIT 1',
            [(int) $branch['id']],
        )->getRowArray();
        if (! is_array($test)) {
            self::markTestSkipped('No unused Admission Test fixture is available.');
        }

        $service = service('meetingFollowUp', false);
        $id = $service->create([
            'admission_test_id' => (int) $test['id'],
            'start_at' => '2026-10-20T10:00',
            'follow_up_type' => 'meeting',
            'status' => 'scheduled',
            'notes' => 'TEST_MEETING_FOLLOW_UP',
            'next_action' => 'TEST_NEXT_ACTION',
        ]);
        $row = $this->db->table('admission_test_followups')->where('id', $id)->get()->getRowArray();
        self::assertSame((int) $test['id'], (int) $row['admission_test_id']);
        self::assertSame((int) $test['trial_id'], (int) $row['trial_id']);
        self::assertSame((int) $test['branch_id'], (int) $row['branch_id']);
        self::assertSame('2026-10-20 10:00:00', $row['start_at']);
        self::assertNull($row['end_at']);
        self::assertSame($id, (int) service('meetingFollowUp', false)->find($id)['id']);

        $service->update($id, [
            'version' => $row['version'],
            'admission_test_id' => (int) $test['id'],
            'start_at' => '2026-10-20T12:00',
            'follow_up_type' => 'call',
            'status' => 'completed',
            'notes' => 'TEST_MEETING_FOLLOW_UP_UPDATED',
        ]);
        $updated = $this->db->table('admission_test_followups')->where('id', $id)->get()->getRowArray();
        self::assertSame(2, (int) $updated['version']);
        self::assertSame((int) $test['id'], (int) $updated['admission_test_id']);
        self::assertNotNull($this->db->table('audit_logs')->where(['action' => 'meeting_follow_up_created', 'entity_id' => (string) $id])->get()->getRowArray());
        self::assertNotNull($this->db->table('audit_logs')->where(['action' => 'meeting_follow_up_updated', 'entity_id' => (string) $id])->get()->getRowArray());
    }

    public function testInvalidTestOrStartDateDoesNotCreatePartialRows(): void
    {
        $service = service('meetingFollowUp', false);
        $before = $this->db->table('admission_test_followups')->countAllResults();

        try {
            $service->create([
                'admission_test_id' => 999999999,
                'start_at' => '2026-10-20T10:00',
                'follow_up_type' => 'meeting',
                'status' => 'scheduled',
            ]);
            self::fail('Invalid Admission Test should be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Not found.', $exception->getMessage());
        }

        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $test = $this->db->table('admission_tests')->where('branch_id', (int) $branch['id'])->orderBy('id')->get()->getRowArray();
        if (! is_array($test)) {
            self::markTestSkipped('Admission Test fixture is unavailable.');
        }

        try {
            $service->create([
                'admission_test_id' => (int) $test['id'],
                'start_at' => 'not-a-date',
                'follow_up_type' => 'meeting',
                'status' => 'scheduled',
            ]);
            self::fail('Invalid start date should be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Invalid follow-up date.', $exception->getMessage());
        }

        self::assertSame($before, $this->db->table('admission_test_followups')->countAllResults());
    }

    public function testBrowserPayloadWithBlankNotesCreatesWithoutEndDateOrEndTime(): void
    {
        $test = $this->db->table('admission_tests')->orderBy('id', 'DESC')->get()->getRowArray();

        if (! is_array($test)) {
            self::markTestSkipped('Admission Test fixture is unavailable.');
        }

        $id = service('meetingFollowUp', false)->create([
            'admission_test_id' => (int) $test['id'],
            'start_at' => '2026-10-20 10:00:00',
            'start_time' => '10:00',
            'follow_up_type' => 'meeting',
            'status' => 'scheduled',
            'assigned_user_id' => '',
            'notes' => '',
            'outcome' => '',
            'next_action' => '',
        ]);

        $row = $this->db->table('admission_test_followups')->where('id', $id)->get()->getRowArray();

        self::assertIsArray($row);
        self::assertSame('', $row['notes']);
        self::assertNull($row['end_at']);
        self::assertArrayNotHasKey('end_time', $row);
    }
}
