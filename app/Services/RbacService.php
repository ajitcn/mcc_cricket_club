<?php
namespace App\Services;
use RuntimeException;
final class RbacService {
 private function guard():array{$u=session('user');if(!$u||!service('authorization')->can('rbac.manage'))throw new RuntimeException('Forbidden');return $u;}
 public function assignRole(int $user,int $role):void{$a=$this->guard();$db=db();$db->table('user_roles')->ignore(true)->insert(['user_id'=>$user,'role_id'=>$role,'created_at'=>date('Y-m-d H:i:s')]);service('auditPersistence')->record((int)$a['id'],'role_assigned','user',(string)$user,null,null,['role_id'=>$role]);}
 public function revokeRole(int $user,int $role):void{$a=$this->guard();db()->table('user_roles')->where(['user_id'=>$user,'role_id'=>$role])->delete();service('auditPersistence')->record((int)$a['id'],'role_revoked','user',(string)$user,null,['role_id'=>$role],null);}
 public function assignBranch(int $user,int $branch):void{$a=$this->guard();db()->table('user_branches')->ignore(true)->insert(['user_id'=>$user,'branch_id'=>$branch,'created_at'=>date('Y-m-d H:i:s')]);service('auditPersistence')->record((int)$a['id'],'branch_assigned','user',(string)$user,$branch,null,['branch_id'=>$branch]);}
 public function revokeBranch(int $user,int $branch):void{$a=$this->guard();db()->table('user_branches')->where(['user_id'=>$user,'branch_id'=>$branch])->delete();service('auditPersistence')->record((int)$a['id'],'branch_revoked','user',(string)$user,$branch,['branch_id'=>$branch],null);}
}
