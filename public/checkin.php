<?php
/**
 * public/checkin.php
 * HotelOS Enterprise - Intelligent Check-In Engine
 * Phase 4: Core Operations Module
 * 
 * Features:
 * - Guest Detection (Search by Mobile)
 * - Smart Room Allocation (Only available rooms)
 * - The "Lock" Mechanism (Instant status update)
 * - Transaction-safe with PDO BEGIN/COMMIT/ROLLBACK
 * 
 * THEME ENGINE: INTEGRATED (Phase 2)
 */

session_start();

// Security: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../');
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once 'layout.php';

$tenantId = $_SESSION['tenant_id'] ?? 1;
$hotelName = $_SESSION['hotel_name'] ?? 'HotelOS';

// Initialize variables
$error = '';
$success = '';
$foundGuest = null;
$availableRooms = [];

// =====================================================
// BACKEND ENGINE: Fetch Available Rooms
// =====================================================
try {
    $stmt = $pdo->prepare("SELECT id, room_number, category, base_price FROM rooms WHERE tenant_id = ? AND status = 'available' ORDER BY room_number ASC");
    $stmt->execute([$tenantId]);
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Failed to fetch rooms: " . $e->getMessage();
}

// =====================================================
// AJAX: Guest Search by Mobile
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_guest') {
    header('Content-Type: application/json');
    $mobile = $_POST['mobile'] ?? '';

    if (strlen($mobile) >= 10) {
        $stmt = $pdo->prepare("SELECT id, full_name, mobile, email, company_name, gst_number, address, identity_card_type, identity_card_number, city, state FROM guests WHERE tenant_id = ? AND mobile = ? LIMIT 1");
        $stmt->execute([$tenantId, $mobile]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($guest) {
            echo json_encode(['status' => 'found', 'guest' => $guest]);
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Enter valid 10-digit mobile']);
    }
    exit;
}

// =====================================================
// BACKEND ENGINE: Handle Check-In Submission
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {

    // Collect form data
    $guestId = $_POST['guest_id'] ?? null;
    $guestName = trim($_POST['guest_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $gstNumber = trim($_POST['gst_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $idType = $_POST['id_type'] ?? '';
    $idNumber = trim($_POST['id_number'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $nights = (int) ($_POST['nights'] ?? 1);
    $totalAmount = (float) ($_POST['total_amount'] ?? 0);
    $advancePayment = (float) ($_POST['advance_payment'] ?? 0);
    $paymentMode = $_POST['payment_mode'] ?? 'cash';

    // Validation
    if (empty($guestName) || empty($mobile) || $roomId === 0) {
        $error = "Guest Name, Mobile, and Room are required.";
    } else {

        // TRANSACTION-SAFE LOGIC (PRESERVED)
        try {
            $pdo->beginTransaction();

            // STEP 1: Create or Update Guest
            if (empty($guestId)) {
                $stmt = $pdo->prepare("INSERT INTO guests (tenant_id, full_name, mobile, email, company_name, gst_number, address, identity_card_type, identity_card_number, city, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenantId, $guestName, $mobile, $email, $companyName, $gstNumber, $address, $idType, $idNumber, $city, $state]);
                $guestId = $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare("UPDATE guests SET full_name = ?, email = ?, company_name = ?, gst_number = ?, address = ?, identity_card_type = ?, identity_card_number = ?, city = ?, state = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$guestName, $email, $companyName, $gstNumber, $address, $idType, $idNumber, $city, $state, $guestId, $tenantId]);
            }

            // STEP 2: Create Booking
            $checkIn = date('Y-m-d H:i:s');
            $checkOut = date('Y-m-d H:i:s', strtotime("+$nights days"));
            $uniqueBookingId = 'BK' . date('YmdHis') . rand(100, 999);

            $stmt = $pdo->prepare("INSERT INTO bookings (tenant_id, guest_id, room_id, unique_booking_id, check_in, check_out, status, total_amount, paid_amount, booking_source) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, 'walk-in')");
            $stmt->execute([$tenantId, $guestId, $roomId, $uniqueBookingId, $checkIn, $checkOut, $totalAmount, $advancePayment]);
            $bookingId = $pdo->lastInsertId();

            // STEP 3: Lock Room
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$roomId, $tenantId]);

            // STEP 4: Record Payment
            if ($advancePayment > 0) {
                $invoiceNumber = 'INV' . date('YmdHis');
                $stmt = $pdo->prepare("INSERT INTO transactions (tenant_id, booking_id, invoice_number, type, category, amount, payment_mode, description) VALUES (?, ?, ?, 'credit', 'Advance Payment', ?, ?, 'Advance payment at check-in')");
                $stmt->execute([$tenantId, $bookingId, $invoiceNumber, $advancePayment, $paymentMode]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Check-In Successful! Booking ID: $uniqueBookingId";
            header('Location: dashboard.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Check-In Failed: " . $e->getMessage();
        }
    }
}

// START OUTPUT BUFFER FOR LAYOUT
ob_start();
?>

<!-- Mobile Header (Visible only on small screens) -->
<header
    class="lg:hidden flex items-center justify-between p-4 app-card border-b-0 border-r-0 border-l-0 border-[var(--glass-border)] shrink-0 sticky top-0 z-30">
    <span class="font-tech font-bold text-lg tracking-wider app-text-main">CHECK-IN</span>
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
<main class="flex-1 overflow-y-auto w-full p-4 lg:p-8 pb-32 lg:pb-8" x-data="checkinApp()">

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

    <form method="POST" action="" class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">

        <input type="hidden" name="action" value="checkin">
        <input type="hidden" name="guest_id" x-model="guestId">

        <!-- LEFT COLUMN: GUEST IDENTITY (5/12) -->
        <div class="lg:col-span-5 space-y-6">
            <div class="app-card rounded-xl p-5 md:p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[var(--glass-border)]">
                    <div class="w-8 h-8 rounded bg-blue-500/10 flex items-center justify-center text-blue-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <h2 class="font-tech text-base font-bold app-text-main tracking-wide">GUEST IDENTITY</h2>
                </div>

                <!-- Mobile Number -->
                <div class="mb-5">
                    <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Mobile Number <span
                            class="text-red-400">*</span></label>
                    <div
                        class="flex gap-0 border border-[var(--input-border)] rounded-lg overflow-hidden focus-within:ring-1 focus-within:ring-blue-500 focus-within:border-blue-500 transition-all">
                        <span
                            class="bg-[var(--input-bg)] app-text-muted px-3 py-3 font-mono text-sm flex items-center border-r border-[var(--input-border)]">+91</span>
                        <input type="tel" name="mobile" x-model="mobile" required maxlength="10"
                            class="flex-1 bg-[var(--input-bg)] app-text-main px-4 py-3 outline-none font-mono tracking-wide placeholder-gray-500/50"
                            placeholder="98765 43210">
                        <button type="button" @click="searchGuest()"
                            class="bg-[var(--input-bg)] hover:bg-blue-600 hover:text-white text-blue-400 px-4 transition-colors border-l border-[var(--input-border)]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-2 h-4">
                        <p x-show="guestStatus === 'found'"
                            class="text-green-500 text-[10px] font-bold flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> RETURNING GUEST</p>
                        <p x-show="guestStatus === 'not_found'"
                            class="text-blue-400 text-[10px] font-bold flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-blue-400 rounded-full"></span> NEW ENTRY</p>
                    </div>
                </div>

                <!-- Full Name -->
                <div class="mb-5">
                    <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Full Name <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="guest_name" x-model="guestName" required
                        class="app-input w-full px-4 py-3 rounded-lg font-medium focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                        placeholder="Guest Name">
                </div>

                <!-- Email -->
                <div class="mb-5">
                    <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Email</label>
                    <input type="email" name="email" x-model="email"
                        class="app-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                        placeholder="Email Address">
                </div>

                <!-- Company Details (B2B) -->
                <div class="mb-5 p-4 rounded-lg bg-[var(--surface-color)]/30 border border-[var(--glass-border)]">
                    <label class="block mb-3 text-xs font-bold text-amber-500 uppercase">B2B Details (Optional)</label>
                    <div class="grid grid-cols-1 gap-3">
                        <input type="text" name="company_name" x-model="companyName"
                            class="app-input w-full px-4 py-3 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all"
                            placeholder="Company Name">
                        <input type="text" name="gst_number" x-model="gstNumber"
                            class="app-input w-full px-4 py-3 rounded-lg text-sm font-mono focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all"
                            placeholder="GST Number (e.g. 29XXXXX1234X1ZX)">
                    </div>
                </div>

                <!-- Address (Legal Requirement) -->
                <div class="mb-5">
                    <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Full Address</label>
                    <textarea name="address" x-model="address" rows="2"
                        class="app-input w-full px-4 py-3 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all resize-none"
                        placeholder="Street, City, State, PIN"></textarea>
                </div>

                <!-- ID Proof -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">ID Type</label>
                        <select name="id_type" x-model="idType"
                            class="app-input w-full px-2 py-3 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all">
                            <option value="">None</option>
                            <option value="Aadhaar">Aadhaar</option>
                            <option value="PAN">PAN</option>
                            <option value="Passport">Passport</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">ID Number</label>
                        <input type="text" name="id_number" x-model="idNumber"
                            class="app-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                            placeholder="XXXX">
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: ROOM & BILLING (7/12) -->
        <div class="lg:col-span-7 space-y-6">
            <div class="app-card rounded-xl p-5 md:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-[var(--glass-border)]">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded bg-purple-500/10 flex items-center justify-center text-purple-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                        </div>
                        <h2 class="font-tech text-base font-bold app-text-main tracking-wide">ROOM ALLOCATION</h2>
                    </div>
                    <span
                        class="bg-[var(--input-bg)] text-[10px] font-bold px-2 py-1 rounded app-text-muted border border-[var(--input-border)]">
                        <?= count($availableRooms) ?> AVAIL
                    </span>
                </div>

                <div class="mb-6">
                    <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Select Unit <span
                            class="text-red-400">*</span></label>
                    <select name="room_id" x-model="roomId" @change="updatePrice()" required
                        class="app-input w-full px-4 py-4 rounded-lg text-lg font-bold outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        <option value="" class="opacity-50">-- Select Available Unit --</option>
                        <?php foreach ($availableRooms as $room): ?>
                            <option value="<?= $room['id'] ?>" data-price="<?= $room['base_price'] ?>">
                                Room <?= htmlspecialchars($room['room_number']) ?> &nbsp;•&nbsp;
                                <?= ucfirst($room['category']) ?> &nbsp;•&nbsp;
                                ₹<?= number_format($room['base_price'], 0) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($availableRooms)): ?>
                        <p class="text-red-500 text-xs mt-2 font-bold">⚠️ All rooms occupied</p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    <div class="col-span-1">
                        <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Duration (Days)</label>
                        <div
                            class="flex items-center bg-[var(--input-bg)] rounded-lg border border-[var(--input-border)]">
                            <button type="button" @click="if(nights>1) { nights--; calculateTotal() }"
                                class="px-3 py-3 app-text-muted hover:app-text-main hover:bg-[var(--glass-border)] transition">-</button>
                            <input type="number" name="nights" x-model="nights" readonly
                                class="w-full bg-transparent text-center font-bold app-text-main outline-none"
                                value="1">
                            <button type="button" @click="if(nights<30) { nights++; calculateTotal() }"
                                class="px-3 py-3 app-text-muted hover:app-text-main hover:bg-[var(--glass-border)] transition">+</button>
                        </div>
                    </div>
                    <div class="col-span-1">
                        <label class="block mb-2 text-xs font-semibold app-text-muted uppercase">Total Rate</label>
                        <div
                            class="px-4 py-3 rounded-lg bg-[var(--input-bg)] border border-[var(--input-border)] app-text-main font-mono font-bold text-center">
                            ₹<span x-text="totalAmount">0</span>
                        </div>
                        <input type="hidden" name="total_amount" x-model="totalAmount">
                    </div>
                </div>

                <div class="bg-[var(--input-bg)] rounded-lg p-4 border border-[var(--input-border)]">
                    <label class="block mb-3 text-sm font-bold text-green-500 uppercase tracking-wider">Payments</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <input type="number" name="advance_payment" x-model="advancePayment" min="0"
                                @input="calculateTotal()"
                                class="app-input w-full px-4 py-2 rounded text-sm font-bold placeholder-gray-500/50 focus:outline-none focus:ring-1 focus:ring-green-500"
                                placeholder="Advance ₹">
                        </div>
                        <div>
                            <select name="payment_mode"
                                class="app-input w-full px-4 py-2 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-[var(--glass-border)] flex justify-between items-center">
                        <span class="text-xs app-text-muted font-bold uppercase">Pending Due</span>
                        <span class="text-lg font-bold text-orange-500 font-mono">₹<span
                                x-text="totalAmount - advancePayment">0</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- DESKTOP SUBMIT -->
        <div class="hidden lg:block lg:col-span-12 text-right">
            <button type="submit"
                class="app-btn px-8 py-4 rounded-lg flex items-center justify-center gap-3 float-right w-64 shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all text-sm font-bold uppercase tracking-widest"
                :disabled="!roomId || !guestName || !mobile">
                <span>Confirm Check-In</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3">
                    </path>
                </svg>
            </button>
        </div>

        <!-- MOBILE STICKY FOOTER -->
        <div class="lg:hidden fixed bottom-14 left-0 w-full p-4 app-card border-t border-[var(--glass-border)] z-40">
            <button type="submit"
                class="app-btn w-full py-3.5 rounded-lg flex items-center justify-center gap-2 shadow-lg"
                :disabled="!roomId || !guestName || !mobile">
                <span class="uppercase tracking-widest text-sm font-bold">Check-In</span>
            </button>
        </div>

    </form>
</main>

<script>
    function checkinApp() {
        return {
            guestId: '',
            guestName: '',
            mobile: '',
            email: '',
            companyName: '',
            gstNumber: '',
            address: '',
            idType: '',
            idNumber: '',
            city: '',
            state: '',
            guestStatus: '',
            roomId: '',
            roomPrice: 0,
            nights: 1,
            totalAmount: 0,
            advancePayment: 0,

            async searchGuest() {
                if (this.mobile.length < 10) { return; }
                const formData = new URLSearchParams();
                formData.append('action', 'search_guest');
                formData.append('mobile', this.mobile);
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    });
                    const data = await response.json();
                    if (data.status === 'found') {
                        this.guestStatus = 'found';
                        this.guestId = data.guest.id;
                        this.guestName = data.guest.full_name;
                        this.email = data.guest.email || '';
                        this.companyName = data.guest.company_name || '';
                        this.gstNumber = data.guest.gst_number || '';
                        this.address = data.guest.address || '';
                        this.idType = data.guest.identity_card_type || '';
                        this.idNumber = data.guest.identity_card_number || '';
                    } else {
                        this.guestStatus = 'not_found';
                        this.guestId = '';
                    }
                } catch (e) { console.error(e); }
            },
            updatePrice() {
                const select = document.querySelector('select[name="room_id"]');
                const option = select.options[select.selectedIndex];
                this.roomPrice = parseFloat(option.dataset.price) || 0;
                this.calculateTotal();
            },
            calculateTotal() {
                this.totalAmount = this.roomPrice * this.nights;
            }
        }
    }
</script>

<?php
$content = ob_get_clean();
renderLayout("New Check-In", $content, true);
?>