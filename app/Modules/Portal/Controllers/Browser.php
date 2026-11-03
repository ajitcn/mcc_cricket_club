<?php

declare(strict_types=1);

namespace App\Modules\Portal\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

final class Browser extends BaseController
{
    public function loginPage(
        ?string $developmentOtp = null,
        ?string $loginType = null,
        ?string $mobile = null
    ): string
    {
        $resolvedLoginType = $loginType ?? old('login_type', 'student');
        $resolvedMobile = $mobile ?? old('mobile', '');
        $otpRequested = service('portalAuth')->hasPendingOtp($resolvedLoginType, $resolvedMobile);

        return view('App\\Modules\\Portal\\Views\\login', [
            'message' => session()->getFlashdata('message'),
            'error' => session()->getFlashdata('error'),
            'loginType' => $resolvedLoginType,
            'mobile' => $resolvedMobile,
            'otpRequested' => $otpRequested,
            'developmentOtp' => $developmentOtp,
        ]);
    }

    public function login(): ResponseInterface|string
    {
        $type = strtolower(trim((string) $this->request->getPost('login_type')));
        $mobile = trim((string) $this->request->getPost('mobile'));
        $action = strtolower(trim((string) $this->request->getPost('action')));
        try {
            if ($action === 'verify') {
                $authenticated = service('portalAuth')->verifyOtp($type, $mobile, (string) $this->request->getPost('otp'));
                if (! $authenticated) {
                    return redirect()->to('/student-login')->withInput()->with('error', 'The OTP is invalid or expired.');
                }
                session()->remove('portal_otp_pending');
                return redirect()->to($type === 'parent' ? '/parent-dashboard' : '/student-dashboard');
            }

            $developmentOtp = service('portalAuth')->requestOtp($type, $mobile);
            if ($developmentOtp !== null) {
                return $this->loginPage($developmentOtp, $type, $mobile);
            }
            return redirect()->to('/student-login')->withInput()->with('message', 'If the mobile number is eligible, an OTP has been sent.');
        } catch (RuntimeException $exception) {
            if (! in_array($exception->getMessage(), [
                'PORTAL_OTP_STORAGE_UNAVAILABLE',
                'PORTAL_OTP_DELIVERY_FAILED',
                'PORTAL_OTP_COOLDOWN',
                'Invalid mobile number.',
                'Invalid portal login type.',
            ], true)) {
                throw $exception;
            }

            return redirect()->to('/student-login')
                ->withInput()
                ->with('error', match ($exception->getMessage()) {
                    'Invalid mobile number.' => 'Enter a valid mobile number.',
                    'Invalid portal login type.' => 'Select Student or Parent.',
                    'PORTAL_OTP_COOLDOWN' => 'Please wait before requesting another OTP.',
                    default => 'If the mobile number is eligible, an OTP will be sent shortly.',
                });
        }
    }

    public function logout(): ResponseInterface
    {
        service('portalAuth')->logout();
        return redirect()->to('/student-login')->with('message', 'You have been signed out.');
    }

    public function studentDashboard(): string|ResponseInterface
    {
        if (strtolower((string) session('portal_account_type')) !== 'student') {
            return redirect()->to('/parent-dashboard');
        }

        return view('App\\Modules\\Portal\\Views\\dashboard', [
            'title' => 'Student dashboard',
            'portalType' => 'Student',
            'dashboard' => service('portalAuth')->dashboard(),
        ]);
    }

    public function parentDashboard(): string|ResponseInterface
    {
        if (strtolower((string) session('portal_account_type')) !== 'parent') {
            return redirect()->to('/student-dashboard');
        }

        return view('App\\Modules\\Portal\\Views\\dashboard', [
            'title' => 'Parent dashboard',
            'portalType' => 'Parent',
            'dashboard' => service('portalAuth')->dashboard(),
        ]);
    }

    public function studentProfile(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'profile', '/student-dashboard');
    }

    public function studentAcademy(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'academy', '/student-dashboard');
    }

    public function studentCoach(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'coach', '/student-dashboard');
    }

    public function studentSchedule(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'schedule', '/student-dashboard');
    }

    public function studentMatches(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'matches', '/student-dashboard');
    }

    public function studentTournaments(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'tournaments', '/student-dashboard');
    }

    public function studentAttendance(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'attendance', '/student-dashboard');
    }

    public function studentDevelopment(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'development', '/student-dashboard');
    }

    public function studentDocuments(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'documents', '/student-dashboard');
    }

    public function studentNotices(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'notices', '/student-dashboard');
    }

    public function studentProgress(): string|ResponseInterface
    {
        return $this->portalSection('Student', 'progress', '/student-dashboard');
    }

    public function parentProfile(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'profile', '/parent-dashboard');
    }

    public function parentAcademy(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'academy', '/parent-dashboard');
    }

    public function parentCoach(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'coach', '/parent-dashboard');
    }

    public function parentSchedule(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'schedule', '/parent-dashboard');
    }

    public function parentMatches(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'matches', '/parent-dashboard');
    }

    public function parentTournaments(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'tournaments', '/parent-dashboard');
    }

    public function parentAttendance(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'attendance', '/parent-dashboard');
    }

    public function parentDevelopment(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'development', '/parent-dashboard');
    }

    public function parentDocuments(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'documents', '/parent-dashboard');
    }

    public function parentNotices(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'notices', '/parent-dashboard');
    }

    public function parentProgress(): string|ResponseInterface
    {
        return $this->portalSection('Parent', 'progress', '/parent-dashboard');
    }

    private function portalSection(string $portalType, string $page, string $fallback): string|ResponseInterface
    {
        if (strtolower((string) session('portal_account_type')) !== strtolower($portalType)) {
            return redirect()->to($fallback);
        }

        return view('App\\Modules\\Portal\\Views\\dashboard', [
            'title' => $portalType . ' ' . ucfirst($page),
            'portalType' => $portalType,
            'portalPage' => $page,
            'dashboard' => service('portalAuth')->dashboard(),
        ]);
    }

    public function studentAchievements(): string|ResponseInterface
    {
        if (strtolower((string) session('portal_account_type')) !== 'student') {
            return redirect()->to('/student-dashboard');
        }

        return $this->portalAchievementsPage('Student');
    }

    public function parentAchievements(): string|ResponseInterface
    {
        if (strtolower((string) session('portal_account_type')) !== 'parent') {
            return redirect()->to('/parent-dashboard');
        }

        return $this->portalAchievementsPage('Parent');
    }

    private function portalAchievementsPage(string $portalType): string
    {
        return view('App\\Modules\\Portal\\Views\\dashboard', [
            'title' => $portalType . ' achievements',
            'portalType' => $portalType,
            'portalPage' => 'achievements',
            'dashboard' => service('portalAuth')->dashboard(),
            'portalAwards' => service('portalAuth')->publishedAchievements(),
        ]);
    }
}
