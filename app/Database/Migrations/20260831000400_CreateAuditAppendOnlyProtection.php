<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateAuditAppendOnlyProtection extends Migration
{
    public function up(): void
    {
        $triggers = $this->db->query("SHOW TRIGGERS LIKE 'audit_logs'")->getResultArray();
        $names = array_map(static fn(array $row): string => (string) ($row['Trigger'] ?? ''), $triggers);
        if (!in_array('audit_logs_no_update', $names, true)) {
            $this->db->query("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
    'Audit records are append-only'");
        }
        if (!in_array('audit_logs_no_delete', $names, true)) {
            $this->db->query("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
    'Audit records are append-only'");
        }
    }
    public function down(): void
    {
        // Audit protection is forward-only under the permanent data-preservation rule.
    }
}
