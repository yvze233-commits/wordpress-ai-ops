<?php

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImageLibraryController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SkillController;
use App\Http\Controllers\Admin\TitleLibraryController;
use App\Http\Controllers\Admin\TopicSourceController;
use App\Http\Controllers\Admin\WordPressConnectionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/admin', [DashboardController::class, 'index'])->name('admin.dashboard');
Route::get('/admin/batches', [BatchController::class, 'index'])->name('admin.batches.index');
Route::get('/admin/batches/{batch}', [BatchController::class, 'show'])->name('admin.batches.show');
Route::get('/admin/sources', [TopicSourceController::class, 'index'])->name('admin.sources.index');
Route::get('/admin/titles', [TitleLibraryController::class, 'index'])->name('admin.titles.index');
Route::get('/admin/knowledge', [KnowledgeController::class, 'index'])->name('admin.knowledge.index');
Route::get('/admin/images', [ImageLibraryController::class, 'index'])->name('admin.images.index');
Route::get('/admin/wordpress', [WordPressConnectionController::class, 'index'])->name('admin.wordpress.index');
Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
Route::post('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
Route::get('/admin/settings/models', [SettingsController::class, 'models'])->name('admin.settings.models');
Route::post('/admin/settings/ai-role', [SettingsController::class, 'saveRole'])->name('admin.settings.ai-role.save');
Route::post('/admin/settings/connections', [SettingsController::class, 'storeConnection'])->name('admin.settings.connections.store');
Route::post('/admin/settings/routing', [SettingsController::class, 'updateRouting'])->name('admin.settings.routing.update');
Route::prefix('admin/skills')->name('admin.skills.')->group(function (): void {
    Route::get('/', [SkillController::class, 'index'])->name('index');
    Route::post('/', [SkillController::class, 'store'])->name('store');
    Route::post('/{skill}/enable', [SkillController::class, 'enable'])->name('enable');
    Route::post('/{skill}/disable', [SkillController::class, 'disable'])->name('disable');
    Route::post('/{skill}/versions', [SkillController::class, 'version'])->name('version');
});
Route::prefix('admin/reviews')->name('admin.reviews.')->group(function (): void {
    Route::get('/', [ReviewController::class, 'index'])->name('index');
    Route::get('/{contentItem}', [ReviewController::class, 'show'])->name('show');
    Route::post('/{contentItem}/approve', [ReviewController::class, 'approve'])->name('approve');
    Route::post('/{contentItem}/manual', [ReviewController::class, 'requestManual'])->name('manual');
    Route::post('/{contentItem}/write-draft', [ReviewController::class, 'writeDraft'])->name('write-draft');
});
