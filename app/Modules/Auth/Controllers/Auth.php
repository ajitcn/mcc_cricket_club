<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Controllers\BaseController;
use App\Services\RecoveryRateLimitUnavailableException;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class Auth extends BaseController
{
    private const MAX_JSON_BYTES = 16_384;

    public function loginPage(): string
    {
        return view('login', ['error' => session()->getFlashdata('error'), 'message' => session()->getFlashdata('message'), 'email' => session()->getFlashdata('email')]);
    }

    public function login(): ResponseInterface|string
    {
        try {
            $input = $this->readCredentials();
        } catch (JsonException|InvalidArgumentException $exception) {
            return $this->loginError($exception->getMessage(), 422);
        }

        if ($input === null) {
            return $this->loginError('A valid credentials object is required.', 422);
        }

        try {
            $authenticated = service('auth')->attempt($input['email'], $input['password']);
        } catch (Throwable $exception) {
            log_message('error', 'Authentication attempt failed.');

            return $this->loginError('Authentication is temporarily unavailable.', 503);
        }

        if (! $authenticated) {
            return $this->loginError('Invalid credentials.', 401);
        }

        if (! $this->isJsonRequest()) {
            return redirect()->to('/')->with('message', 'Signed in.');
        }
        return $this->response->setStatusCode(200)->setJSON([
            'status' => true,
            'data' => ['authenticated' => true],
        ]);
    }

    /** Logout also handles stale or disabled sessions. */
    public function logout(): ResponseInterface|string
    {
        try {
            service('auth')->logout();
        } catch (Throwable $exception) {
            log_message('error', 'Logout attempt failed.');

            return $this->safeError('Logout could not be completed.', 500);
        }

        if (! $this->isJsonRequest()) {
            return redirect()->to('/auth/login')->with('message', 'You have been signed out.');
        }
        return $this->response->setStatusCode(200)->setJSON([
            'status' => true,
            'data' => ['authenticated' => false],
        ]);
    }

    public function selectActiveBranch(): ResponseInterface|string
    {
        try {
            service('branchContext')->selectActiveBranch(
                $this->request->getPost('branch_id')
            );

            return redirect()->to('/');
        } catch (Throwable $exception) {
            $message = $exception->getMessage() === 'Invalid branch selection.'
                ? 'Select an active branch assigned to your account.'
                : 'The active branch could not be changed.';

            return redirect()->to('/')->with('error', $message);
        }
    }

    public function recoveryRequest(): ResponseInterface
    {
        try {
            $input = $this->readRecoveryRequest();
        } catch (JsonException|InvalidArgumentException $exception) {
            return $this->safeError('The recovery request is invalid.', 422);
        }

        try {
            service('auth')->requestReset($input['email'], $this->request->getIPAddress());
        } catch (RecoveryRateLimitUnavailableException $exception) {
            log_message('error', 'Recovery rate-limit enforcement failed.');

            return $this->recoveryAcceptedResponse();
        } catch (Throwable $exception) {
            log_message('error', 'Recovery request failed.');

            return $this->safeError('Recovery is temporarily unavailable.', 503);
        }

        return $this->recoveryAcceptedResponse();
    }

    private function recoveryAcceptedResponse(): ResponseInterface
    {
        return $this->response->setStatusCode(202)->setJSON([
            'status' => true,
            'data' => [
                'message' => 'If the account is eligible, recovery instructions will be sent.',
            ],
        ]);
    }

    public function recoveryReset(): ResponseInterface
    {
        try {
            $input = $this->readRecoveryReset();
        } catch (JsonException|InvalidArgumentException $exception) {
            return $this->safeError('The recovery request is invalid.', 422);
        }

        try {
            service('auth')->resetPassword($input['token'], $input['password']);
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Invalid reset token.') {
                return $this->safeError('The recovery token is invalid or expired.', 400);
            }

            if ($exception->getMessage() === 'Invalid password.') {
                return $this->safeError('The recovery request is invalid.', 422);
            }

            log_message('error', 'Password reset failed.');

            return $this->safeError('Password reset is temporarily unavailable.', 503);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'status' => true,
            'data' => ['reset' => true],
        ]);
    }

    /** @return array{email: string, password: string}|null */
    private function readCredentials(): ?array
    {
        if ($this->isJsonRequest()) {
            $body = $this->request->getBody();
            if (strlen($body) > self::MAX_JSON_BYTES) {
                throw new InvalidArgumentException('The request is too large.');
            }
            $payload = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } else {
            $payload = $this->request->getPost();
            unset($payload[csrf_token()]);
        }

        if (! is_array($payload) || array_diff(array_keys($payload), ['email', 'password']) !== []) {
            throw new InvalidArgumentException('Only email and password are accepted.');
        }
        if (! isset($payload['email'], $payload['password'])
            || ! is_string($payload['email']) || ! is_string($payload['password'])) {
            return null;
        }

        $email = strtolower(trim($payload['email']));
        $password = $payload['password'];
        if ($email === '' || $password === '') {
            return null;
        }
        if (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (strlen($password) > 1024) {
            throw new InvalidArgumentException('The credentials are invalid.');
        }

        return ['email' => $email, 'password' => $password];
    }

    /** @return array{email: string} */
    private function readRecoveryRequest(): array
    {
        $payload = $this->readJsonPayload();
        $this->assertFields($payload, ['email']);

        if (! isset($payload['email']) || ! is_string($payload['email'])) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        $email = strtolower(trim($payload['email']));
        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        return ['email' => $email];
    }

    /** @return array{token: string, password: string} */
    private function readRecoveryReset(): array
    {
        $payload = $this->readJsonPayload();
        $this->assertFields($payload, ['token', 'password']);

        if (! isset($payload['token'], $payload['password'])
            || ! is_string($payload['token']) || ! is_string($payload['password'])) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        if (! preg_match('/^[a-f0-9]{64}$/', $payload['token'])) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        if ($payload['password'] === '' || strlen($payload['password']) > 1024) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        return ['token' => $payload['token'], 'password' => $payload['password']];
    }

    /** @return array<string, mixed> */
    private function readJsonPayload(): array
    {
        if (! str_contains(strtolower((string) $this->request->getHeaderLine('Content-Type')), 'application/json')) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        $body = $this->request->getBody();
        if (strlen($body) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        $payload = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function assertFields(array $payload, array $allowed): void
    {
        if (array_diff(array_keys($payload), $allowed) !== []) {
            throw new InvalidArgumentException('Invalid recovery request.');
        }
    }

    private function safeError(string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status' => false,
            'message' => $message,
        ]);
    }

    private function loginError(string $message, int $status): ResponseInterface|string
    {
        if (! $this->isJsonRequest()) {
            return redirect()->to('/auth/login')->withInput()->with('error', $message);
        }

        return $this->safeError($message, $status);
    }

    private function isJsonRequest(): bool
    {
        $contentType = strtolower((string) $this->request->getHeaderLine('Content-Type'));

        return str_contains($contentType, 'application/json')
            || ($contentType === '' && trim((string) $this->request->getBody()) !== '' && $this->request->getPost() === []);
    }
}
