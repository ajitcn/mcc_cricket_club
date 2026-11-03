<?php
namespace App\Controllers;
use CodeIgniter\HTTP\{RequestInterface, ResponseInterface};
use Psr\Log\LoggerInterface;
abstract class BaseAuthenticatedController extends BaseController {
    protected array $user=[]; protected array $roles=[]; protected array $permissions=[];
    public function initController(RequestInterface $request,ResponseInterface $response,LoggerInterface $logger) { parent::initController($request,$response,$logger); $this->user=session('user')??[]; $this->roles=session('roles')??[]; $this->permissions=session('permissions')??[]; }
    protected function hasRole(string $role):bool{return in_array($role,$this->roles,true);}
    protected function hasPermission(string $permission):bool{return in_array($permission,$this->permissions,true);}
}
