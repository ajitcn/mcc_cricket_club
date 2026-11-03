<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\{RequestInterface, ResponseInterface};
class Permission implements FilterInterface {
    public function before(RequestInterface $request,$arguments=null) { $required=$arguments[0]??null; if($required && service('authorization')->can($required)) return null; if($request->isAJAX()) return service('response')->setStatusCode(403)->setJSON(['status'=>false,'msg'=>'Permission denied.']); return service('response')->setStatusCode(403)->setBody('Permission denied.'); }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){}
}
