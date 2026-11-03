<?php
namespace App\Controllers;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\{RequestInterface, ResponseInterface};
use Psr\Log\LoggerInterface;
abstract class BaseController extends Controller {
    protected $helpers = ['form','common'];
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) { parent::initController($request,$response,$logger); }
}
