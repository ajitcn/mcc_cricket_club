<?php

declare (strict_types=1);
namespace App\Modules\Admin\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page('App\Modules\Admin\Views\index', 'Administration', ['stats' => service('admin')->statistics()]);
    }
    public function createBranchPage(): string
    {
        return $this->page('App\Modules\Admin\Views\branch_form', 'Create branch', ['mode' => 'create', 'branch' => []]);
    }
    public function createBranch(): ResponseInterface|string
    {
        $input = $this->request->getPost();
        unset($input[csrf_token()]);

        try {
            $id = service('admin')->createBranch($input);

            return redirect()->to('/admin/branches/' . $id)->with('message', 'Branch created.');
        } catch (Throwable $e) {
            $response = redirect()
                ->to('/admin/branches/create')
                ->withInput()
                ->with('error', $this->branchSafe($e));
            $fieldErrors = $this->branchFieldErrors($input, $e);

            if ($fieldErrors !== []) {
                $response->with('errors', $fieldErrors);
            }

            return $response;
        }
    }

    public function branches(): string
    {
        return $this->page('App\Modules\Admin\Views\branches', 'Branches', [
            'branches' => service('admin')->managedBranches($this->request->getGet()),
            'filters' => $this->request->getGet(),
        ]);
    }
    public function branch(int $id): string
    {
        try {
            return $this->page('App\Modules\Admin\Views\branch', 'Branch details', ['branch' => service('admin')->branch($id)]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(404)->setBody(esc($this->branchSafe($e)));
        }
    }
    public function editBranchPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\Modules\Admin\Views\branch_form', 'Edit branch', ['mode' => 'edit', 'branch' => service('admin')->branch($id)]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(404)->setBody(esc($this->branchSafe($e)));
        }
    }
    public function updateBranch(int $id): ResponseInterface|string
    {
        $input = $this->request->getPost();
        unset($input[csrf_token()]);

        try {
            service('admin')->updateBranch($id, $input);
            return redirect()->to('/admin/branches/' . $id)->with('message', 'Branch updated.');
        } catch (Throwable $e) {
            $response = redirect()
                ->to('/admin/branches/' . $id . '/edit')
                ->withInput()
                ->with('error', $this->branchSafe($e));
            $fieldErrors = $this->branchFieldErrors($input, $e);

            if ($fieldErrors !== []) {
                $response->with('errors', $fieldErrors);
            }

            return $response;
        }
    }
    public function setBranchActive(int $id): ResponseInterface|string
    {
        try {
            service('admin')->setBranchActive($id, (string) $this->request->getPost('active') === '1');
            return redirect()->to('/admin/branches/' . $id)->with('message', 'Branch status updated.');
        } catch (Throwable $e) {
            return redirect()->to('/admin/branches/' . $id)->with('error', $this->branchSafe($e));
        }
    }
    public function leadSources(): string
    {
        return $this->page('App\Modules\Admin\Views\lead_config', 'Lead sources', ['kind' => 'source', 'items' => service('admin')->leadSources()]);
    }
    public function leadStatuses(): string
    {
        return $this->page('App\Modules\Admin\Views\lead_config', 'Lead statuses', ['kind' => 'status', 'items' => service('admin')->leadStatuses()]);
    }
    public function leadSourceForm(): string
    {
        return $this->page('App\Modules\Admin\Views\lead_config_form', 'Create lead source', ['kind' => 'source', 'mode' => 'create', 'item' => []]);
    }
    public function leadStatusForm(): string
    {
        return $this->page('App\Modules\Admin\Views\lead_config_form', 'Create lead status', ['kind' => 'status', 'mode' => 'create', 'item' => []]);
    }
    public function leadSourceEdit(int $id): string
    {
        return $this->configEdit('source', $id);
    }
    public function leadStatusEdit(int $id): string
    {
        return $this->configEdit('status', $id);
    }
    public function createLeadSource(): ResponseInterface|string
    {
        try {
            service('admin')->createLeadSource((string) $this->request->getPost('name'), (int) $this->request->getPost('sort_order'));
            return redirect()->to('/admin/lead-sources')->with('message', 'Lead source created.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->adminSafe($e));
        }
    }
    public function createLeadStatus(): ResponseInterface|string
    {
        try {
            service('admin')->createLeadStatus((string) $this->request->getPost('name'), (int) $this->request->getPost('sort_order'));
            return redirect()->to('/admin/lead-statuses')->with('message', 'Lead status created.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->adminSafe($e));
        }
    }
    public function updateLeadSource(int $id): ResponseInterface|string
    {
        try {
            service('admin')->updateLeadSource($id, (string) $this->request->getPost('name'), (int) $this->request->getPost('sort_order'));
            return redirect()->to('/admin/lead-sources')->with('message', 'Lead source updated.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->adminSafe($e));
        }
    }
    public function updateLeadStatus(int $id): ResponseInterface|string
    {
        try {
            service('admin')->updateLeadStatus($id, (string) $this->request->getPost('name'), (int) $this->request->getPost('sort_order'));
            return redirect()->to('/admin/lead-statuses')->with('message', 'Lead status updated.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->adminSafe($e));
        }
    }
    public function setLeadSourceActive(int $id): ResponseInterface|string
    {
        try {
            service('admin')->setLeadSourceActive($id, (string) $this->request->getPost('active') === '1');
            return redirect()->to('/admin/lead-sources')->with('message', 'Lead source status updated.');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->adminSafe($e));
        }
    }
    public function setLeadStatusActive(int $id): ResponseInterface|string
    {
        try {
            service('admin')->setLeadStatusActive($id, (string) $this->request->getPost('active') === '1');
            return redirect()->to('/admin/lead-statuses')->with('message', 'Lead status status updated.');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->adminSafe($e));
        }
    }
    private function configEdit(string $kind, int $id): string
    {
        try {
            $items = $kind === 'source' ? service('admin')->leadSources() : service('admin')->leadStatuses();
            foreach ($items as $item) {
                if ((int) $item['id'] === $id) {
                    return $this->page('App\Modules\Admin\Views\lead_config_form', 'Edit lead ' . $kind, ['kind' => $kind, 'mode' => 'edit', 'item' => $item]);
                }
            }
            throw new \RuntimeException('Not found.');
        } catch (Throwable $e) {
            return $this->response->setStatusCode(404)->setBody(esc($this->adminSafe($e)));
        }
    }
    public function users(): string
    {
        return $this->page('App\Modules\Admin\Views\users', 'Users', ['users' => service('admin')->users($this->request->getGet('q')),
    'query' => (string) $this->request->getGet('q')]);
    }
    public function createUserPage(): string
    {
        return $this->page('App\Modules\Admin\Views\user_form', 'Create user', ['mode' => 'create', 'user' => []]);
    }
    public function createUser(): ResponseInterface|string
    {
        try {
            $id = service('admin')->createUser($this->request->getPost());
            return redirect()->to('/admin/users/' . $id)->with('message', 'User created.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->adminSafe($e));
        }
    }
    public function user(int $id): string
    {
        try {
            return $this->page('App\Modules\Admin\Views\user', 'User details', ['user' => service('admin')->user($id),
    'roles' => service('admin')->roles(), 'branches' => service('admin')->branches()]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(404)->setBody(esc($this->adminSafe($e)));
        }
    }
    public function editUserPage(int $id): string
    {
        try {
            return $this->page('App\Modules\Admin\Views\user_form', 'Edit user', ['mode' => 'edit', 'user' => service('admin')->user($id)]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(404)->setBody(esc($this->adminSafe($e)));
        }
    }
    public function updateUser(int $id): ResponseInterface|string
    {
        try {
            service('admin')->updateUser($id, $this->request->getPost());
            return redirect()->to('/admin/users')->with('message', 'User updated.');
        } catch (Throwable $e) {
            $error = $e->getMessage() === 'Invalid user details.'
                ? 'Enter a valid display name and email. Leave the new password blank to keep the current password, or enter at least 16 characters.'
                : $this->adminSafe($e);

            return redirect()->to('/admin/users/' . $id . '/edit')
                ->withInput()
                ->with('error', $error);
        }
    }
    public function setEnabled(int $id): ResponseInterface|string
    {
        try {
            service('admin')->setEnabled($id, (string) $this->request->getPost('enabled') === '1');
            return redirect()->to('/admin/users/' . $id)->with('message', 'Account status updated.');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->adminSafe($e));
        }
    }
    public function assignRole(int $id): ResponseInterface|string
    {
        try {
            service('admin')->assignRole($id, (int) $this->request->getPost('role_id'), (string) $this->request->getPost('assign') === '1');
            return redirect()->to('/admin/users/' . $id)->with('message', 'Role assignment updated.');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->adminSafe($e));
        }
    }
    public function assignBranch(int $id): ResponseInterface|string
    {
        try {
            service('admin')->assignBranch($id, (int) $this->request->getPost('branch_id'), (string) $this->request->getPost('assign') === '1');
            return redirect()->to('/admin/users/' . $id)->with('message', 'Branch assignment updated.');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->adminSafe($e));
        }
    }
    public function roles(): string
    {
        return $this->page('App\Modules\Admin\Views\roles', 'Roles', ['roles' => service('admin')->roles()]);
    }
    public function createRolePage(): string
    {
        return $this->page('App\Modules\Admin\Views\role_form', 'Create role', ['mode' => 'create', 'role' => []]);
    }
    public function createRole(): ResponseInterface|string
    {
        try {
            $id = service('admin')->createRole((string) $this->request->getPost('name'));
            return redirect()->to('/admin/roles/' . $id)->with('message', 'Role created.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->adminSafe($e));
        }
    }
    public function role(int $id): string
    {
        try {
            return $this->page('App\Modules\Admin\Views\role', 'Role details', ['role' => service('admin')->role($id),
    'permissions' => service('admin')->permissionCatalog()]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(404)->setBody(esc($this->adminSafe($e)));
        }
    }
    public function editRolePage(int $id): string
    {
        try {
            return $this->page('App\Modules\Admin\Views\role_form', 'Edit role', ['mode' => 'edit', 'role' => service('admin')->role($id)]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(404)->setBody(esc($this->adminSafe($e)));
        }
    }
    public function updateRole(int $id): ResponseInterface|string
    {
        try {
            service('admin')->updateRole($id, (string) $this->request->getPost('name'));
            return redirect()->to('/admin/roles/' . $id)->with('message', 'Role updated.');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->adminSafe($e));
        }
    }
    public function assignPermission(int $id): ResponseInterface|string
    {
        try {
            if ((string) $this->request->getPost('permission_sync') === '1') {
                service('admin')->syncPermissions(
                    $id,
                    $this->request->getPost('permissions') ?? [],
                    (string) $this->request->getPost('expected_permission_version'),
                );

                return redirect()
                    ->to(site_url('admin/roles/' . $id))
                    ->with('message', 'Role permissions updated.');
            }

            service('admin')->assignPermission(
                $id,
                (string) $this->request->getPost('permission'),
                (string) $this->request->getPost('assign') === '1',
            );

            return redirect()
                ->to(site_url('admin/roles/' . $id))
                ->with('message', 'Permission assignment updated.');
        } catch (Throwable $e) {
            return redirect()
                ->to(site_url('admin/roles/' . $id))
                ->withInput()
                ->with('error', $this->adminSafe($e));
        }
    }
    public function permissions(): string
    {
        return $this->page('App\Modules\Admin\Views\permissions', 'Permissions', ['permissions' => service('admin')->permissionCatalog()]);
    }
    public function auditLogs(): string
    {
        try {
            return $this->page('App\Modules\Admin\Views\audit_logs', 'Audit logs',
    ['audit_logs' => service('auditLog')->listForAuthorizedScope(['branch_id' => $this->request->getGet('branch_id')])]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(403)->setBody('Permission denied.');
        }
    }
    public function permission(string $permission): string
    {
        $rows = service('admin')->permissionCatalog();
        foreach ($rows as $row) {
            if ($row['permission'] === $permission) {
                return $this->page('App\Modules\Admin\Views\permission', 'Permission details', ['permission' => $row]);
            }
        }
        return $this->response->setStatusCode(404)->setBody('Permission not found.');
    }
    private function adminSafe(Throwable $e): string
    {
        return match ($e->getMessage()) {
            'Forbidden' => 'You are not authorized to manage access.',
            'Not found.' => 'The requested account or role was not found.',
            'Self privilege changes are not allowed.' => 'Self privilege changes are not allowed.',
            'Branch is not authorized.' => 'That branch is outside your authorized scope.',
            'Email already exists.' => 'That email address is already in use.',
            'Invalid user details.' => 'Enter a valid name, email, and password of at least 16 characters.',
            'Invalid branch details.' => 'Enter a valid branch name and code.',
            'Branch already exists.' => 'That branch name or code is already in use.',
            'Unable to create branch.' => 'The branch could not be created.',
            'Invalid role name.', 'Invalid role.' => 'Enter a valid unique role name.',
            'Role already exists.' => 'That role already exists.',
            'Invalid permission.' => 'Select an approved permission.',
            'Invalid permission selection.' => 'Select approved permissions.',
            'Duplicate permission selection.' => 'Each permission can be selected only once.',
            'Role permissions changed. Reload and try again.' => 'The role permissions changed. Reload and try again.',
            'Role already assigned.', 'Permission already assigned.', 'Branch already assigned.' => 'That assignment already exists.',
            'Role is not assigned.', 'Permission is not assigned.', 'Branch is not assigned.' => 'That assignment does not exist.',
            'You cannot disable your own account.' => 'You cannot disable your own account.',
            'Invalid branch assignment.' => 'Select a valid branch.',
            'default' => $this->unexpectedFailure($e),
        };
    }

    private function branchSafe(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'Forbidden' => 'You are not authorized to manage branches in this context.',
            'Not found.' => 'The requested Branch was not found.',
            'Invalid branch details.' => 'Enter a valid Branch name, code, and address.',
            'Branch already exists.' => 'That Branch name or code is already in use.',
            default => $this->unexpectedFailure($exception),
        };
    }

    private function unexpectedFailure(Throwable $exception): string
    {
        $reference = 'ADM-' . strtoupper(bin2hex(random_bytes(4)));
        $detail = preg_replace(
            '/(?i)(password|token|secret|authorization|cookie)\s*[=:]\s*[^\s,;]+/',
            '$1=[redacted]',
            $exception->getMessage(),
        );
        $detail = is_string($detail)
            ? preg_replace("/'[^']*'/", "'[redacted]'", $detail)
            : $detail;

        log_message('error', 'Administration request failed [{reference}] {class}: {detail}', [
            'reference' => $reference,
            'class' => $exception::class,
            'detail' => $detail === null ? 'Exception detail unavailable.' : $detail,
        ]);

        return 'The access-management request could not be completed. Reference '
            . $reference
            . ' can be provided to support.';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function branchFieldErrors(array $input, Throwable $exception): array
    {
        if ($exception->getMessage() !== 'Invalid branch details.') {
            return [];
        }

        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Enter a branch name.';
        } elseif (strlen($name) > 120) {
            $errors['name'] = 'Branch name must be 120 characters or fewer.';
        }

        if ($code === '') {
            $errors['code'] = 'Enter a branch code.';
        } elseif (strlen($code) > 80) {
            $errors['code'] = 'Branch code must be 80 characters or fewer.';
        }

        if ($address === '') {
            $errors['address'] = 'Enter a branch address.';
        } elseif (strlen($address) > 255) {
            $errors['address'] = 'Branch address must be 255 characters or fewer.';
        }

        return $errors;
    }
}
