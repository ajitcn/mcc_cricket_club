<?php
namespace App\Models;
use CodeIgniter\Model;
final class UserModel extends Model { protected $table='users'; protected $primaryKey='id'; protected $returnType='array'; protected $allowedFields=['email','password_hash','display_name','is_enabled','failed_login_count','session_version','locked_until','last_login_at','created_at','updated_at']; public function findByEmail(string $email): ?array { return $this->where('email',strtolower(trim($email)))->first(); } }
