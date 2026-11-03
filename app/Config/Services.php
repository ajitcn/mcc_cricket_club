<?php

declare (strict_types=1);
namespace Config;

use App\Services\AdmissionService;
use App\Services\AttendanceService;
use App\Services\AuditPersistenceService;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\CrmService;
use App\Services\RbacService;
use App\Services\PlayerService;
use App\Services\SchedulingService;
use App\Services\AdminService;
use App\Services\DashboardService;
use App\Services\TrialService;
use App\Services\TrialMediaService;
use App\Services\NavigationService;
use App\Services\CoachManagementService;
use App\Services\FitnessManagementService;
use App\Services\GroundManagementService;
use App\Services\MatchManagementService;
use App\Services\MatchPlayerPerformanceService;
use App\Services\TournamentManagementService;
use App\Services\PlayerDevelopmentService;
use App\Services\PlayerSessionPlanService;
use App\Services\PlayerFeedbackService;
use App\Services\ParentPortalService;
use App\Services\PortalAuthService;
use App\Services\ConfiguredWhatsAppOtpProvider;
use App\Services\MockWhatsAppOtpProvider;
use App\Services\PortalOtpDebugService;
use App\Services\DocumentManagementService;
use App\Services\ReportService;
use App\Services\NotificationService;
use App\Services\MarketingDashboardService;
use App\Services\AchievementManagementService;
use App\Services\AchievementCertificateService;
use App\Services\AchievementEligibilityService;
use App\Services\AchievementReportService;
use App\Services\Player360Service;
use App\Services\PerformanceService;
use App\Services\BranchContextService;
use App\Services\EquipmentService;
use App\Services\ProgramManagementService;
use App\Services\BatchManagementService;
use App\Services\AdmissionMasterService;
use App\Services\AdmissionFollowUpService;
use App\Modules\MeetingFollowUps\Services\MeetingFollowUpService;
use App\Services\AdmissionInstallmentTrackingService;
use App\Services\AdmissionInstallmentPaymentService;
use App\Services\AdmissionPdfService;
use App\Modules\AdmissionTests\Services\AdmissionTestService;
use App\Services\OpponentManagementService;
use App\Services\RecoveryDeliveryInterface;
use App\Services\RecoveryRateLimitInterface;
use App\Services\DatabaseRecoveryRateLimiter;
use App\Services\SmtpRecoveryDelivery;
use CodeIgniter\Config\BaseService;
final class Services extends BaseService
{
    public static function auth(bool $getShared = true): AuthService
    {
        return $getShared ? static::getSharedInstance('auth') : new AuthService();
    }
    public static function recoveryRateLimiter(bool $getShared = true): RecoveryRateLimitInterface
    {
        return $getShared ? static::getSharedInstance('recoveryRateLimiter') : new DatabaseRecoveryRateLimiter();
    }
    public static function authorization(bool $getShared = true): AuthorizationService
    {
        return $getShared ? static::getSharedInstance('authorization') : new AuthorizationService();
    }
    public static function auditPersistence(bool $getShared = true): AuditPersistenceService
    {
        return $getShared ? static::getSharedInstance('auditPersistence') : new AuditPersistenceService();
    }
    public static function auditLog(bool $getShared = true): AuditLogService
    {
        return $getShared ? static::getSharedInstance('auditLog') : new AuditLogService();
    }
    public static function recoveryDelivery(bool $getShared = true): RecoveryDeliveryInterface
    {
        return $getShared ? static::getSharedInstance('recoveryDelivery') : new SmtpRecoveryDelivery();
    }
    public static function crm(bool $getShared = true): CrmService
    {
        return $getShared ? static::getSharedInstance('crm') : new CrmService();
    }
    public static function admission(bool $getShared = true): AdmissionService
    {
        return $getShared ? static::getSharedInstance('admission') : new AdmissionService();
    }

    public static function admissionTest(bool $getShared = true): AdmissionTestService
    {
        return $getShared
            ? static::getSharedInstance('admissionTest')
            : new AdmissionTestService();
    }

    public static function meetingFollowUp(bool $getShared = true): MeetingFollowUpService
    {
        return $getShared ? static::getSharedInstance('meetingFollowUp') : new MeetingFollowUpService();
    }

    public static function admissionPdf(bool $getShared = true): AdmissionPdfService
    {
        return $getShared
            ? static::getSharedInstance('admissionPdf')
            : new AdmissionPdfService();
    }
    public static function rbac(bool $getShared = true): RbacService
    {
        return $getShared ? static::getSharedInstance('rbac') : new RbacService();
    }
    public static function player(bool $getShared = true): PlayerService
    {
        return $getShared ? static::getSharedInstance('player') : new PlayerService();
    }

    public static function playerSessionPlan(bool $getShared = true): PlayerSessionPlanService
    {
        return $getShared
            ? static::getSharedInstance('playerSessionPlan')
            : new PlayerSessionPlanService();
    }

    public static function playerFeedback(bool $getShared = true): PlayerFeedbackService
    {
        return $getShared
            ? static::getSharedInstance('playerFeedback')
            : new PlayerFeedbackService();
    }
    public static function scheduling(bool $getShared = true): SchedulingService
    {
        return $getShared ? static::getSharedInstance('scheduling') : new SchedulingService();
    }
    public static function attendance(bool $getShared = true): AttendanceService
    {
        return $getShared ? static::getSharedInstance('attendance') : new AttendanceService();
    }
    public static function admin(bool $getShared = true): AdminService
    {
        return $getShared ? static::getSharedInstance('admin') : new AdminService();
    }
    public static function dashboard(bool $getShared = true): DashboardService
    {
        return $getShared ? static::getSharedInstance('dashboard') : new DashboardService();
    }
    public static function trial(bool $getShared = true): TrialService
    {
        return $getShared ? static::getSharedInstance('trial') : new TrialService();
    }

    public static function trialMedia(bool $getShared = true): TrialMediaService
    {
        return $getShared
            ? static::getSharedInstance('trialMedia')
            : new TrialMediaService();
    }
    public static function navigation(bool $getShared = true): NavigationService
    {
        return $getShared ? static::getSharedInstance('navigation') : new NavigationService();
    }
    public static function coach(bool $getShared = true): CoachManagementService
    {
        return $getShared ? static::getSharedInstance('coach') : new CoachManagementService();
    }

    public static function fitness(bool $getShared = true): FitnessManagementService
    {
        return $getShared ? static::getSharedInstance('fitness') : new FitnessManagementService();
    }

    public static function ground(bool $getShared = true): GroundManagementService
    {
        return $getShared ? static::getSharedInstance('ground') : new GroundManagementService();
    }

    public static function branchContext(bool $getShared = true): BranchContextService
    {
        return $getShared
            ? static::getSharedInstance('branchContext')
            : new BranchContextService();
    }

    public static function equipment(bool $getShared = true): EquipmentService
    {
        return $getShared
            ? static::getSharedInstance('equipment')
            : new EquipmentService();
    }

    public static function program(bool $getShared = true): ProgramManagementService
    {
        return $getShared
            ? static::getSharedInstance('program')
            : new ProgramManagementService();
    }

    public static function batch(bool $getShared = true): BatchManagementService
    {
        return $getShared
            ? static::getSharedInstance('batch')
            : new BatchManagementService();
    }

    public static function admissionMaster(bool $getShared = true): AdmissionMasterService
    {
        return $getShared
            ? static::getSharedInstance('admissionMaster')
            : new AdmissionMasterService();
    }

    public static function admissionFollowUp(bool $getShared = true): AdmissionFollowUpService
    {
        return $getShared
            ? static::getSharedInstance('admissionFollowUp')
            : new AdmissionFollowUpService();
    }

    public static function admissionInstallments(bool $getShared = true): AdmissionInstallmentTrackingService
    {
        return $getShared
            ? static::getSharedInstance('admissionInstallments')
            : new AdmissionInstallmentTrackingService();
    }

    public static function admissionInstallmentPayment(bool $getShared = true): AdmissionInstallmentPaymentService
    {
        return $getShared
            ? static::getSharedInstance('admissionInstallmentPayment')
            : new AdmissionInstallmentPaymentService();
    }

    public static function opponent(bool $getShared = true): OpponentManagementService
    {
        return $getShared
            ? static::getSharedInstance('opponent')
            : new OpponentManagementService();
    }

    public static function achievement(bool $getShared = true): AchievementManagementService
    {
        return $getShared
            ? static::getSharedInstance('achievement')
            : new AchievementManagementService();
    }

    public static function achievementCertificate(bool $getShared = true): AchievementCertificateService
    {
        return $getShared
            ? static::getSharedInstance('achievementCertificate')
            : new AchievementCertificateService();
    }

    public static function achievementReport(bool $getShared = true): AchievementReportService
    {
        return $getShared
            ? static::getSharedInstance('achievementReport')
            : new AchievementReportService();
    }

    public static function achievementEligibility(bool $getShared = true): AchievementEligibilityService
    {
        return $getShared
            ? static::getSharedInstance('achievementEligibility')
            : new AchievementEligibilityService();
    }

    public static function match(bool $getShared = true): MatchManagementService
    {
        return $getShared ? static::getSharedInstance('match') : new MatchManagementService();
    }

    public static function matchPlayerPerformance(bool $getShared = true): MatchPlayerPerformanceService
    {
        return $getShared
            ? static::getSharedInstance('matchPlayerPerformance')
            : new MatchPlayerPerformanceService();
    }

    public static function tournament(bool $getShared = true): TournamentManagementService
    {
        return $getShared
            ? static::getSharedInstance('tournament')
            : new TournamentManagementService();
    }

    public static function development(bool $getShared = true): PlayerDevelopmentService
    {
        return $getShared
            ? static::getSharedInstance('development')
            : new PlayerDevelopmentService();
    }

    public static function parentPortal(bool $getShared = true): ParentPortalService
    {
        return $getShared ? static::getSharedInstance('parentPortal') : new ParentPortalService();
    }

    public static function portalAuth(bool $getShared = true): PortalAuthService
    {
        $debug = new PortalOtpDebugService();
        $database = Database::connect('default');
        $provider = strtolower(trim((string) env('PORTAL_OTP_PROVIDER'))) === 'mock'
            ? new MockWhatsAppOtpProvider($database, $debug)
            : new ConfiguredWhatsAppOtpProvider();

        return $getShared
            ? static::getSharedInstance('portalAuth')
            : new PortalAuthService($provider, $debug);
    }

    public static function document(bool $getShared = true): DocumentManagementService
    {
        return $getShared ? static::getSharedInstance('document') : new DocumentManagementService();
    }

    public static function report(bool $getShared = true): ReportService
    {
        return $getShared ? static::getSharedInstance('report') : new ReportService();
    }

    public static function notification(bool $getShared = true): NotificationService
    {
        return $getShared ? static::getSharedInstance('notification') : new NotificationService();
    }

    public static function marketing(bool $getShared = true): MarketingDashboardService
    {
        return $getShared
            ? static::getSharedInstance('marketing')
            : new MarketingDashboardService();
    }

    public static function player360(bool $getShared = true): Player360Service
    {
        return $getShared
            ? static::getSharedInstance('player360')
            : new Player360Service();
    }

    public static function performance(bool $getShared = true): PerformanceService
    {
        return $getShared ? static::getSharedInstance('performance') : new PerformanceService();
    }
}
