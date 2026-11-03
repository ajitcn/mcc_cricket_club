<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPortalOtpAuthentication extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('mobile_verified_at', 'persons')) {
            $this->forge->addColumn('persons', [
                'mobile_verified_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'phone'],
            ]);
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'account_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'mobile_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'session_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'otp_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'expires_at' => ['type' => 'DATETIME'],
            'attempt_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'requested_at' => ['type' => 'DATETIME'],
            'consumed_at' => ['type' => 'DATETIME', 'null' => true],
            'purpose' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'portal_login'],
            'provider_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['session_hash', 'account_type', 'created_at']);
        $this->forge->addKey(['mobile_hash', 'requested_at']);
        $this->forge->createTable('portal_otp_challenges', true);
    }

    public function down(): void
    {
        // OTP records and verification metadata are retained in deployed environments.
    }
}
