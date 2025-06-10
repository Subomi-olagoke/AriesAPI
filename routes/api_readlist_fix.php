    // Readlist routes with proper UUID support
    // Routes with ID parameters (must come AFTER more specific routes to avoid conflicts)
    Route::get('/readlists/{id}', [ReadlistController::class, 'show']);
    Route::get('/readlist/{id}', [ReadlistController::class, 'show']);
    Route::put('/readlists/{id}', [ReadlistController::class, 'update']);
    Route::put('/readlist/{id}', [ReadlistController::class, 'update']);
    Route::delete('/readlists/{id}', [ReadlistController::class, 'destroy']);
    Route::delete('/readlist/{id}', [ReadlistController::class, 'destroy']);

    // Readlist item management routes
    Route::post('/readlists/{id}/items', [ReadlistController::class, 'addItem']);
    Route::post('/readlist/{id}/items', [ReadlistController::class, 'addItem']);
    Route::post('/readlist/{id}/item', [ReadlistController::class, 'addItem']);
    Route::post('/readlists/{id}/item', [ReadlistController::class, 'addItem']);
    Route::post('/readlists/{id}/urls', [ReadlistController::class, 'addUrl']);
    Route::post('/readlist/{id}/urls', [ReadlistController::class, 'addUrl']);
    Route::post('/readlist/{id}/url', [ReadlistController::class, 'addUrl']);
    Route::post('/readlists/{id}/url', [ReadlistController::class, 'addUrl']);
    Route::delete('/readlists/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::delete('/readlist/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::delete('/readlist/{id}/item/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::delete('/readlists/{id}/item/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::post('/readlists/{id}/reorder', [ReadlistController::class, 'reorderItems']);
    Route::post('/readlist/{id}/reorder', [ReadlistController::class, 'reorderItems']);
    Route::post('/readlists/{id}/regenerate-key', [ReadlistController::class, 'regenerateShareKey']);
    Route::post('/readlist/{id}/regenerate-key', [ReadlistController::class, 'regenerateShareKey']);