<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

abstract class StaffController extends BaseAuthenticatedController
{
    protected function navigation(): array
    {
        return service('navigation')->staff();
    }

    protected function page(string $view, string $title, array $data = []): string
    {
        return view($view, array_merge([
            'title' => $title,
            'nav' => $this->navigation(),
            'message' => session()->getFlashdata('message'),
            'error' => session()->getFlashdata('error'),
        ], $data));
    }

    protected function browser(): bool
    {
        return ! str_contains(
            strtolower((string) $this->request->getHeaderLine('Content-Type')),
            'application/json',
        );
    }

    protected function redirectBack(string $fallback): ResponseInterface
    {
        return redirect()->to($this->request->getServer('HTTP_REFERER') ?: $fallback);
    }

    protected function safe(Throwable $e): string
    {
        if (str_contains($e->getMessage(), "Column 'location' cannot be null")) {
            return 'Trial location storage is not ready. Apply the nullable Trial location migration.';
        }

        return match ($e->getMessage()) {
            'Forbidden' => 'You are not authorized for this record.',
            'Not found.' => 'The requested record was not found.',
            'Update conflict.' => 'This record changed. Reload and try again.',
            'Invalid owner.' => 'Select an authorized owner.',
            'Invalid lead.' => 'Enter valid lead details.',
            'Lead is not eligible.' => 'This lead is not eligible for admission.',
            'Admission already exists.' => 'An admission already exists for this lead.',
            'Invalid player profile.' => 'Enter valid player profile details.',
            'Invalid admission details.' => 'Enter valid admission details.',
            'Invalid admission option.' => 'Select a valid admission option.',
            'Invalid Trial rating.' => 'Enter a rating from 0 to 10, including decimals, or leave it blank.',
            'Invalid status.' => 'Select a valid admission status.',
            'Batch and admission date are required.' => 'Batch and admission date are required.',
            'The requested record was not found.' => 'The requested record was not found.',
            'Completed development plans cannot be edited or extended.' => 'Completed development plans cannot be edited or extended.',
            'Invalid branch transfer.' => 'The branch transfer is invalid.',
            'Invalid assignment.' => 'The assignment is invalid.',
            'Invalid assignment details.' => 'Enter a valid assignment date and concurrency value.',
            'Assignment effective date must be later than the current assignment.' => 'Choose an effective date later than the current assignment.',
            'No active batch assignment exists to remove.' => 'There is no active batch assignment to remove.',
            'No active coach assignment exists to remove.' => 'There is no active coach assignment to remove.',
            'Batch is not in the player branch.' => 'Select a batch from the player branch.',
            'Coach is not assigned to the player branch.' => 'Select a coach assigned to the player branch.',
            'Coach is not active and enabled for the player branch.' => 'Select an active Coach assigned to the player branch.',
            'Duplicate active batch assignment.' => 'That batch is already assigned to this player.',
            'Duplicate active coach assignment.' => 'That coach is already assigned to this player.',
            'Invalid batch.' => 'Select a valid batch.',
            'Invalid program.' => 'Select a valid Program.',
            'Program already exists.' => 'A Program with this name already exists.',
            'Batch already exists.' => 'A Batch with this name already exists in this Branch.',
            'Invalid coach.' => 'Select an authorized coach.',
            'Invalid secondary coach.' => 'Select an authorized Secondary Coach / Evaluator.',
            'Primary and secondary coaches must be different.' => 'Select different primary and secondary Coaches / Evaluators.',
            'Only the Primary Coach can change the Secondary Coach.' => 'Only the assigned Primary Coach can change the Secondary Coach.',
            'Invalid contact.' => 'Enter valid contact details.',
            'Reason required.' => 'A reason is required.',
            'Admission is terminal.' => 'This admission is no longer editable.',
            'Admission is not approved.' => 'Only an approved admission can be converted.',
            'Invalid transition.' => 'That player status transition is not allowed.',
            'Invalid player goal.' => 'Enter a goal title and description within the allowed length.',
            'Coach is not assigned to this player.' => 'You can set goals only for Players assigned to you.',
            'Goal was changed by another user.' => 'This goal changed. Reload and try again.',
            'Invalid player session plan.' => 'Enter Problem, Outcome, and Method and keep each field within the allowed length.',
            'Invalid coaching tools.' => 'Choose valid Coaching Tools.',
            'Invalid coaching focus.' => 'Choose valid Coaching Focus options.',
            'A current session plan version is required.' => 'Reload this Session Plan before saving it again.',
            'Session plan was changed by another user.' => 'This Session Plan changed. Reload and try again.',
            'Invalid player feedback.' => 'Enter valid feedback details within the allowed length.',
            'Player is not eligible.' => 'Select a valid Player.',
            'Feedback was changed by another user.' => 'This feedback changed. Reload and try again.',
            'Correction reason required.' => 'A reason is required when correcting feedback.',
            'Correction reason is too long.' => 'Enter a correction reason no longer than 255 characters.',
            'Feedback already exists for this period.' => 'Feedback already exists for this quarterly period.',
            'Invalid feedback year.' => 'Select a valid calendar year.',
            'Admission branch mismatch.' => 'The admission record is invalid.',
            'A document file is required.' => 'Select a document file to upload.',
            'Invalid document upload.' => 'The document must be a valid PDF, JPEG, or PNG up to 10 MB.',
            'Profile photos must be JPEG or PNG images.' => 'Profile photos must be JPEG or PNG images.',
            'Executable or script content is not permitted.' => 'Executable or script content is not permitted.',
            'Invalid document expiry date.' => 'Enter a valid document expiry date.',
            'Invalid verification state.' => 'Select a valid verification state.',
            'Verification reason required.' => 'A verification reason is required when rejecting a document.',
            'Document scan unavailable.' => 'The document cannot be verified until scanning is available.',
            'Unable to save document verification.' => 'The document verification could not be saved.',
            'Admission is not eligible.' => 'The document is not eligible for this admission.',
            'Document is not eligible.' => 'The document is not eligible for this admission.',
            'Unable to remove document metadata.' => 'Unable to remove document metadata.',
            'Unable to remove private storage object.' => 'Unable to remove the private document file.',
            'Cancellation reason required.' => 'A cancellation reason is required.',
            'Invalid trial details.' => 'Enter a valid trial date, time, and optional location.',
            'Invalid trial date.' => 'Enter a valid trial date in DD-MM-YYYY format.',
            'Invalid trial time.' => 'Enter a valid trial time.',
            'Invalid trial location.' => 'Select a valid location for this branch, or leave it empty.',
            'Invalid trial remarks.' => 'Enter shorter trial remarks.',
            'Invalid report date filter.' => 'Enter valid report dates with From on or before To.',
            'A future trial is already booked.' => 'A future trial is already booked for this Lead.',
            'Lead is not eligible for a trial.' => 'This Lead is not eligible for a trial.',
            'Required lead status configuration is missing or inactive.' => 'Trial booking is unavailable because the required Lead status configuration is missing or inactive.',
            'Correction reason required.' => 'A correction reason is required.',
            'Correction reason is too long.' => 'Enter a correction reason no longer than 255 characters.',
            'Completed or cancelled follow-ups are immutable.' => 'Completed or cancelled follow-ups are immutable.',
            'Follow-up is no longer pending.' => 'This follow-up is no longer pending.',
            'Invalid follow-up outcome.' => 'Select a valid follow-up outcome.',
            'Invalid follow-up.' => 'Enter valid follow-up details.',
            'Invalid Admission follow-up.' => 'Enter valid Admission follow-up details.',
            'No new follow-up can be created after the Admission is completed or converted.' => 'No new follow-up can be created after the Admission is completed or converted.',
            'Invalid follow-up date.' => 'Enter a valid follow-up date and time.',
            'Invalid follow-up time.' => 'Enter a valid follow-up time.',
            'Invalid Admission Test status transition.' => 'This Admission Test is not ready for a Meeting Follow-up.',
            'Invalid assigned user.' => 'Select an authorized assigned user.',
            'Invalid Admission.' => 'Select an authorized Admission.',
            'Invalid Admission Test.' => 'Select an authorized Admission Test.',
            'Duplicate installment row.' => 'Each installment year can be submitted only once.',
            'Duplicate installment identifier.' => 'The same persisted installment row cannot be submitted twice.',
            'Invalid installment identifier.' => 'The selected installment row is invalid for this Admission Test.',
            'Invalid removed installment identifier.' => 'The selected removed installment row is invalid.',
            'Invalid Meeting Follow-up.' => 'Enter valid Meeting Follow-up details.',
            'Meeting end date must be after the start date.' => 'Meeting end date must be after the start date.',
            'Meeting Follow-up linkage cannot be changed.' => 'The Admission Test linkage cannot be changed.',
            'Unable to create Meeting Follow-up.' => 'The Meeting Follow-up could not be saved.',
            'Unable to save Meeting Follow-up.' => 'The Meeting Follow-up could not be saved.',
            default => 'The request could not be completed.',
        };
    }
}
