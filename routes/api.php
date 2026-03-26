<?php

use Illuminate\Http\Request;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\VeterinarianController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorChargePresetController;
use App\Http\Controllers\Api\SurgeryChargePresetController;
use App\Http\Controllers\Api\DisposabalChargePresetController;
use App\Http\Controllers\Api\ClinicSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\PatientReportController;
use App\Http\Controllers\Api\PatientVaccinationController;
use App\Http\Controllers\Api\SmsLogController;
use App\Http\Controllers\Api\DirectSaleController;
use App\Http\Controllers\Api\RevenueController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\DiagnosticUploadController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\SalaryPaymentController;
use App\Http\Controllers\Api\EmployeeBonusController;
use App\Http\Controllers\Api\CustomerReturnController;
use App\Http\Controllers\Api\SystemMaintenanceController;
use App\Http\Controllers\Api\IncomeExpenseController;

// Keep backwards-compatible routes at /api/<resource>
// preview next passbook number (placed before resource to avoid route parameter conflicts)
Route::get('patients/next-passbook', [PatientController::class, 'nextPassbook']);
Route::apiResource('patients', PatientController::class);
Route::get('patients/{patient}/vaccinations', [PatientVaccinationController::class, 'index']);
Route::post('patients/{patient}/vaccinations', [PatientVaccinationController::class, 'store']);
Route::patch('patient-vaccinations/{patientVaccination}', [PatientVaccinationController::class, 'update']);
Route::delete('patient-vaccinations/{patientVaccination}', [PatientVaccinationController::class, 'destroy']);
Route::post('appointments/{appointment}/send-invoice', [AppointmentController::class, 'sendInvoiceSms']);
// doctor charge presets (full CRUD)
Route::apiResource('doctor-charge-presets', DoctorChargePresetController::class)->only(['index','store','update','destroy']);
// surgery charge presets (full CRUD)
Route::apiResource('surgery-charge-presets', SurgeryChargePresetController::class)->only(['index','store','update','destroy']);
// disposabal charge presets (full CRUD)
Route::apiResource('disposabal-charge-presets', DisposabalChargePresetController::class)->only(['index','store','update','destroy']);
Route::apiResource('owners', OwnerController::class);
Route::apiResource('veterinarians', VeterinarianController::class);
Route::apiResource('medicines', MedicineController::class);
Route::apiResource('items-variants', MedicineController::class);
Route::apiResource('suppliers', SupplierController::class);
Route::apiResource('employees', EmployeeController::class);
Route::apiResource('salary-payments', SalaryPaymentController::class);
Route::apiResource('employee-bonuses', EmployeeBonusController::class);
Route::apiResource('customer-returns', CustomerReturnController::class);
Route::get('stock/{stock}/batches', [StockController::class, 'batches']);
Route::post('stock/{stock}/batches', [StockController::class, 'storeBatch']);
Route::put('stock/{stock}/batches/{batch}', [StockController::class, 'updateBatch']);
Route::delete('stock/{stock}/batches/{batch}', [StockController::class, 'destroyBatch']);
Route::post('stock/{stock}/adjust', [StockController::class, 'adjust']);
Route::get('stock/{stock}/adjustments', [StockController::class, 'adjustments']);
Route::apiResource('stock', StockController::class);
Route::apiResource('appointments', AppointmentController::class);
Route::apiResource('direct-sales', DirectSaleController::class)->only(['index', 'store']);
Route::get('clinic-settings', [ClinicSettingController::class, 'show']);
Route::put('clinic-settings', [ClinicSettingController::class, 'update']);
Route::get('patient-reports', [PatientReportController::class, 'index']);
Route::post('patient-reports/sync', [PatientReportController::class, 'sync']);
Route::post('uploads/diagnostic-report', [DiagnosticUploadController::class, 'store']);
Route::post('uploads/medicine-brand-image', [DiagnosticUploadController::class, 'storeMedicineBrandImage']);
Route::get('sms-logs', [SmsLogController::class, 'index']);
Route::get('sms-logs/count', [SmsLogController::class, 'count']);
Route::get('revenue', [RevenueController::class, 'getRevenue']);
Route::get('reports/comprehensive', [ReportsController::class, 'getComprehensiveReport']);
Route::get('reports/sales-heatmap', [ReportsController::class, 'getSalesHeatmapAnalytics']);
Route::get('billing/invoices', [BillingController::class, 'invoices']);
Route::post('billing/invoices', [BillingController::class, 'createInvoice']);
Route::get('billing/invoices/{invoice}', [BillingController::class, 'showInvoice']);
Route::post('billing/invoices/{invoice}/payments', [BillingController::class, 'addPayment']);
Route::post('billing/invoices/{invoice}/refunds', [BillingController::class, 'addRefund']);
Route::get('billing/day-end/summary', [BillingController::class, 'dayEndSummary']);
Route::post('billing/day-end/close', [BillingController::class, 'closeDay']);

Route::get('procurement/purchase-orders', [ProcurementController::class, 'purchaseOrders']);
Route::post('procurement/purchase-orders', [ProcurementController::class, 'createPurchaseOrder']);
Route::get('procurement/purchase-orders/{purchaseOrder}', [ProcurementController::class, 'showPurchaseOrder']);
Route::put('procurement/purchase-orders/{purchaseOrder}', [ProcurementController::class, 'updatePurchaseOrder']);
Route::get('procurement/goods-receipts', [ProcurementController::class, 'goodsReceipts']);
Route::post('procurement/goods-receipts', [ProcurementController::class, 'createGoodsReceipt']);
Route::get('procurement/goods-receipts/{goodsReceipt}', [ProcurementController::class, 'showGoodsReceipt']);
Route::get('procurement/supplier-invoices', [ProcurementController::class, 'supplierInvoices']);
Route::post('procurement/supplier-invoices', [ProcurementController::class, 'createSupplierInvoice']);
Route::get('procurement/supplier-invoices/{supplierInvoice}', [ProcurementController::class, 'showSupplierInvoice']);
Route::put('procurement/supplier-invoices/{supplierInvoice}', [ProcurementController::class, 'updateSupplierInvoice']);
Route::post('procurement/supplier-invoices/{supplierInvoice}/payments', [ProcurementController::class, 'paySupplierInvoice']);
Route::get('procurement/supplier-credit-notes', [ProcurementController::class, 'supplierCreditNotes']);
Route::post('procurement/supplier-credit-notes', [ProcurementController::class, 'createSupplierCreditNote']);
Route::get('procurement/supplier-credit-notes/{supplierCreditNote}', [ProcurementController::class, 'showSupplierCreditNote']);

Route::post('system/migrate', [SystemMaintenanceController::class, 'migrate']);
Route::post('system/seed', [SystemMaintenanceController::class, 'seed']);
Route::get('system/database', [SystemMaintenanceController::class, 'checkDatabase']);

// auth routes (token-based)
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->group(function() {
    Route::get('/income-expenses', [IncomeExpenseController::class, 'index']);
    Route::post('/income-expenses', [IncomeExpenseController::class, 'store']);
    Route::put('/income-expenses/{id}', [IncomeExpenseController::class, 'update']);
    Route::delete('/income-expenses/{id}', [IncomeExpenseController::class, 'destroy']);
});

// Also expose v1 prefix (optional)
Route::prefix('v1')->group(function () {
    // preview next passbook number under v1 as well (placed before resource)
    Route::get('patients/next-passbook', [PatientController::class, 'nextPassbook']);
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/vaccinations', [PatientVaccinationController::class, 'index']);
    Route::post('patients/{patient}/vaccinations', [PatientVaccinationController::class, 'store']);
    Route::patch('patient-vaccinations/{patientVaccination}', [PatientVaccinationController::class, 'update']);
    Route::delete('patient-vaccinations/{patientVaccination}', [PatientVaccinationController::class, 'destroy']);
    Route::post('appointments/{appointment}/send-invoice', [AppointmentController::class, 'sendInvoiceSms']);
    // doctor charge presets under v1 (full CRUD)
    Route::apiResource('doctor-charge-presets', DoctorChargePresetController::class)->only(['index','store','update','destroy']);
    // surgery charge presets under v1 (full CRUD)
    Route::apiResource('surgery-charge-presets', SurgeryChargePresetController::class)->only(['index','store','update','destroy']);
    Route::apiResource('owners', OwnerController::class);
    Route::apiResource('veterinarians', VeterinarianController::class);
    Route::apiResource('medicines', MedicineController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('salary-payments', SalaryPaymentController::class);
    Route::apiResource('employee-bonuses', EmployeeBonusController::class);
    Route::apiResource('customer-returns', CustomerReturnController::class);
    Route::get('stock/{stock}/batches', [StockController::class, 'batches']);
    Route::post('stock/{stock}/batches', [StockController::class, 'storeBatch']);
    Route::put('stock/{stock}/batches/{batch}', [StockController::class, 'updateBatch']);
    Route::delete('stock/{stock}/batches/{batch}', [StockController::class, 'destroyBatch']);
    Route::post('stock/{stock}/adjust', [StockController::class, 'adjust']);
    Route::get('stock/{stock}/adjustments', [StockController::class, 'adjustments']);
    Route::apiResource('stock', StockController::class);
    Route::apiResource('appointments', AppointmentController::class);
    Route::apiResource('direct-sales', DirectSaleController::class)->only(['index', 'store']);
    Route::get('clinic-settings', [ClinicSettingController::class, 'show']);
    Route::put('clinic-settings', [ClinicSettingController::class, 'update']);
    Route::get('patient-reports', [PatientReportController::class, 'index']);
    Route::post('patient-reports/sync', [PatientReportController::class, 'sync']);
    Route::post('uploads/diagnostic-report', [DiagnosticUploadController::class, 'store']);
    Route::post('uploads/medicine-brand-image', [DiagnosticUploadController::class, 'storeMedicineBrandImage']);
    Route::get('sms-logs', [SmsLogController::class, 'index']);
    Route::get('sms-logs/count', [SmsLogController::class, 'count']);
    Route::get('revenue', [RevenueController::class, 'getRevenue']);
    Route::get('reports/comprehensive', [ReportsController::class, 'getComprehensiveReport']);
    Route::get('reports/sales-heatmap', [ReportsController::class, 'getSalesHeatmapAnalytics']);
    Route::get('billing/invoices', [BillingController::class, 'invoices']);
    Route::post('billing/invoices', [BillingController::class, 'createInvoice']);
    Route::get('billing/invoices/{invoice}', [BillingController::class, 'showInvoice']);
    Route::post('billing/invoices/{invoice}/payments', [BillingController::class, 'addPayment']);
    Route::post('billing/invoices/{invoice}/refunds', [BillingController::class, 'addRefund']);
    Route::get('billing/day-end/summary', [BillingController::class, 'dayEndSummary']);
    Route::post('billing/day-end/close', [BillingController::class, 'closeDay']);

    Route::get('procurement/purchase-orders', [ProcurementController::class, 'purchaseOrders']);
    Route::post('procurement/purchase-orders', [ProcurementController::class, 'createPurchaseOrder']);
    Route::get('procurement/purchase-orders/{purchaseOrder}', [ProcurementController::class, 'showPurchaseOrder']);
    Route::put('procurement/purchase-orders/{purchaseOrder}', [ProcurementController::class, 'updatePurchaseOrder']);
    Route::get('procurement/goods-receipts', [ProcurementController::class, 'goodsReceipts']);
    Route::post('procurement/goods-receipts', [ProcurementController::class, 'createGoodsReceipt']);
    Route::get('procurement/goods-receipts/{goodsReceipt}', [ProcurementController::class, 'showGoodsReceipt']);
    Route::get('procurement/supplier-invoices', [ProcurementController::class, 'supplierInvoices']);
    Route::post('procurement/supplier-invoices', [ProcurementController::class, 'createSupplierInvoice']);
    Route::get('procurement/supplier-invoices/{supplierInvoice}', [ProcurementController::class, 'showSupplierInvoice']);
    Route::put('procurement/supplier-invoices/{supplierInvoice}', [ProcurementController::class, 'updateSupplierInvoice']);
    Route::post('procurement/supplier-invoices/{supplierInvoice}/payments', [ProcurementController::class, 'paySupplierInvoice']);
    Route::get('procurement/supplier-credit-notes', [ProcurementController::class, 'supplierCreditNotes']);
    Route::post('procurement/supplier-credit-notes', [ProcurementController::class, 'createSupplierCreditNote']);
    Route::get('procurement/supplier-credit-notes/{supplierCreditNote}', [ProcurementController::class, 'showSupplierCreditNote']);

    Route::post('system/migrate', [SystemMaintenanceController::class, 'migrate']);
    Route::post('system/seed', [SystemMaintenanceController::class, 'seed']);
    Route::get('system/database', [SystemMaintenanceController::class, 'checkDatabase']);
});
