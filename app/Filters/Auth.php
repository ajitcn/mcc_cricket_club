<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\{RequestInterface, ResponseInterface};
class Auth implements FilterInterface {
    public function before(RequestInterface $request,$arguments=null) { if(service('auth')->isLoggedIn()) return null; if($request->isAJAX()) return service('response')->setStatusCode(401)->setJSON(['status'=>false,'message'=>'Authentication required.']); return redirect()->to(base_url('/'))->with('error','Authentication required.'); }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){}
}
