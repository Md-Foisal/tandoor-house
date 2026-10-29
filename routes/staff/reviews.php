<?php

use App\Http\Controllers\Staff\Reviews\ReviewBoardController;
use App\Http\Controllers\Staff\Reviews\ReviewModerationController;
use App\Http\Controllers\Staff\Reviews\ReviewReplyController;
use Illuminate\Support\Facades\Route;

Route::prefix('reviews')->name('reviews.')->group(function () {
    Route::get('/', [ReviewBoardController::class, 'index'])->name('index');
    Route::post('publish', [ReviewModerationController::class, 'bulkPublish'])->name('bulk-publish');
    Route::post('{review}/publish', [ReviewModerationController::class, 'publish'])->name('publish');
    Route::post('{review}/hide', [ReviewModerationController::class, 'hide'])->name('hide');
    Route::put('{review}/reply', [ReviewReplyController::class, 'update'])->name('reply.update');
    Route::delete('{review}/reply', [ReviewReplyController::class, 'destroy'])->name('reply.destroy');
});
