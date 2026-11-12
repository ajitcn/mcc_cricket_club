<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use Config\Database;
use Throwable;

final class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900;
    private const RESET_TOKEN_BYTES = 32;
    private const RESET_TOKEN_TTL = 900;

    public function __construct(
        private readonly ?RecoveryDeliveryInterface $recoveryDelivery = null,
        private readonly ?RecoveryRateLimitInterface $recoveryRateLimiter = null,
    )
    {
    }

    public function isLoggedIn(): bool
    {
        return service('authorization')->isAuthenticated() && $this->isCurrentSession();
    }

    public function isCurrentSession(): bool
    {
        $id = session('user.id');
        if (! is_int($id) && ! ctype_digit((string) $id)) {
            return false;
        }
        $user = (new UserModel())->find((int) $id);
        $version = session('session_version');

        return is_array($user) && (int) $user['is_enabled'] === 1
            && $version !== null && (int) $user['session_version'] === (int) $version;
    }

    public function user(): ?array
    {
        $user = session('user');

        return is_array($user) ? $user : null;
    }

    public function roles(): array
    {
        $roles = session('roles');

        return is_array($roles) ? $roles : [];
    }

    public function permissions(): array
    {
        $permissions = session('permissions');

        return is_array($permissions) ? $permissions : [];
    }

    public function attempt(string $email, string $password): bool
    {
        $model = new UserModel();
        $user = $model->findByEmail($email);
        $valid = is_array($user) && (int) $user['is_enabled'] === 1
            && ! $this->isLocked($user['locked_until'] ?? null)
            && password_verify($password, (string) $user['password_hash']);

        if (! $valid) {
            $this->recordFailedAttempt($model, $user);

            return false;
        }

        $updates = [
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $updates['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $model->update((int) $user['id'], $updates);
        $session = session();
        if (ENVIRONMENT !== 'testing' && session_status() !== PHP_SESSION_ACTIVE) {
            $session->start();
        }
        $session->regenerate(true);
        $this->populateSession($user);
        service('auditPersistence')->record((int) $user['id'], 'login', 'user', (string) $user['id'], null, null, ['success' => true]);

        return true;
    }

    public function requestReset(string $email, ?string $ipAddress = null): void
    {
        $email = strtolower(trim($email));
        $ipAddress ??= 'unknown';
        try {
            $allowed = ($this->recoveryRateLimiter ?? service('recoveryRateLimiter'))->allow($email, $ipAddress);
        } catch (RecoveryRateLimitUnavailableException $exception) {
            log_message('error', 'Recovery rate-limit storage failed.');
            throw $exception;
        }

        if (! $allowed) {
            $this->recordResetTokenEvent('password_recovery_rate_limited');

            return;
        }

        $user = (new UserModel())->findByEmail($email);
        $actor = is_array($user) ? (int) $user['id'] : null;
        $entity = $actor === null ? 'unknown' : (string) $actor;

        service('auditPersistence')->record($actor, 'password_recovery_requested', 'user', $entity, null);

        if (! is_array($user) || (int) $user['is_enabled'] !== 1) {
            return;
        }

        $db = Database::connect('default');
        $token = bin2hex(random_bytes(self::RESET_TOKEN_BYTES));
        $tokenId = null;

        $db->transStart();
        $db->table('password_reset_tokens')->where(['user_id' => (int) $user['id'], 'used_at' => null])
            ->update(['used_at' => date('Y-m-d H:i:s')]);
        $db->table('password_reset_tokens')->insert([
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + self::RESET_TOKEN_TTL),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $tokenId = (int) $db->insertID();
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new \RuntimeException('Recovery request failed.');
        }

        service('auditPersistence')->record((int) $user['id'], 'password_recovery_delivery_attempted', 'user', (string) $user['id'], null);

        try {
            ($this->recoveryDelivery ?? service('recoveryDelivery'))->deliver((string) $user['email'], $token);
            service('auditPersistence')->record((int) $user['id'], 'password_recovery_delivery_succeeded', 'user', (string) $user['id'], null);
        } catch (RecoveryDeliveryException $exception) {
            $db->table('password_reset_tokens')->where(['id' => $tokenId, 'used_at' => null])->delete();
            service('auditPersistence')->record((int) $user['id'], 'password_recovery_delivery_failed', 'user', (string) $user['id'], null);
            log_message('error', 'Recovery delivery failed.');
        } finally {
            unset($token);
        }
    }

    public function resetPassword(string $token, string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 1024) {
            throw new \RuntimeException('Invalid password.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->recordResetTokenEvent('password_reset_token_invalid');
            throw new \RuntimeException('Invalid reset token.');
        }

        $db = Database::connect('default');
        $db->transStart();
        $row = $db->query(
            'SELECT * FROM password_reset_tokens WHERE token_hash = ? LIMIT 1 FOR UPDATE',
            [hash('sha256', $token)],
        )->getRowArray();

        $expired = is_array($row) && strtotime((string) $row['expires_at']) < time();
        if (! is_array($row) || $row['used_at'] !== null || $expired) {
            $this->recordResetTokenEvent(is_array($row) && $row['used_at'] !== null
                ? 'password_reset_token_reused'
                : ($expired ? 'password_reset_token_expired' : 'password_reset_token_invalid'));
            $db->transRollback();
            throw new \RuntimeException('Invalid reset token.');
        }

        $user = (new UserModel())->find((int) $row['user_id']);
        if (! is_array($user)) {
            $db->transRollback();
            $this->recordResetTokenEvent('password_reset_token_invalid');
            throw new \RuntimeException('Invalid reset token.');
        }

        (new UserModel())->update((int) $user['id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'session_version' => (int) $user['session_version'] + 1,
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);
        $db->table('password_reset_tokens')->where('id', (int) $row['id'])
            ->where('used_at', null)->update(['used_at' => date('Y-m-d H:i:s')]);
        service('auditPersistence')->record((int) $user['id'], 'password_reset_completed', 'user', (string) $user['id'], null);
        try {
            ($this->recoveryRateLimiter ?? service('recoveryRateLimiter'))->clearAccount((string) $user['email']);
        } catch (RecoveryRateLimitUnavailableException $exception) {
            $db->transRollback();
            log_message('error', 'Recovery rate-limit storage failed during password reset.');

            throw new \RuntimeException('Reset failed.', 0, $exception);
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new \RuntimeException('Reset failed.');
        }
    }

    public function disable(int $userId, int $actorId): void
    {
        $db = Database::connect('default');
        $user = (new UserModel())->find($userId);
        if (! is_array($user)) {
            throw new \RuntimeException('Not found.');
        }
        $db->transStart();
        (new UserModel())->update($userId, ['is_enabled' => 0, 'session_version' => (int) $user['session_version'] + 1]);
        service('auditPersistence')->record($actorId, 'account_disabled', 'user', (string) $userId, null, ['is_enabled' => 1], ['is_enabled' => 0]);
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new \RuntimeException('Disable failed.');
        }
    }

    public function logout(): void
    {
        $user = $this->user();
        if (is_array($user) && isset($user['id'])) {
            try {
                service('auditPersistence')->record((int) $user['id'], 'logout', 'user', (string) $user['id'], null);
            } catch (Throwable $exception) {
                log_message('error', 'Logout audit failed.');
            }
        }
        session()->remove([
            'isLoggedIn',
            'user',
            'session_version',
            'roles',
            'permissions',
            'authorizedBranches',
            'activeBranchId',
            'activeBranchAssignmentHash',
            'accountEnabled',
        ]);
        session()->destroy();
    }

    public function updatePassword(string $currentPassword, string $newPassword, string $confirmation): void
    {
        if ($newPassword === '' || strlen($newPassword) < 12 || strlen($newPassword) > 1024 || $newPassword !== $confirmation) {
            throw new \RuntimeException('Invalid password.');
        }
        if ($currentPassword === '' || $currentPassword === $newPassword) {
            throw new \RuntimeException('Invalid password change.');
        }

        $user = $this->user();
        $userId = (int) ($user['id'] ?? 0);
        $record = $userId > 0 ? (new UserModel())->find($userId) : null;
        if (! is_array($record) || ! password_verify($currentPassword, (string) $record['password_hash'])) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $nextVersion = (int) $record['session_version'] + 1;
        (new UserModel())->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'session_version' => $nextVersion,
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);
        session()->set('session_version', $nextVersion);
        service('auditPersistence')->record($userId, 'password_changed', 'user', (string) $userId, null);
    }

    private function isLocked(mixed $until): bool
    {
        return is_string($until) && $until !== '' && strtotime($until) > time();
    }

    private function recordFailedAttempt(UserModel $model, ?array $user): void
    {
        $id = is_array($user) ? (int) $user['id'] : null;
        if ($id !== null) {
            $count = (int) $user['failed_login_count'] + 1;
            $model->update($id, ['failed_login_count' => $count, 'locked_until' => $count >= self::MAX_FAILED_ATTEMPTS ? date('Y-m-d H:i:s', time() + self::LOCKOUT_SECONDS) : null]);
        }
        service('auditPersistence')->record($id, 'login_failed', 'user', $id === null ? 'unknown' : (string) $id, null, null, ['success' => false]);
    }

    private function populateSession(array $user): void
    {
        $db = Database::connect('default');
        session()->remove(['activeBranchId', 'activeBranchAssignmentHash']);
        $roles = $db->table('roles r')->select('r.name')->join('user_roles ur', 'ur.role_id = r.id')->where('ur.user_id', $user['id'])->get()->getResultArray();
        $permissions = $db->table('role_permissions rp')->select('rp.permission')->join('user_roles ur', 'ur.role_id = rp.role_id')->where('ur.user_id', $user['id'])->get()->getResultArray();
        $branches = $db->table('user_branches')->select('branch_id')->where('user_id', $user['id'])->get()->getResultArray();
        session()->set([
            'isLoggedIn' => true,
            'user' => ['id' => (int) $user['id'], 'email' => (string) $user['email'], 'display_name' => (string) $user['display_name']],
            'session_version' => (int) $user['session_version'], 'roles' => array_column($roles, 'name'),
            'permissions' => array_column($permissions, 'permission'), 'authorizedBranches' => array_map('strval', array_column($branches, 'branch_id')),
            'accountEnabled' => true,
        ]);
    }

    private function recordResetTokenEvent(string $action): void
    {
        try {
            service('auditPersistence')->record(null, $action, 'user', 'unknown', null);
        } catch (Throwable $exception) {
            log_message('error', 'Recovery audit failed.');
        }
    }
}
