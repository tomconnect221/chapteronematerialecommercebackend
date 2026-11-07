<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\SubadminController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\DatabaseBackupController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/branches', [CustomerController::class, 'getBranches']);
Route::get('/categories', [CustomerController::class, 'getCategories']);
Route::get('/products', [CustomerController::class, 'getProducts']);
Route::get('/products/search', [SearchController::class, 'advancedProductSearch']);
Route::get('/products/{id}/related', [SearchController::class, 'relatedProducts']);
Route::get('/sliders', [AdminController::class, 'getSliders']);
Route::get('/ads', [AdminController::class, 'getActiveAds']);
Route::post('/{id}/click', [AdminController::class, 'trackAdClick']);
Route::get('/products/{id}', [CustomerController::class, 'getProduct']);
Route::get('/settings', [AdminController::class, 'getSettings']);

// Recently Viewed Products
Route::get('/recently-viewed', [CustomerController::class, 'getRecentlyViewed']);
Route::post('/products/{id}/view', [CustomerController::class, 'markProductViewed']);

// Rider Ratings (Public)
Route::get('/riders/{id}/ratings', [RatingController::class, 'getRiderRatings']);

// Public Ads Routes (no auth)
Route::get('/ads', [AdminController::class, 'getActiveAds']);
Route::get('/ads/{idOrPosition}', [AdminController::class, 'getAdByIdOrAdsByPosition']);

Route::post('/paystack/webhook', [PaystackWebhookController::class, 'handle']);

Route::prefix('search')->group(function () {
    Route::get('/global', [SearchController::class, 'globalSearch']);
    Route::get('/products/advanced', [SearchController::class, 'advancedProductSearch']);
    Route::get('/suggestions', [SearchController::class, 'getSuggestions']);
});

// ===========================
// 🔐 PASSWORD RESET ROUTES
// ===========================

// Request password reset link (unauthenticated)
Route::post('/auth/password/reset-request', [PasswordResetController::class, 'sendResetLink']);

// Handle password reset via email link (unauthenticated)
Route::post('/auth/password/reset', [PasswordResetController::class, 'resetPassword']);

// Change password while logged in (authenticated)
// Support all user types by checking all guards
Route::middleware('auth:api,customer-api,subadmin-api,rider-api')->post('/auth/password/change', [AuthController::class, 'resetPassword']);

// Admin routes
Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    // Database backup and restore
    Route::get('/database/backup', [DatabaseBackupController::class, 'download']);
    Route::post('/database/restore', [DatabaseBackupController::class, 'restore']);
    Route::post('/database/clear', [DatabaseBackupController::class, 'clear']);

    // Branch management
    Route::post('/branches', [AdminController::class, 'createBranch']);
    Route::get('/branches', [AdminController::class, 'getBranches']);
    Route::get('/branches/{id}', [AdminController::class, 'getBranch']);
    Route::put('/branches/{id}', [AdminController::class, 'updateBranch']);
    Route::delete('/branches/{id}', [AdminController::class, 'deleteBranch']);

    // Subadmin management
    Route::post('/subadmins', [AdminController::class, 'createSubadmin']);
    Route::get('/subadmins', [AdminController::class, 'getSubadmins']);
    Route::get('/subadmins/{id}', [AdminController::class, 'getSubadmin']);
    Route::put('/subadmins/{id}', [AdminController::class, 'updateSubadmin']);
    Route::delete('/subadmins/{id}', [AdminController::class, 'deleteSubadmin']);

    // Rider management
    Route::post('/riders', [AdminController::class, 'createRider']);
    Route::get('/riders', [AdminController::class, 'getRiders']);
    Route::get('/riders/{id}', [AdminController::class, 'getRider']);
    Route::put('/riders/{id}', [AdminController::class, 'updateRider']);
    Route::delete('/riders/{id}', [AdminController::class, 'deleteRider']);

    // Category management
    Route::post('/categories', [AdminController::class, 'createCategory']);
    Route::get('/categories', [AdminController::class, 'getCategories']);
    Route::get('/categories/{id}', [AdminController::class, 'getCategory']);
    Route::post('/categories/{id}', [AdminController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [AdminController::class, 'deleteCategory']);

    // Product management
    Route::post('/products', [AdminController::class, 'createProduct']);
    Route::get('/products', [AdminController::class, 'getProducts']);
    Route::get('/products/{id}', [AdminController::class, 'getProduct']);
    Route::post('/products/{id}', [AdminController::class, 'updateProduct']);
    Route::delete('/products/{id}', [AdminController::class, 'deleteProduct']);

    // Order management
    Route::get('/orders', [AdminController::class, 'getOrders']);
    Route::get('/orders/{id}', [AdminController::class, 'getOrder']);
    Route::put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);
    Route::post('/orders/{orderId}/assign-rider', [AdminController::class, 'assignRider']);

    // Customer management
    Route::get('/customers', [AdminController::class, 'getCustomers']);
    Route::get('/customers/{id}', [AdminController::class, 'getCustomer']);
    Route::put('/customers/{id}', [AdminController::class, 'updateCustomer']);
    Route::delete('/customers/{id}', [AdminController::class, 'deleteCustomer']);
    Route::get('/customers/pending-orders', [AdminController::class, 'getCustomersWithPendingOrders']);

    // Dashboard & Analytics
    Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
    Route::get('/analytics/revenue', [AdminController::class, 'getRevenueAnalytics']);
    Route::get('/accounting', [AdminController::class, 'getAccountingReport']);
    Route::get('/settings', [AdminController::class, 'getSettings']);
    Route::post('/settings', [AdminController::class, 'updateSettings']);
    Route::post('/settings/logo', [AdminController::class, 'uploadLogo']);
    Route::post('/profile-picture', [AdminController::class, 'updateProfilePicture']);
    Route::get('/profile', [AdminController::class, 'getProfile']);
    Route::put('/profile', [AdminController::class, 'updateProfile']);

    // Sliders Management
    Route::prefix('sliders')->group(function () {
        Route::get('/', [AdminController::class, 'getSliders']);
        Route::post('/', [AdminController::class, 'createSlider']);
        Route::put('/{id}', [AdminController::class, 'updateSlider']);
        Route::delete('/{id}', [AdminController::class, 'deleteSlider']);
    });

    // Ads Management
    Route::prefix('ads')->group(function () {
        Route::get('/', [AdminController::class, 'getAds']);
        Route::post('/', [AdminController::class, 'createAd']);
        Route::get('/{id}', [AdminController::class, 'getAd']);
        Route::post('/{id}', [AdminController::class, 'updateAd']);
        Route::patch('/{id}', [AdminController::class, 'updateAd']);
        Route::delete('/{id}', [AdminController::class, 'deleteAd']);
    });

    // Transaction Details
    Route::prefix('transactions')->group(function () {
        Route::get('/', [AdminController::class, 'getTransactions']);
        Route::get('/{id}', [AdminController::class, 'getTransaction']);
        Route::get('/orders/{orderId}', [AdminController::class, 'getOrderTransactions']);
    });

    // Invoice Management
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/statistics', [InvoiceController::class, 'getStatistics']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::get('/orders/{orderId}', [InvoiceController::class, 'getByOrder']);
        Route::put('/{id}/status', [InvoiceController::class, 'updateStatus']);
    });

    // Rating Management
    Route::prefix('ratings')->group(function () {
        Route::get('/', [RatingController::class, 'getAllRatings']);
    });

    // Admin Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [AdminNotificationController::class, 'index']);
        Route::get('/stats', [AdminNotificationController::class, 'getStats']);
        Route::get('/unread-count', [AdminNotificationController::class, 'getUnreadCount']);
        Route::get('/critical', [AdminNotificationController::class, 'getCritical']);
        Route::get('/category/{category}', [AdminNotificationController::class, 'getByCategory']);
        Route::put('/{id}/read', [AdminNotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [AdminNotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [AdminNotificationController::class, 'destroy']);
        Route::post('/cleanup-expired', [AdminNotificationController::class, 'cleanupExpired']);
    });
});

// Subadmin routes
Route::middleware(['auth:subadmin-api', 'role:subadmin'])->prefix('subadmin')->group(function () {
    // Branch management
    Route::get('/branch', [SubadminController::class, 'getBranch'])->middleware('permission:branch.view');
    Route::put('/branch', [SubadminController::class, 'updateBranch'])->middleware('permission:branch.update');

    // Product management
    Route::get('/products', [SubadminController::class, 'getProducts'])->middleware('permission:products.view');
    Route::get('/products/{id}', [SubadminController::class, 'getProduct'])->middleware('permission:products.view');
    Route::post('/products', [SubadminController::class, 'createProduct'])->middleware('permission:products.create');
    Route::post('/products/{id}', [SubadminController::class, 'updateProduct'])->middleware('permission:products.update');
    Route::delete('/products/{id}', [SubadminController::class, 'deleteProduct'])->middleware('permission:products.delete');

    // Category management
    Route::get('/categories', [SubadminController::class, 'getCategories'])->middleware('permission:categories.view');
    Route::post('/categories', [SubadminController::class, 'createCategory'])->middleware('permission:categories.create');
    Route::post('/categories/{id}', [SubadminController::class, 'updateCategory'])->middleware('permission:categories.update');
    Route::delete('/categories/{id}', [SubadminController::class, 'deleteCategory'])->middleware('permission:categories.delete');

    // Order management
    Route::get('/orders', [SubadminController::class, 'getOrders'])->middleware('permission:orders.view');
    Route::get('/orders/{id}', [SubadminController::class, 'getOrder'])->middleware('permission:orders.view');
    Route::put('/orders/{id}/status', [SubadminController::class, 'updateOrderStatus'])->middleware('permission:orders.update');
    Route::post('/orders/{orderId}/assign-rider', [SubadminController::class, 'assignRider'])->middleware('permission:orders.assign_rider');

    // Rider management
    Route::get('/available-riders', [SubadminController::class, 'getAvailableRiders'])->middleware('permission:orders.assign_rider');

    // Statistics
    Route::get('/statistics', [SubadminController::class, 'getStatistics'])->middleware('permission:orders.view');
    Route::post('/profile-picture', [SubadminController::class, 'updateProfilePicture'])->middleware('permission:profile.update');
    Route::get('/profile', [SubadminController::class, 'getProfile'])->middleware('permission:profile.view');
    Route::put('/profile', [SubadminController::class, 'updateProfile'])->middleware('permission:profile.update');

    // Inventory Management (Subadmin only)
    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->middleware('permission:inventory.view');
        Route::get('/{id}', [InventoryController::class, 'show'])->middleware('permission:inventory.view');
        Route::put('/{id}', [InventoryController::class, 'update'])->middleware('permission:inventory.update');
        Route::delete('/{id}', [InventoryController::class, 'destroy'])->middleware('permission:inventory.delete');
        Route::post('/{id}/restock', [InventoryController::class, 'restock'])->middleware('permission:inventory.restock');
        Route::get('/alerts/low-stock', [InventoryController::class, 'lowStockAlerts'])->middleware('permission:inventory.view');
    });

    // Subadmin Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [AdminNotificationController::class, 'index']);
        Route::get('/stats', [AdminNotificationController::class, 'getStats']);
        Route::get('/unread-count', [AdminNotificationController::class, 'getUnreadCount']);
        Route::get('/critical', [AdminNotificationController::class, 'getCritical']);
        Route::get('/category/{category}', [AdminNotificationController::class, 'getByCategory']);
        Route::put('/{id}/read', [AdminNotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [AdminNotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [AdminNotificationController::class, 'destroy']);
    });
});

// Customer routes
Route::middleware(['auth:customer-api', 'role:customer'])->prefix('customer')->group(function () {
    Route::post('/orders', [CustomerController::class, 'createOrder']);
    Route::get('/orders', [CustomerController::class, 'getOrders']);
    Route::get('/orders/{id}', [CustomerController::class, 'getOrder']);

    Route::post('/initialize-payment', [CustomerController::class, 'initializePayment']);
    Route::post('/verify-payment', [CustomerController::class, 'verifyPayment']);

    Route::get('/wishlist', [CustomerController::class, 'getWhishList']);
    Route::post('/wishlist', [CustomerController::class, 'createWhishList']);
    Route::delete('/wishlist/{id}', [CustomerController::class, 'deleteWhishList']);

    Route::get('/profile', [CustomerController::class, 'getProfile']);
    Route::put('/profile', [CustomerController::class, 'updateProfile']);
    Route::post('/profile-picture', [CustomerController::class, 'updateProfilePicture']);
    Route::put('/update-shipping-addresses', [CustomerController::class, 'updateShippingAddresses']);

    // Customer Invoices
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'getCustomerInvoices']);
        Route::get('/{id}', [InvoiceController::class, 'getCustomerInvoice']);
    });

    // Customer Ratings
    Route::prefix('ratings')->group(function () {
        Route::post('/', [RatingController::class, 'rateRider']);
        Route::get('/', [RatingController::class, 'getCustomerRatings']);
        Route::put('/{id}', [RatingController::class, 'updateRating']);
        Route::delete('/{id}', [RatingController::class, 'deleteRating']);
    });
});

// Rider routes
Route::middleware(['auth:rider-api', 'role:rider'])->prefix('rider')->group(function () {
    Route::put('/status', [RiderController::class, 'updateStatus']);
    Route::put('/location', [RiderController::class, 'updateLocation']);
    Route::get('/orders', [RiderController::class, 'getOrders']);
    Route::get('/orders/{id}', [RiderController::class, 'getOrder']);
    Route::put('/orders/{id}/delivered', [RiderController::class, 'markDelivered']);
    Route::get('/available-orders', [RiderController::class, 'getAvailableOrders']);
    Route::get('/profile', [RiderController::class, 'getProfile']);
    Route::put('/profile', [RiderController::class, 'updateProfile']);
    Route::post('/profile-picture', [RiderController::class, 'updateProfilePicture']);
});

Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // File Upload Routes (for all authenticated users)
    Route::prefix('upload')->group(function () {
        Route::post('/product-images', [FileUploadController::class, 'uploadProductImages']);
        Route::post('/image', [FileUploadController::class, 'uploadImage']);
        Route::delete('/file', [FileUploadController::class, 'deleteFile']);
        Route::get('/file-info', [FileUploadController::class, 'getFileInfo']);
    });

    // Notification Routes (for all authenticated users)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'getNotifications']);
        Route::get('/count', [NotificationController::class, 'getNotificationCount']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'deleteNotification']);
    });
});
