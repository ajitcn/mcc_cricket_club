<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

/** Resolves a Branch Admin's active branch from current database assignments. */
final class BranchContextService
{
    /** @return array{show_selector: bool, branches: list<array<string, mixed>>, active_branch_id: ?int, needs_selection: bool} */
    public function navigationState(): array
    {
        if (! $this->isBranchAdminContext()) {
            session()->remove('activeBranchId');

            return [
                'show_selector' => false,
                'branches' => [],
                'active_branch_id' => null,
                'needs_selection' => false,
            ];
        }

        $branches = $this->authorizedBranches(true);
        $activeBranchId = $this->resolveActiveBranch($branches);

        return [
            'show_selector' => count($branches) > 1,
            'branches' => $branches,
            'active_branch_id' => $activeBranchId,
            'needs_selection' => count($branches) > 1 && $activeBranchId === null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function authorizedBranches(bool $activeOnly = false): array
    {
        $userId = $this->userId();
        if ($userId === null) {
            return [];
        }

        $query = Database::connect('default')
            ->table('user_branches ub')
            ->select('b.id, b.name, b.code, b.is_active')
            ->join('branches b', 'b.id = ub.branch_id')
            ->where('ub.user_id', $userId);

        if ($activeOnly) {
            $query->where('b.is_active', 1);
        }

        $branches = $query
            ->orderBy('b.name', 'ASC')
            ->get()
            ->getResultArray();

        return $branches;
    }

    public function isCurrentlyAuthorizedBranch(int $branchId, bool $activeOnly = false): bool
    {
        if ($branchId < 1) {
            return false;
        }

        foreach ($this->authorizedBranches($activeOnly) as $branch) {
            if ((int) $branch['id'] === $branchId) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> */
    public function authorizedBranchIds(bool $activeOnly = false): array
    {
        return array_values(array_map(
            static fn (array $branch): int => (int) $branch['id'],
            $this->authorizedBranches($activeOnly),
        ));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin') && ! $this->hasRole('Branch Admin');
    }

    public function isBranchAdminContext(): bool
    {
        return $this->hasRole('Branch Admin') && ! $this->hasRole('Super Admin');
    }

    public function activeBranchId(): ?int
    {
        if (! $this->isBranchAdminContext()) {
            session()->remove('activeBranchId');

            return null;
        }

        return $this->resolveActiveBranch($this->authorizedBranches(true));
    }

    public function activeBranchName(): ?string
    {
        $branchId = $this->activeBranchId();
        if ($branchId === null) {
            return null;
        }

        foreach ($this->authorizedBranches(true) as $branch) {
            if ((int) $branch['id'] === $branchId) {
                return (string) $branch['name'];
            }
        }

        session()->remove('activeBranchId');

        return null;
    }

    public function selectActiveBranch(mixed $branchValue): void
    {
        if (! $this->isBranchAdminContext()) {
            throw new RuntimeException('Forbidden');
        }

        $branches = $this->authorizedBranches(true);
        if (count($branches) < 2) {
            throw new RuntimeException('Branch selection is unavailable.');
        }

        if (! is_scalar($branchValue) || ! ctype_digit((string) $branchValue)) {
            throw new RuntimeException('Invalid branch selection.');
        }

        $branchId = (int) $branchValue;
        $allowedIds = array_map(
            static fn (array $branch): int => (int) $branch['id'],
            $branches
        );

        if ($branchId < 1 || ! in_array($branchId, $allowedIds, true)) {
            throw new RuntimeException('Invalid branch selection.');
        }

        session()->set('activeBranchId', (string) $branchId);
        session()->set('activeBranchAssignmentHash', $this->assignmentHash($branches));
    }

    /** @param list<array<string, mixed>> $branches */
    private function resolveActiveBranch(array $branches): ?int
    {
        $assignmentHash = $this->assignmentHash($branches);
        if (session('activeBranchAssignmentHash') !== $assignmentHash) {
            session()->remove('activeBranchId');
            session()->set('activeBranchAssignmentHash', $assignmentHash);
        }

        if ($branches === []) {
            session()->remove('activeBranchId');

            return null;
        }

        if (count($branches) === 1) {
            $branchId = (int) $branches[0]['id'];
            session()->set('activeBranchId', (string) $branchId);

            return $branchId;
        }

        $storedBranch = session('activeBranchId');
        if (! is_scalar($storedBranch) || ! ctype_digit((string) $storedBranch)) {
            session()->remove('activeBranchId');

            return null;
        }

        $branchId = (int) $storedBranch;
        foreach ($branches as $branch) {
            if ((int) $branch['id'] === $branchId) {
                return $branchId;
            }
        }

        session()->remove('activeBranchId');

        return null;
    }

    /** @param list<array<string, mixed>> $branches */
    private function assignmentHash(array $branches): string
    {
        $branchIds = array_map(
            static fn (array $branch): int => (int) $branch['id'],
            $branches
        );
        sort($branchIds, SORT_NUMERIC);

        return hash('sha256', implode(',', $branchIds));
    }

    private function hasRole(string $roleName): bool
    {
        $userId = $this->userId();
        if ($userId === null) {
            return false;
        }

        return Database::connect('default')
            ->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.name', $roleName)
            ->countAllResults() > 0;
    }

    private function userId(): ?int
    {
        $user = session('user');
        $userId = is_array($user) ? ($user['id'] ?? null) : null;

        return is_scalar($userId) && ctype_digit((string) $userId) && (int) $userId > 0
            ? (int) $userId
            : null;
    }
}
