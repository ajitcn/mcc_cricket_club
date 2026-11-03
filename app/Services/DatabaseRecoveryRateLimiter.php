<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class DatabaseRecoveryRateLimiter implements RecoveryRateLimitInterface
{
    private const ACCOUNT_SCOPE = 'account';
    private const IP_SCOPE = 'ip';
    private const WINDOW_SECONDS = 900;
    private const ACCOUNT_LIMIT = 5;
    private const IP_LIMIT = 10;

    public function allow(string $email, string $ipAddress): bool
    {
        $db = Database::connect('default');
        $now = date('Y-m-d H:i:s');
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $keys = [
            [self::ACCOUNT_SCOPE, $this->hashKey('account', strtolower(trim($email))), self::ACCOUNT_LIMIT],
            [self::IP_SCOPE, $this->hashKey('ip', $ipAddress), self::IP_LIMIT],
        ];

        try {
            $db->transBegin();

            foreach ($keys as [$scope, $keyHash, $limit]) {
                $this->ensureKey($db, $scope, $keyHash, $now);
                $this->lockKey($db, $scope, $keyHash);
                $db->table('recovery_rate_limit_events')
                    ->where(['scope' => $scope, 'key_hash' => $keyHash])
                    ->where('requested_at <', $cutoff)
                    ->delete();

                $count = $db->table('recovery_rate_limit_events')
                    ->where(['scope' => $scope, 'key_hash' => $keyHash])
                    ->where('requested_at >=', $cutoff)
                    ->countAllResults();
                if ($count >= $limit) {
                    $db->transRollback();

                    return false;
                }
            }

            foreach ($keys as [$scope, $keyHash]) {
                $db->table('recovery_rate_limit_events')->insert([
                    'scope' => $scope,
                    'key_hash' => $keyHash,
                    'requested_at' => $now,
                ]);
            }
            $db->transCommit();

            if ($db->transStatus() === false) {
                throw new RecoveryRateLimitUnavailableException('Rate-limit storage failed.');
            }

            return true;
        } catch (RecoveryRateLimitUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RecoveryRateLimitUnavailableException('Rate-limit storage failed.', 0, $exception);
        }
    }

    public function clearAccount(string $email): void
    {
        try {
            Database::connect('default')->table('recovery_rate_limit_events')
                ->where(['scope' => self::ACCOUNT_SCOPE, 'key_hash' => $this->hashKey('account', strtolower(trim($email)))])
                ->delete();
        } catch (Throwable $exception) {
            throw new RecoveryRateLimitUnavailableException('Rate-limit storage failed.', 0, $exception);
        }
    }

    private function ensureKey(BaseConnection $db, string $scope, string $keyHash, string $now): void
    {
        $db->table('recovery_rate_limit_keys')->ignore(true)->insert([
            'scope' => $scope,
            'key_hash' => $keyHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function lockKey(BaseConnection $db, string $scope, string $keyHash): void
    {
        $db->query(
            'SELECT scope FROM recovery_rate_limit_keys WHERE scope = ? AND key_hash = ? FOR UPDATE',
            [$scope, $keyHash],
        )->getRowArray();
    }

    private function hashKey(string $scope, string $value): string
    {
        return hash('sha256', $scope . ':' . $value);
    }
}
