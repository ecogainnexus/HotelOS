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
 */
session_start();

// Security: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';

$tenantId = $_SESSION['tenant_id'] ?? 1;
$userName = $_SESSION['user_name'] ?? 'Admin';
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
        $stmt = $pdo->prepare("SELECT id, full_name, mobile, email, identity_card_type, identity_card_number, city, state FROM guests WHERE tenant_id = ? AND mobile = ? LIMIT 1");
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

        // ===============================================
        // TRANSACTION-SAFE CHECK-IN (The Heart of HotelOS)
        // ===============================================
        try {
            $pdo->beginTransaction();

            // STEP 1: Create or Update Guest
            if (empty($guestId)) {
                // New Guest
                $stmt = $pdo->prepare("INSERT INTO guests (tenant_id, full_name, mobile, email, identity_card_type, identity_card_number, city, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenantId, $guestName, $mobile, $email, $idType, $idNumber, $city, $state]);
                $guestId = $pdo->lastInsertId();
            } else {
                // Update existing guest details
                $stmt = $pdo->prepare("UPDATE guests SET full_name = ?, email = ?, identity_card_type = ?, identity_card_number = ?, city = ?, state = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$guestName, $email, $idType, $idNumber, $city, $state, $guestId, $tenantId]);
            }

            // STEP 2: Create Booking
            $checkIn = date('Y-m-d H:i:s');
            $checkOut = date('Y-m-d H:i:s', strtotime("+$nights days"));
            $uniqueBookingId = 'BK' . date('YmdHis') . rand(100, 999);

            $stmt = $pdo->prepare("INSERT INTO bookings (tenant_id, guest_id, room_id, unique_booking_id, check_in, check_out, status, total_amount, paid_amount, booking_source) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, 'walk-in')");
            $stmt->execute([$tenantId, $guestId, $roomId, $uniqueBookingId, $checkIn, $checkOut, $totalAmount, $advancePayment]);
            $bookingId = $pdo->lastInsertId();

            // STEP 3: CRITICAL - Lock the Room (Set to Occupied)
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$roomId, $tenantId]);

            // STEP 4: Record Advance Payment (if any)
            if ($advancePayment > 0) {
                $invoiceNumber = 'INV' . date('YmdHis');
                $stmt = $pdo->prepare("INSERT INTO transactions (tenant_id, booking_id, invoice_number, type, category, amount, payment_mode, description) VALUES (?, ?, ?, 'credit', 'Advance Payment', ?, ?, 'Advance payment at check-in')");
                $stmt->execute([$tenantId, $bookingId, $invoiceNumber, $advancePayment, $paymentMode]);
            }

            // COMMIT - All steps successful!
            $pdo->commit();

            // Redirect to Dashboard with success message
            $_SESSION['flash_success'] = "Check-In Successful! Booking ID: $uniqueBookingId";
            header('Location: dashboard.php');
            exit;

        } catch (Exception $e) {
            // ROLLBACK - Something went wrong
            $pdo->rollBack();
            $error = "Check-In Failed: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In | <?= htmlspecialchars($hotelName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 min-h-screen">

    <!-- Header -->
    <header class="glass sticky top-0 z-50 px-6 py-4 shadow-md">
        <div class="flex justify-between items-center max-w-7xl mx-auto">
            <div>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-800 text-sm">← Back to Dashboard</a>
                <h1 class="text-2xl font-bold text-gray-800">New Check-In</h1>
            </div>
            <span class="text-gray-600 text-sm"><?= htmlspecialchars($hotelName) ?></span>
        </div>
    </header>

    <main class="max-w-5xl mx-auto p-6" x-data="checkinApp()">

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <input type="hidden" name="action" value="checkin">
            <input type="hidden" name="guest_id" x-model="guestId">

            <!-- LEFT: Guest Details -->
            <div class="glass rounded-2xl p-6 shadow-xl space-y-5">
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2">Guest Details</h2>

                <!-- Mobile Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                    <div class="flex gap-2">
                        <input type="tel" name="mobile" x-model="mobile" required maxlength="10" pattern="[0-9]{10}"
                            class="flex-1 border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                            placeholder="10-digit mobile">
                        <button type="button" @click="searchGuest()"
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl transition">
                            Search
                        </button>
                    </div>
                    <p x-show="guestStatus === 'found'" class="text-green-600 text-xs mt-1">✅ Returning Guest Found!</p>
                    <p x-show="guestStatus === 'not_found'" class="text-blue-600 text-xs mt-1">🆕 New Guest - Please
                        fill details.</p>
                </div>

                <!-- Guest Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="guest_name" x-model="guestName" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                        placeholder="Guest's full name">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" x-model="email"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                        placeholder="guest@email.com">
                </div>

                <!-- ID Proof -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                        <select name="id_type" x-model="idType"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none">
                            <option value="">Select</option>
                            <option value="Aadhaar">Aadhaar Card</option>
                            <option value="PAN">PAN Card</option>
                            <option value="Passport">Passport</option>
                            <option value="Driving">Driving License</option>
                            <option value="Voter">Voter ID</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                        <input type="text" name="id_number" x-model="idNumber"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                            placeholder="XXXX-XXXX-XXXX">
                    </div>
                </div>

                <!-- City/State -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" x-model="city"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                            placeholder="City">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" x-model="state"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                            placeholder="State">
                    </div>
                </div>
            </div>

            <!-- RIGHT: Room & Billing -->
            <div class="glass rounded-2xl p-6 shadow-xl space-y-5">
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2">Room & Billing</h2>

                <!-- Room Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Room *</label>
                    <select name="room_id" x-model="roomId" @change="updatePrice()" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none text-lg">
                        <option value="">-- Choose Available Room --</option>
                        <?php foreach ($availableRooms as $room): ?>
                            <option value="<?= $room['id'] ?>" data-price="<?= $room['base_price'] ?>">
                                Room <?= htmlspecialchars($room['room_number']) ?> (<?= ucfirst($room['category']) ?>) -
                                ₹<?= number_format($room['base_price'], 0) ?>/night
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($availableRooms)): ?>
                        <p class="text-red-500 text-sm mt-1">⚠️ No rooms available!</p>
                    <?php endif; ?>
                </div>

                <!-- Nights -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Number of Nights</label>
                    <input type="number" name="nights" x-model="nights" min="1" max="30" @input="calculateTotal()"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                        value="1">
                </div>

                <!-- Total Amount -->
                <div class="bg-purple-50 rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Room Rate:</span>
                        <span class="font-semibold">₹<span x-text="roomPrice"></span>/night</span>
                    </div>
                    <div class="flex justify-between items-center mt-2 text-xl font-bold text-purple-700">
                        <span>Total Amount:</span>
                        <span>₹<span x-text="totalAmount"></span></span>
                    </div>
                    <input type="hidden" name="total_amount" x-model="totalAmount">
                </div>

                <!-- Advance Payment -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Advance Payment (₹)</label>
                    <input type="number" name="advance_payment" x-model="advancePayment" min="0"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none"
                        placeholder="0">
                </div>

                <!-- Payment Mode -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode</label>
                    <select name="payment_mode"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:outline-none">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                        <option value="online">Online Transfer</option>
                    </select>
                </div>

                <!-- Balance Due -->
                <div class="bg-yellow-50 rounded-xl p-4">
                    <div class="flex justify-between items-center text-lg">
                        <span class="text-gray-700">Balance Due at Checkout:</span>
                        <span class="font-bold text-yellow-700">₹<span
                                x-text="totalAmount - advancePayment"></span></span>
                    </div>
                </div>
            </div>

            <!-- Floating Submit Button -->
            <div class="lg:col-span-2">
                <button type="submit"
                    class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white text-xl font-bold py-4 px-6 rounded-2xl shadow-lg transform transition hover:-translate-y-1 flex items-center justify-center gap-3"
                    :disabled="!roomId || !guestName || !mobile">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Complete Check-In
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
                idType: '',
                idNumber: '',
                city: '',
                state: '',
                guestStatus: '', // 'found', 'not_found', ''
                roomId: '',
                roomPrice: 0,
                nights: 1,
                totalAmount: 0,
                advancePayment: 0,

                async searchGuest() {
                    if (this.mobile.length < 10) {
                        alert('Enter valid 10-digit mobile');
                        return;
                    }

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
                            this.idType = data.guest.identity_card_type || '';
                            this.idNumber = data.guest.identity_card_number || '';
                            this.city = data.guest.city || '';
                            this.state = data.guest.state || '';
                        } else {
                            this.guestStatus = 'not_found';
                            this.guestId = '';
                        }
                    } catch (e) {
                        console.error(e);
                    }
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

</body>

</html>