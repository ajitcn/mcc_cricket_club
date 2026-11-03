<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class CreateParentPortal extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],'parent_user_id'=>['type'=>'BIGINT','unsigned'=>true],'player_id'=>['type'=>'BIGINT','unsigned'=>true],'relationship_type'=>['type'=>'VARCHAR','constraint'=>30],'created_at'=>['type'=>'DATETIME']]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey(['parent_user_id','player_id']);
        $this->forge->addForeignKey('parent_user_id','users','id','CASCADE','CASCADE'); $this->forge->addForeignKey('player_id','players','id','CASCADE','CASCADE');
        $this->forge->createTable('parent_player_links', true);
        foreach ($this->db->table('roles')->select('id,name')->get()->getResultArray() as $role) {
            if ($role['name'] === 'Parent' && $this->db->table('role_permissions')->where(['role_id'=>$role['id'],'permission'=>'parent_portal.view'])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert(['role_id'=>$role['id'],'permission'=>'parent_portal.view','created_at'=>date('Y-m-d H:i:s')]);
            }
        }
    }
    public function down(): void { }
}
