<?php

namespace App\Services;

/**
 * Generic server-side authorization boundary.
 *
 * This class deliberately does not define MCC roles or business permissions.
 * Approved policy data can be supplied by the future identity implementation.
 */
class AuthorizationService
{
    /** @var array<string, mixed> */
    private array $context;

    private bool $usesLiveSession;

    /**
     * @param array<string, mixed>|null $context Testable authorization context.
     */
    public function __construct(?array $context = null)
    {
        $this->usesLiveSession = $context === null;
        $this->context = $context ?? (function_exists('session') ? (session()->get() ?? []) : []);
    }

    public function isAuthenticated(): bool
    {
        $context = $this->context();

        return ($context['isLoggedIn'] ?? false) === true
            && ! $this->isDisabled();
    }

    public function isDisabled(): bool
    {
        $context = $this->context();

        return ($context['isDisabled'] ?? false) === true
            || ($context['accountEnabled'] ?? true) === false;
    }

    public function can(string $permission, ?string $branchId = null): bool
    {
        if (! $this->isAuthenticated() || trim($permission) === '') {
            return false;
        }

        $permissions = $this->context()['permissions'] ?? [];
        if (! is_array($permissions) || ! in_array($permission, $permissions, true)) {
            return false;
        }

        return $branchId === null || $this->canAccessBranch($branchId);
    }

    public function canAccessBranch(string $branchId): bool
    {
        if (! $this->isAuthenticated() || trim($branchId) === '') {
            return false;
        }

        $context = $this->context();
        $branches = $context['authorizedBranches'] ?? $context['branchIds'] ?? [];

        return is_array($branches) && in_array($branchId, $branches, true);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        if ($this->usesLiveSession && function_exists('session')) {
            return session()->get() ?? [];
        }

        return $this->context;
    }
}
