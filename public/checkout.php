<?php
/**
 * public/checkout.php
 * HotelOS Enterprise - Check-Out & Billing Engine
 * Phase 4.5: Revenue Realization Module
 * 
 * Features:
 * - Search booking by Room Number or Booking ID
 * - Auto-calculate nights stayed, GST, total bill
 * - Payment settlement with transaction safety
 * - Professional invoice preview (printable)
 * - Room status update after checkout
 */

session_start();

// Security: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/config.php';
require_once 'layout.php';

$tenantId = $_SESSION['tenant_id'] ?? 1;
$hotelName = $_SESSION['hotel_name'] ?? 'HotelOS';
$userName = $_SESSION['user_name'] ?? 'Admin';

// Initialize variables
$error = '';
$success = '';
$bookingData = null;
$billCalculation = null;

// =====================================================
// BACKEND ENGINE: Search Active Booking
// =====================================================
if (isset($_GET['search']) && !empty($_GET['search_value'])) {
    $searchValue = trim($_GET['search_value']);
    $searchType = $_GET['search_type'] ?? 'room_number';

    try {
        if ($searchType === 'room_number') {
            // Search by Room Number
            $stmt = $pdo->prepare("
                SELECT 
                    b.id as booking_id,
                    b.unique_booking_id,
                    b.check_in,
                    b.total_amount as estimated_total,
                    b.paid_amount,
                    g.id as guest_id,
                    g.full_name as guest_name,
                    g.mobile,
                    g.email,
                    g.company_name,
                    g.gst_number,
                    r.id as room_id,
                    r.room_number,
                    r.category,
                    r.base_price
                FROM bookings b
                INNER JOIN guests g ON b.guest_id = g.id
                INNER JOIN rooms r ON b.room_id = r.id
                WHERE b.tenant_id = ? 
                AND r.room_number = ? 
                AND b.status = 'active'
                ORDER BY b.check_in DESC
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $searchValue]);
        } else {
            // Search by Booking ID
            $stmt = $pdo->prepare("
                SELECT 
                    b.id as booking_id,
                    b.unique_booking_id,
                    b.check_in,
                    b.total_amount as estimated_total,
                    b.paid_amount,
                    g.id as guest_id,
                    g.full_name as guest_name,
                    g.mobile,
                    g.email,
                    g.company_name,
                    g.gst_number,
                    r.id as room_id,
                    r.room_number,
                    r.category,
                    r.base_price
                FROM bookings b
                INNER JOIN guests g ON b.guest_id = g.id
                INNER JOIN rooms r ON b.room_id = r.id
                WHERE b.tenant_id = ? 
                AND b.unique_booking_id = ? 
                AND b.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $searchValue]);
        }

        $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bookingData) {
            $error = "No active booking found for: " . htmlspecialchars($searchValue);
        } else {
            // =====================================================
            // CALCULATION ENGINE: The Accountant
            // =====================================================

            // STEP 1: Calculate Nights Stayed
            $checkInTime = strtotime($bookingData['check_in']);
            $currentTime = time();
            $secondsStayed = $currentTime - $checkInTime;
            $nightsStayed = max(1, ceil($secondsStayed / 86400)); // Minimum 1 night

            // STEP 2: Calculate Room Charges
            $basePrice = (float) $bookingData['base_price'];
            $roomTotal = $nightsStayed * $basePrice;

            // STEP 3: Calculate GST (12% if ≤7500, 18% if >7500)
            if ($roomTotal <= 7500) {
                $gstRate = 12;
            } else {
                $gstRate = 18;
            }
            $gstAmount = ($roomTotal * $gstRate) / 100;

            // STEP 4: Additional Charges (Placeholder for future)
            $additionalCharges = 0;

            // STEP 5: Calculate Grand Total
            $grandTotal = $roomTotal + $gstAmount + $additionalCharges;
            $alreadyPaid = (float) $bookingData['paid_amount'];
            $pendingDue = $grandTotal - $alreadyPaid;

            // Store calculation for UI
            $billCalculation = [
                'nights_stayed' => $nightsStayed,
                'base_price' => $basePrice,
                'room_total' => $roomTotal,
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'additional_charges' => $additionalCharges,
                'grand_total' => $grandTotal,
                'already_paid' => $alreadyPaid,
                'pending_due' => $pendingDue,
                'check_in_formatted' => date('d M Y, h:i A', $checkInTime),
                'duration_text' => $nightsStayed . ' Night' . ($nightsStayed > 1 ? 's' : '')
            ];
        }

    } catch (Exception $e) {
        $error = "Search failed: " . $e->getMessage();
        error_log("[CHECKOUT ERROR] " . $e->getMessage());
    }
}

// =====================================================
// BACKEND ENGINE: Process Checkout Settlement
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'settle_checkout') {

    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $finalAmount = (float) ($_POST['final_amount'] ?? 0);
    $paymentMode = $_POST['payment_mode'] ?? 'cash';
    $discount = (float) ($_POST['discount'] ?? 0);

    // Validation
    if ($bookingId === 0 || $roomId === 0 || $finalAmount < 0) {
        $error = "Invalid checkout data. Please try again.";
    } else {

        // TRANSACTION-SAFE SETTLEMENT
        try {
            $pdo->beginTransaction();

            // STEP 1: Record Final Payment (if any amount due)
            if ($finalAmount > 0) {
                $invoiceNumber = 'INV' . date('YmdHis') . rand(100, 999);
                $stmt = $pdo->prepare("
                    INSERT INTO transactions 
                    (tenant_id, booking_id, invoice_number, type, category, amount, payment_mode, description) 
                    VALUES (?, ?, ?, 'credit', 'Final Settlement', ?, ?, 'Final payment at checkout')
                ");
                $stmt->execute([$tenantId, $bookingId, $invoiceNumber, $finalAmount, $paymentMode]);
            }

            // STEP 2: Update Booking Status
            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET status = 'completed', 
                    check_out = NOW(),
                    paid_amount = paid_amount + ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$finalAmount, $bookingId, $tenantId]);

            // STEP 3: Update Room Status (to 'cleaning' as per approved plan)
            $stmt = $pdo->prepare("
                UPDATE rooms 
                SET status = 'cleaning' 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$roomId, $tenantId]);

            $pdo->commit();

            $_SESSION['flash_success'] = "Check-Out Successful! Guest settled and room marked for cleaning.";
            header('Location: dashboard.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Checkout Failed: " . $e->getMessage();
            error_log("[CHECKOUT ERROR] " . $e->getMessage());
        }
    }
}

// START OUTPUT BUFFER FOR LAYOUT
ob_start();
?>

<!-- Mobile Header -->
<header
    class="lg:hidden flex items-center justify-between p-4 app-card border-b-0 border-r-0 border-l-0 border-[var(--glass-border)] shrink-0 sticky top-0 z-30">
    <span class="font-tech font-bold text-lg tracking-wider app-text-main">CHECK-OUT</span>
    <div class="flex items-center gap-2">
        <a href="dashboard.php"
            class="h-8 px-3 rounded-lg app-card border border-[var(--glass-border)] app-text-main flex items-center gap-1 text-xs font-bold hover:bg-[var(--glass-border)] transition">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                </path>
            </svg>
            Home
        </a>
        <a href="logout.php"
            class="h-8 px-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-500 flex items-center gap-1 text-xs font-bold hover:bg-red-500/20 transition">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
        </a>
    </div>
</header>

<!-- Scrollable Main Content -->
<main class="flex-1 overflow-y-auto w-full p-4 lg:p-8 pb-32 lg:pb-8" x-data="checkoutApp()">

    <?php if ($error): ?>
        <div
            class="max-w-6xl mx-auto mb-6 bg-red-500/10 border border-red-500/20 text-red-500 px-4 py-3 rounded-lg flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- SEARCH FORM -->
    <div class="max-w-6xl mx-auto mb-6">
        <div class="app-card rounded-xl p-5 md:p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-5 pb-4 border-b border-[var(--glass-border)]">
                <div class="w-8 h-8 rounded bg-blue-500/10 flex items-center justify-center text-blue-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <h2 class="font-tech text-base font-bold app-text-main tracking-wide">SEARCH ACTIVE BOOKING</h2>
            </div>

            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3">
                    <select name="search_type"
                        class="app-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all">
                        <option value="room_number" <?= (($_GET['search_type'] ?? 'room_number') === 'room_number') ? 'selected' : '' ?>>Room Number</option>
                        <option value="booking_id" <?= (($_GET['search_type'] ?? '') === 'booking_id') ? 'selected' : '' ?>>Booking ID</option>
                    </select>
                </div>
                <div class="md:col-span-7">
                    <input type="text" name="search_value" value="<?= htmlspecialchars($_GET['search_value'] ?? '') ?>"
                        required
                        class="app-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono"
                        placeholder="Enter value...">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" name="search" value="1"
                        class="app-btn w-full py-3 rounded-lg flex items-center justify-center gap-2 font-bold uppercase text-sm tracking-wider">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($bookingData && $billCalculation): ?>

        <!-- BILLING INTERFACE -->
        <form method="POST" action="" class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">

            <input type="hidden" name="action" value="settle_checkout">
            <input type="hidden" name="booking_id" value="<?= $bookingData['booking_id'] ?>">
            <input type="hidden" name="room_id" value="<?= $bookingData['room_id'] ?>">

            <!-- LEFT PANEL: INVOICE PREVIEW (8/12) -->
            <div class="lg:col-span-8">
                <div class="bg-white text-gray-900 rounded-xl shadow-lg p-6 md:p-8" id="printableInvoice">

                    <!-- Invoice Header -->
                    <div class="border-b-2 border-gray-200 pb-4 mb-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($hotelName) ?></h1>
                                <p class="text-sm text-gray-600 mt-1">Check-Out Invoice</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 uppercase">Invoice #</p>
                                <p class="font-mono font-bold text-sm">
                                    <?= htmlspecialchars($bookingData['unique_booking_id']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-2"><?= date('d M Y, h:i A') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Guest Details -->
                    <div class="grid grid-cols-2 gap-6 mb-6 pb-6 border-b border-gray-200">
                        <div>
                            <p class="text-xs text-gray-500 uppercase mb-1">Guest Name</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($bookingData['guest_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase mb-1">Mobile</p>
                            <p class="font-mono text-gray-900">+91 <?= htmlspecialchars($bookingData['mobile']) ?></p>
                        </div>
                        <?php if (!empty($bookingData['company_name'])): ?>
                            <div>
                                <p class="text-xs text-gray-500 uppercase mb-1">Company</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($bookingData['company_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase mb-1">GST Number</p>
                                <p class="font-mono text-sm text-gray-900"><?= htmlspecialchars($bookingData['gst_number']) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stay Details -->
                    <div class="mb-6 pb-6 border-b border-gray-200">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase mb-1">Room Number</p>
                                <p class="font-bold text-lg text-blue-600">
                                    <?= htmlspecialchars($bookingData['room_number']) ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase mb-1">Category</p>
                                <p class="font-semibold text-gray-900"><?= ucfirst($bookingData['category']) ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase mb-1">Duration</p>
                                <p class="font-semibold text-gray-900"><?= $billCalculation['duration_text'] ?></p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500 uppercase mb-1">Check-In Time</p>
                            <p class="font-mono text-sm text-gray-700"><?= $billCalculation['check_in_formatted'] ?></p>
                        </div>
                    </div>

                    <!-- Bill Breakdown -->
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Room Charges (<?= $billCalculation['nights_stayed'] ?> ×
                                ₹<?= number_format($billCalculation['base_price'], 2) ?>)</span>
                            <span
                                class="font-mono font-semibold">₹<?= number_format($billCalculation['room_total'], 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">GST (<?= $billCalculation['gst_rate'] ?>%)</span>
                            <span
                                class="font-mono font-semibold">₹<?= number_format($billCalculation['gst_amount'], 2) ?></span>
                        </div>
                        <?php if ($billCalculation['additional_charges'] > 0): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Additional Charges</span>
                                <span
                                    class="font-mono font-semibold">₹<?= number_format($billCalculation['additional_charges'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Totals -->
                    <div class="border-t-2 border-gray-300 pt-4 space-y-2">
                        <div class="flex justify-between items-center text-lg">
                            <span class="font-bold text-gray-900">Grand Total</span>
                            <span
                                class="font-mono font-bold text-gray-900">₹<?= number_format($billCalculation['grand_total'], 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-green-600 font-semibold">Already Paid</span>
                            <span
                                class="font-mono font-semibold text-green-600">₹<?= number_format($billCalculation['already_paid'], 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xl pt-2 border-t border-gray-200">
                            <span
                                class="font-bold <?= $billCalculation['pending_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $billCalculation['pending_due'] > 0 ? 'Pending Due' : 'Fully Paid' ?>
                            </span>
                            <span
                                class="font-mono font-bold <?= $billCalculation['pending_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                ₹<?= number_format(abs($billCalculation['pending_due']), 2) ?>
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT PANEL: PAYMENT FORM (4/12) -->
            <div class="lg:col-span-4 space-y-6">
                <div class="app-card rounded-xl p-5 md:p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[var(--glass-border)]">
                        <div class="w-8 h-8 rounded bg-green-500/10 flex items-center justify-center text-green-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                        </div>
                        <h2 class="font-tech text-base font-bold app-text-main tracking-wide">SETTLEMENT</h2>
                    </div>

                    <!-- Discount (Optional) -->
                    <div class="mb-5">
                        <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Discount (Optional)</label>
                        <input type="number" name="discount" x-model="discount" @input="updateFinalPayable()" min="0"
                            :max="pendingDue" step="0.01"
                            class="app-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-1 focus:ring-green-500 transition-all font-mono"
                            placeholder="₹ 0.00">
                    </div>

                    <!-- Final Payable (Reactive) -->
                    <div class="mb-5 p-4 rounded-lg bg-blue-500/10 border border-blue-500/20">
                        <p class="text-xs app-text-muted uppercase mb-1">Final Payable</p>
                        <p class="text-3xl font-bold font-mono text-blue-600">₹<span
                                x-text="finalPayable.toFixed(2)">0.00</span></p>
                        <input type="hidden" name="final_amount" :value="finalPayable">
                    </div>

                    <!-- Payment Mode -->
                    <div class="mb-6">
                        <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Payment Mode</label>
                        <select name="payment_mode" x-model="paymentMode"
                            class="app-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-1 focus:ring-green-500 transition-all">
                            <option value="cash">💵 Cash</option>
                            <option value="upi">📱 UPI</option>
                            <option value="card">💳 Card</option>
                            <option value="bank_transfer">🏦 Bank Transfer</option>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                        class="app-btn w-full py-4 rounded-lg flex items-center justify-center gap-3 shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all font-bold uppercase text-sm tracking-widest"
                        :disabled="finalPayable < 0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Settle & Check Out</span>
                    </button>

                    <p class="text-xs app-text-muted text-center mt-4">Room will be marked for cleaning</p>
                </div>
            </div>

        </form>

    <?php endif; ?>

</main>

<script>
    function checkoutApp() {
        return {
            pendingDue: <?= $billCalculation['pending_due'] ?? 0 ?>,
            discount: 0,
            finalPayable: <?= $billCalculation['pending_due'] ?? 0 ?>,
            paymentMode: 'cash',

            updateFinalPayable() {
                this.finalPayable = Math.max(0, this.pendingDue - this.discount);
            }
        }
    }
</script>

<?php
$content = ob_get_clean();
renderLayout("Check-Out & Billing", $content, true);
?>