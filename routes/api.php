<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MovementController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectWorkflowController;
use App\Http\Controllers\Api\ProjectAnalyticsController;
use App\Http\Controllers\Api\ProjectExpenseController;
use App\Http\Controllers\Api\ProjectInvitationController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskV1Controller;
use App\Http\Controllers\Api\WorkLogController;
use App\Http\Controllers\Api\FinancialReportController;
use App\Http\Controllers\Api\ParameterController;
use App\Http\Controllers\Api\ProjectConfigurationController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\FirstAccessController;
use App\Http\Controllers\Api\AccountSecurityController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\HomeDashboardController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ProjectDocumentController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\ManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// API principal da SPA. Usa a sessao web protegida por Sanctum e CSRF.
Route::prefix('v1')->name('v1.')->middleware(['web', 'throttle:60,1'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'loginSession'])->middleware(['guest', 'throttle:5,1']);
    });
    Route::post('first-access/{token}', [FirstAccessController::class, 'activate'])->middleware(['guest', 'throttle:5,1'])->name('first-access.activate');
    Route::post('first-access', [FirstAccessController::class, 'requestLink'])->middleware(['guest', 'throttle:3,10'])->name('first-access.request');
    Route::post('invitations/{token}/accept', [ProjectInvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logoutSession']);
            Route::get('me', [AuthController::class, 'meSession']);
            Route::get('me/projects', [AuthController::class, 'myProjects']);
            Route::get('timezones', [AuthController::class, 'timezones']);
        });

        Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('products', ProductController::class);
        Route::get('movements', [MovementController::class, 'index']);
        Route::post('movements', [MovementController::class, 'store']);
        Route::get('dashboard', [MovementController::class, 'dashboard']);
        Route::post('projects/{project}/finalize', [ProjectWorkflowController::class, 'finalize'])->name('projects.finalize');
        Route::post('projects/{project}/reopen', [ProjectWorkflowController::class, 'reopen'])->name('projects.reopen');
        Route::delete('projects/{project}', [ProjectWorkflowController::class, 'destroy'])->name('projects.governed-destroy');
        Route::get('projects/{project}/expenses', [ProjectExpenseController::class, 'index'])->name('projects.expenses.index');
        Route::post('projects/{project}/expenses', [ProjectExpenseController::class, 'store'])->name('projects.expenses.store');
        Route::post('expenses/{expense}/review', [ProjectExpenseController::class, 'review'])->name('expenses.review');
        Route::get('projects/{project}/work-logs', [WorkLogController::class, 'index'])->name('projects.work-logs.index');
        Route::post('projects/{project}/work-logs', [WorkLogController::class, 'store'])->name('projects.work-logs.store');
        Route::post('projects/{project}/invitations', [ProjectInvitationController::class, 'store'])->name('projects.invitations.store');
        Route::post('invitations/{invitation}/revoke', [ProjectInvitationController::class, 'revoke'])->name('invitations.revoke');
        Route::get('reports/portfolio', [FinancialReportController::class, 'portfolio'])->name('reports.portfolio');
        Route::get('reports/project-analytics', ProjectAnalyticsController::class)->name('reports.project-analytics');
        Route::get('projects/{project}/financial-result', [FinancialReportController::class, 'show'])->name('projects.financial-result');
        // Administração operacional: diretório, parâmetros e ajustes financeiros.
        Route::get('management/directory', [DirectoryController::class, 'index']);
        Route::post('management/directory/import', [DirectoryController::class, 'import']);
        Route::get('management/access', [ManagementController::class, 'access']);
        Route::get('management/parameters/{type}', [ManagementController::class, 'parameters']);
        Route::post('management/parameters/{type}', [ManagementController::class, 'saveParameter']);
        Route::put('management/parameters/{type}/{id}', [ManagementController::class, 'saveParameter']);
        Route::delete('management/parameters/{type}/{id}', [ManagementController::class, 'deleteParameter']);
        Route::get('management/settings', [ManagementController::class, 'settings']);
        Route::put('management/settings', [ManagementController::class, 'saveSettings']);
        Route::get('management/users/{user}/permissions', [ManagementController::class, 'permissions']);
        Route::put('management/users/{user}/permissions', [ManagementController::class, 'savePermissions']);
        Route::put('rates/{rate}', [ManagementController::class, 'updateRate']);
        Route::delete('rates/{rate}', [ManagementController::class, 'deleteRate']);
        Route::put('expenses/{expense}', [ManagementController::class, 'updateExpense']);
        Route::delete('expenses/{expense}', [ManagementController::class, 'deleteExpense']);
        Route::delete('projects/{project}/members/{user}', [ManagementController::class, 'removeMember']);
        Route::get('parameters', [ParameterController::class, 'index'])->name('parameters.index');
        Route::get('admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::patch('admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::get('projects/{project}/configuration', [ProjectConfigurationController::class, 'show'])->name('projects.configuration.show');
        Route::post('projects/{project}/members', [ProjectConfigurationController::class, 'addMember'])->name('projects.members.store');
        Route::post('projects/{project}/rates', [ProjectConfigurationController::class, 'storeRate'])->name('projects.rates.store');
        Route::put('projects/{project}/taxes', [ProjectConfigurationController::class, 'replaceTaxes'])->name('projects.taxes.replace');
        Route::apiResource('projects', ProjectController::class)->except(['destroy']);
        Route::apiResource('tasks', TaskV1Controller::class)->except(['show']);
        Route::prefix('security')->group(function () {
            Route::get('/', [AccountSecurityController::class, 'show']);
            Route::put('password', [AccountSecurityController::class, 'updatePassword'])->middleware('throttle:5,1');
            Route::put('preferences', [AccountSecurityController::class, 'updatePreferences']);
            Route::delete('providers/{provider}', [AccountSecurityController::class, 'disconnectProvider'])->middleware('throttle:5,1');
            Route::delete('sessions/{session}', [AccountSecurityController::class, 'revokeSession']);
            Route::delete('sessions', [AccountSecurityController::class, 'revokeOtherSessions']);
            Route::post('two-factor/setup', [AccountSecurityController::class, 'beginTwoFactor'])->middleware('throttle:5,1');
            Route::post('two-factor/confirm', [AccountSecurityController::class, 'confirmTwoFactor'])->middleware('throttle:5,1');
            Route::delete('two-factor', [AccountSecurityController::class, 'disableTwoFactor'])->middleware('throttle:5,1');
        });
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::delete('notification-preferences', [NotificationPreferenceController::class, 'reset']);
        Route::get('home-dashboard', [HomeDashboardController::class, 'show']);
        Route::patch('home-dashboard/tasks/{task}/toggle', [HomeDashboardController::class, 'toggleTask']);
        Route::get('inbox', [InboxController::class, 'index']);
        Route::patch('inbox/read-all', [InboxController::class, 'readAll']);
        Route::patch('inbox/{inboxItem}/read', [InboxController::class, 'read']);
        Route::delete('inbox/{inboxItem}', [InboxController::class, 'archive']);
        Route::get('calendar', [CalendarController::class, 'index']);
        Route::post('calendar/events', [CalendarController::class, 'store']);
        Route::get('documents', [ProjectDocumentController::class, 'index']);
        Route::post('documents', [ProjectDocumentController::class, 'store']);
        Route::get('documents/{document}/download', [ProjectDocumentController::class, 'download']);
        Route::delete('documents/{document}', [ProjectDocumentController::class, 'destroy']);
        Route::get('history', [HistoryController::class, 'index']);
    });
});

// Compatibilidade temporaria para consumidores JWT existentes.
Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('products', ProductController::class);
    Route::get('movements', [MovementController::class, 'index']);
    Route::post('movements', [MovementController::class, 'store']);
    Route::get('dashboard', [MovementController::class, 'dashboard']);

    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('tasks', TaskController::class)->except(['show']);
});
