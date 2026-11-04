<?php

declare(strict_types=1);

namespace App\Modules\Equipment\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string|ResponseInterface
    {
        try {
            return $this->page(
                'App\\Modules\\Equipment\\Views\\index',
                'Equipment',
                [
                    'equipment' => service('equipment')->list(),
                    'canUpdate' => service('authorization')->can('equipment.update'),
                    'canDelete' => service('authorization')->can('equipment.delete'),
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function assignments(): string|ResponseInterface
    {
        try {
            return $this->page('App\\Modules\\Equipment\\Views\\assignments', 'Equipment assignments', [
                'assignments' => service('equipment')->assignments($this->request->getGet()),
                'filters' => $this->request->getGet(),
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function assignmentPage(): string|ResponseInterface
    {
        try {
            return $this->page(
                'App\\Modules\\Equipment\\Views\\assignment_form',
                'Assign equipment',
                [
                    'options' => service('equipment')->assignmentOptions(),
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function editAssignmentPage(int $id): string|ResponseInterface
    {
        try {
            $assignment = service('equipment')->findAssignmentForUpdate($id);

            return $this->page(
                'App\\Modules\\Equipment\\Views\\assignment_form',
                'Edit equipment assignment',
                [
                    'options' => service('equipment')->assignmentOptions(),
                    'assignment' => $assignment,
                    'isEdit' => true,
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function viewAssignmentPage(int $id): string|ResponseInterface
    {
        try {
            return $this->page(
                'App\\Modules\\Equipment\\Views\\assignment_view',
                'View equipment assignment',
                [
                    'assignment' => service('equipment')->findAssignment($id),
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function returnAssignmentPage(int $id): string|ResponseInterface
    {
        try {
            return $this->page(
                'App\\Modules\\Equipment\\Views\\return_form',
                'Record equipment return',
                [
                    'assignment' => service('equipment')->findAssignment($id),
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function createAssignment(): ResponseInterface|string
    {
        try {
            service('equipment')->createAssignment($this->request->getPost());
            return redirect()->to('/equipment/assignments')->with('message', 'Equipment assigned.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->errorMessage($exception));
        }
    }

    public function updateAssignment(int $id): ResponseInterface|string
    {
        try {
            service('equipment')->updateAssignment($id, $this->request->getPost());

            return redirect()->to('/equipment/assignments')->with('message', 'Equipment assignment updated.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->errorMessage($exception));
        }
    }

    public function returnAssignment(int $id): ResponseInterface|string
    {
        try {
            service('equipment')->returnAssignment($id, $this->request->getPost());
            return redirect()->to('/equipment/assignments')->with('message', 'Equipment return recorded.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->errorMessage($exception));
        }
    }

    public function createPage(): string|ResponseInterface
    {
        try {
            $context = service('branchContext')->navigationState();
            $isSuperAdmin = service('branchContext')->isSuperAdmin();

            return $this->page(
                'App\\Modules\\Equipment\\Views\\create',
                'Create equipment',
                [
                    'branches' => $isSuperAdmin
                        ? service('equipment')->branchesForSuperAdmin()
                        : [],
                    'isSuperAdmin' => $isSuperAdmin,
                    'activeBranchId' => $context['active_branch_id'],
                    'activeBranchName' => $isSuperAdmin
                        ? null
                        : service('equipment')->activeBranchNameForBranchAdmin(),
                    'canCreate' => $isSuperAdmin
                        ? service('equipment')->hasAuthorizedBranchForCreation()
                        : $context['active_branch_id'] !== null,
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function create(): ResponseInterface|string
    {
        $input = $this->request->getPost();
        $rules = [
            'name' => 'required|max_length[150]',
            'quantity' => 'required|is_natural_no_zero',
        ];

        if (service('branchContext')->isSuperAdmin()) {
            $rules['branch_id'] = 'required|is_natural_no_zero';
        }

        if (! $this->validateData($input, $rules)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Enter a name, a positive quantity, and a valid authorized branch.');
        }

        try {
            service('equipment')->create($input);

            return redirect()->to('/equipment')
                ->with('message', 'Equipment record created.');
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->errorMessage($exception));
        }
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Equipment\\Views\\create',
                'Edit equipment',
                [
                    'equipment' => service('equipment')->findForUpdate($id),
                    'isEdit' => true,
                    'isSuperAdmin' => service('branchContext')->isSuperAdmin(),
                    'canCreate' => true,
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function update(int $id): ResponseInterface|string
    {
        if (! $this->validateData($this->request->getPost(), [
            'name' => 'required|max_length[150]',
            'quantity' => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Enter a name and a positive quantity.');
        }

        try {
            service('equipment')->update($id, $this->request->getPost());

            return redirect()->to('/equipment')
                ->with('message', 'Equipment record updated.');
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->errorMessage($exception));
        }
    }

    public function deletePage(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Equipment\\Views\\delete',
                'Delete equipment',
                ['equipment' => service('equipment')->findForDelete($id)]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function delete(int $id): ResponseInterface|string
    {
        try {
            service('equipment')->softDelete($id);

            return redirect()->to('/equipment')
                ->with('message', 'Equipment record deleted.');
        } catch (Throwable $exception) {
            return redirect()->to('/equipment')
                ->with('error', $this->errorMessage($exception));
        }
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        return $this->response
            ->setStatusCode(match ($exception->getMessage()) {
                'Forbidden' => 403,
                'Not found.' => 404,
                default => 500,
            })
            ->setBody(esc($this->errorMessage($exception)));
    }

    private function errorMessage(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'Forbidden' => 'You are not authorized to manage equipment in this branch.',
            'Branch selection required.' => 'Select an active branch in the shared navigation before creating equipment.',
            'No authorized branch.' => 'No active authorized branch is available for this account.',
            'Invalid branch selection.' => 'Select a branch you are authorized to manage.',
            'Not found.' => 'The equipment record was not found in your authorized branch scope.',
            'Invalid equipment.' => 'Enter a name and a positive whole quantity within the supported range.',
            'Invalid equipment assignment.' => 'Choose at least one equipment item and enter a valid quantity.',
            'Unable to record assigned equipment.' => 'The equipment assignment could not be recorded. Please try again.',
            default => 'The equipment request could not be completed.',
        };
    }
}
