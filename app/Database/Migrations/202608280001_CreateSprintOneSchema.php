<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateSprintOneSchema extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'email' => ['type' => 'VARCHAR', 'constraint' => 190], 'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
    'display_name' => ['type' => 'VARCHAR', 'constraint' => 150], 'is_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
    'failed_login_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0], 'session_version' => ['type' => 'INT',
    'constraint' => 10, 'unsigned' => true, 'default' => 1], 'locked_until' => ['type' => 'DATETIME', 'null' => true],
    'last_login_at' => ['type' => 'DATETIME', 'null' => true], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'name' => ['type' => 'VARCHAR', 'constraint' => 80], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('roles', true);
        $this->forge->addField(['role_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'permission' => ['type' => 'VARCHAR',
    'constraint' => 100], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey(['role_id', 'permission'], true);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions', true);
        $this->forge->addField(['user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'role_id' => ['type' => 'BIGINT',
    'constraint' => 20, 'unsigned' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey(['user_id', 'role_id'], true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_roles', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'name' => ['type' => 'VARCHAR', 'constraint' => 120], 'code' => ['type' => 'VARCHAR', 'constraint' => 80], 'is_active' => ['type' => 'TINYINT',
    'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('branches', true);
        $this->forge->addField(['user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'branch_id' => ['type' => 'BIGINT',
    'constraint' => 20, 'unsigned' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey(['user_id', 'branch_id'], true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_branches', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'owner_user_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true, 'null' => true], 'source' => ['type' => 'VARCHAR', 'constraint' => 100], 'full_name' => ['type' => 'VARCHAR', 'constraint' => 150],
    'email' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true], 'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
    'date_of_birth' => ['type' => 'DATE', 'null' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 40], 'lost_reason' => ['type' => 'VARCHAR',
    'constraint' => 255, 'null' => true], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->addKey(['branch_id', 'email']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('leads', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'lead_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'previous_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'new_status' => ['type' => 'VARCHAR',
    'constraint' => 40], 'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['lead_id', 'created_at']);
        $this->forge->addForeignKey('lead_id', 'leads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('lead_status_history', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'lead_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'note' => ['type' => 'TEXT'], 'follow_up_at' => ['type' => 'DATETIME', 'null' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('lead_id', 'leads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('lead_follow_ups', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'lead_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
    'status' => ['type' => 'VARCHAR', 'constraint' => 40], 'checklist_complete' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
    'converted_player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true], 'created_at' => ['type' => 'DATETIME'],
    'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('lead_id');
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->addForeignKey('lead_id', 'leads', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('admissions', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'admission_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'previous_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'new_status' => ['type' => 'VARCHAR',
    'constraint' => 40], 'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('admission_id', 'admissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('admission_status_history', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'full_name' => ['type' => 'VARCHAR', 'constraint' => 150], 'email' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
    'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'date_of_birth' => ['type' => 'DATE', 'null' => true],
    'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('persons', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'person_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'lead_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
    'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 40],
    'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('lead_id');
        $this->forge->addForeignKey('person_id', 'persons', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('lead_id', 'leads', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('players', true);
        $this->forge->addField(['player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'branch_id' => ['type' => 'BIGINT',
    'constraint' => 20, 'unsigned' => true], 'effective_from' => ['type' => 'DATETIME'], 'effective_to' => ['type' => 'DATETIME', 'null' => true],
    'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey(['player_id', 'effective_from'], true);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('player_branch_history', true);
        $this->forge->addField(['player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'contact_person_id' => ['type' => 'BIGINT',
    'constraint' => 20, 'unsigned' => true], 'relationship_type' => ['type' => 'VARCHAR', 'constraint' => 40], 'is_primary' => ['type' => 'TINYINT',
    'constraint' => 1, 'default' => 0], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey(['player_id', 'contact_person_id', 'relationship_type'], true);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('contact_person_id', 'persons', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('player_contacts', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true], 'action' => ['type' => 'VARCHAR', 'constraint' => 80],
    'entity_type' => ['type' => 'VARCHAR', 'constraint' => 80], 'entity_id' => ['type' => 'VARCHAR', 'constraint' => 80],
    'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true], 'before_summary' => ['type' => 'TEXT', 'null' => true],
    'after_summary' => ['type' => 'TEXT', 'null' => true], 'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
    'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('audit_logs', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
    'expires_at' => ['type' => 'DATETIME'], 'used_at' => ['type' => 'DATETIME', 'null' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('password_reset_tokens', true);
    }
    public function down(): void
    {
        foreach (['password_reset_tokens', 'audit_logs', 'player_contacts', 'player_branch_history', 'players', 'persons', 'admission_status_history',
    'admissions', 'lead_follow_ups', 'lead_status_history', 'leads', 'user_branches', 'branches', 'user_roles', 'role_permissions', 'roles',
    'users'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
