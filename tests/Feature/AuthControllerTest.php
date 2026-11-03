<?php

declare (strict_types=1);
namespace Tests\Feature;

use App\Controllers\BaseController;
use App\Modules\Auth\Controllers\Auth;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
final class AuthControllerTest extends TestCase
{
    /** @var RequestInterface&MockObject */
    private RequestInterface $request;
    /** @var ResponseInterface&MockObject */
    private ResponseInterface $response;
    /** @var array<string, mixed> */
    private array $json = [];
    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->response->expects(self::once())->method('setStatusCode')->with(422)->willReturn($this->response);
        $this->response->expects(self::once())->method('setJSON')->willReturnCallback(function (array $data): ResponseInterface {
            $this->json = $data;
            return $this->response;
        });
    }
    public function testMalformedJsonIsRejectedWithoutCallingAuthentication(): void
    {
        $this->request->method('getHeaderLine')->willReturn('application/json');
        $this->request->method('getBody')->willReturn('{"email":');
        self::assertSame($this->response, $this->invokeLogin());
        self::assertFalse($this->json['status']);
        self::assertStringNotContainsString('password', json_encode($this->json));
    }
    public function testMissingCredentialsUseTheSafeValidationContract(): void
    {
        $this->request->method('getHeaderLine')->willReturn('application/json');
        $this->request->method('getBody')->willReturn('{}');
        self::assertSame($this->response, $this->invokeLogin());
        self::assertSame(['status' => false, 'message' => 'A valid credentials object is required.'], $this->json);
    }
    public function testInvalidEmailIsRejectedWithoutAccountEnumeration(): void
    {
        $this->request->method('getHeaderLine')->willReturn('application/json');
        $this->request->method('getBody')->willReturn('{"email":"not-an-email","password":"not-a-secret"}');
        self::assertSame($this->response, $this->invokeLogin());
        self::assertFalse($this->json['status']);
        self::assertStringNotContainsString('not-an-email', json_encode($this->json));
    }
    public function testUnexpectedFieldsAreRejected(): void
    {
        $this->request->method('getHeaderLine')->willReturn('application/json');
        $this->request->method('getBody')->willReturn('{"email":"user@example.test","password":"secret","role":"Admin"}');
        self::assertSame($this->response, $this->invokeLogin());
        self::assertFalse($this->json['status']);
    }
    private function invokeLogin(): ResponseInterface
    {
        $controller = new Auth();
        (new \ReflectionProperty(BaseController::class, 'request'))->setValue($controller, $this->request);
        (new \ReflectionProperty(BaseController::class, 'response'))->setValue($controller, $this->response);
        return $controller->login();
    }
}
