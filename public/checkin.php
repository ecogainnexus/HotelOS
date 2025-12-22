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
    <title>Check-In | <?= htmlspecialchars($hotelName) ?> (Space Command)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --neon-blue: #00d4ff;
            --neon-purple: #a855f7;
            --dark-space: #0a0a0f;
            --card-glass: rgba(20, 20, 35, 0.7);
            --content-bg: rgba(255, 255, 255, 0.03);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-space);
            color: #e2e8f0;
        }

        .font-orbitron {
            font-family: 'Orbitron', sans-serif;
        }

        /* Space Background */
        .antigravity-bg {
            background:
                radial-gradient(circle at 10% 20%, rgba(56, 189, 248, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.1) 0%, transparent 40%),
                linear-gradient(to bottom right, #0a0a0f, #111827);
        }

        /* Glass Components */
        .glass-panel {
            background: var(--card-glass);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        /* Neon Inputs */
        .neon-input {
            background: var(--content-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }

        .neon-input:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
        }

        .neon-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .neon-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }

        /* Neon Button */
        .neon-btn-primary {
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
            transition: all 0.3s ease;
        }

        .neon-btn-primary:hover {
            box-shadow: 0 6px 20px rgba(168, 85, 247, 0.5);
            transform: translateY(-2px);
        }

        /* Sidebar Link Active State */
        .nav-link.active {
            background: rgba(0, 212, 255, 0.1);
            border-left: 3px solid var(--neon-blue);
            color: white;
        }

        .nav-link {
            border-left: 3px solid transparent;
            color: #94a3b8;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.03);
        }

        /* Thin Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body class="antigravity-bg h-screen w-full flex overflow-hidden text-gray-200">

    <!-- DESKTOP SIDEBAR (Hidden on Mobile) -->
    <aside class="hidden lg:flex flex-col w-64 glass-panel border-r-0 border-y-0 h-full z-20">
        <div class="p-6 flex items-center gap-3">
            <div
                class="w-10 h-10 rounded-xl bg-gradient-to-br from-neon-blue to-neon-purple flex items-center justify-center shadow-lg shadow-neon-blue/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
            <div>
                <h2 class="font-orbitron font-bold text-lg text-white tracking-wide">HotelOS</h2>
                <p class="text-[10px] uppercase tracking-widest text-neon-blue">Enterprise</p>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-2 mt-4">
            <a href="dashboard.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-r-lg transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                    </path>
                </svg>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="checkin.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-r-lg transition-all">
                <svg class="w-5 h-5 text-neon-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                <span class="font-medium text-white">New Check-In</span>
            </a>
            <a href="#"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-r-lg transition-all opacity-50 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                    </path>
                </svg>
                <span class="font-medium">Check-Out</span>
            </a>
        </nav>

        <div class="p-4 border-t border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold">
                    <?= substr($userName, 0, 1) ?>
                </div>
                <div>
                    <p class="text-sm font-medium text-white"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-xs text-gray-500">Super Admin</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENT AREA -->
    <div class="flex-1 flex flex-col h-full relative overflow-hidden">

        <!-- Mobile Header -->
        <header class="lg:hidden flex items-center justify-between p-4 glass-panel border-x-0 border-t-0 z-30">
            <a href="dashboard.php" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <span class="font-orbitron font-bold text-white tracking-wider">NEW CHECK-IN</span>
            <div class="w-6"></div> <!-- Spacer for center alignment -->
        </header>

        <!-- Scrollable Main Content -->
        <main class="flex-1 overflow-y-auto w-full p-4 pb-24 lg:p-8 lg:pb-8" x-data="checkinApp()">

            <?php if ($error): ?>
                <div
                    class="max-w-6xl mx-auto mb-6 bg-red-500/10 border border-red-500/50 text-red-200 px-4 py-3 rounded-xl flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="guest_id" x-model="guestId">

                <!-- LEFT COLUMN: Guest Identity (5/12 cols) -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="glass-panel rounded-2xl p-5 lg:p-6">
                        <div class="flex items-center gap-2 mb-6 text-neon-purple">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <h2 class="font-orbitron font-semibold text-white tracking-wide uppercase text-sm">Guest
                                Identity</h2>
                        </div>

                        <!-- Mobile Number Search -->
                        <div class="mb-5">
                            <label
                                class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Mobile
                                Number</label>
                            <div class="flex gap-2">
                                <input type="tel" name="mobile" x-model="mobile" required maxlength="10"
                                    pattern="[0-9]{10}"
                                    class="neon-input flex-1 px-4 py-3 rounded-lg text-lg font-mono focus:ring-1 focus:ring-neon-blue"
                                    placeholder="98765 43210">
                                <button type="button" @click="searchGuest()"
                                    class="bg-white/5 hover:bg-neon-purple/20 border border-white/10 text-neon-purple p-3 rounded-lg transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </button>
                            </div>
                            <p x-show="guestStatus === 'found'"
                                class="text-green-400 text-xs mt-2 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                Returning Guest Detected
                            </p>
                            <p x-show="guestStatus === 'not_found'"
                                class="text-neon-blue text-xs mt-2 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                New Guest Entry
                            </p>
                        </div>

                        <!-- Full Name -->
                        <div class="mb-5">
                            <label
                                class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Full
                                Name</label>
                            <input type="text" name="guest_name" x-model="guestName" required
                                class="neon-input w-full px-4 py-3 rounded-lg" placeholder="Enter guest name">
                        </div>

                        <!-- Email -->
                        <div class="mb-5">
                            <label
                                class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Email
                                Address</label>
                            <input type="email" name="email" x-model="email"
                                class="neon-input w-full px-4 py-3 rounded-lg" placeholder="guest@example.com">
                        </div>

                        <!-- ID Proof Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">ID
                                    Type</label>
                                <select name="id_type" x-model="idType"
                                    class="neon-input neon-select w-full px-4 py-3 rounded-lg appearance-none">
                                    <option value="" class="bg-gray-900 text-gray-400">Select</option>
                                    <option value="Aadhaar" class="bg-gray-900">Aadhaar</option>
                                    <option value="PAN" class="bg-gray-900">PAN Card</option>
                                    <option value="Passport" class="bg-gray-900">Passport</option>
                                    <option value="Driving" class="bg-gray-900">Driving Lic</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">ID
                                    Number</label>
                                <input type="text" name="id_number" x-model="idNumber"
                                    class="neon-input w-full px-4 py-3 rounded-lg" placeholder="XXXX-XXXX">
                            </div>
                        </div>

                        <!-- Location Grid -->
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">City</label>
                                <input type="text" name="city" x-model="city"
                                    class="neon-input w-full px-4 py-3 rounded-lg" placeholder="City Name">
                            </div>
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">State</label>
                                <input type="text" name="state" x-model="state"
                                    class="neon-input w-full px-4 py-3 rounded-lg" placeholder="State Name">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Room & Billing (7/12 cols) -->
                <div class="lg:col-span-7 space-y-4">

                    <!-- Room Selection Card -->
                    <div class="glass-panel rounded-2xl p-5 lg:p-6">
                        <div class="flex items-center gap-2 mb-6 text-neon-blue">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                            <h2 class="font-orbitron font-semibold text-white tracking-wide uppercase text-sm">Room
                                Allocation</h2>
                        </div>

                        <div class="mb-6">
                            <label
                                class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Select
                                Room</label>
                            <select name="room_id" x-model="roomId" @change="updatePrice()" required
                                class="neon-input neon-select w-full px-4 py-4 rounded-xl text-lg appearance-none font-medium">
                                <option value="" class="bg-gray-900 text-gray-500">-- Choose Available Room --</option>
                                <?php foreach ($availableRooms as $room): ?>
                                    <option value="<?= $room['id'] ?>" data-price="<?= $room['base_price'] ?>"
                                        class="bg-gray-900 text-white">
                                        Room <?= htmlspecialchars($room['room_number']) ?> &nbsp;•&nbsp;
                                        <?= ucfirst($room['category']) ?> &nbsp;•&nbsp;
                                        ₹<?= number_format($room['base_price'], 0) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($availableRooms)): ?>
                                <p class="text-red-400 text-xs mt-2">⚠️ No rooms available. Please checkout a guest first.
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Nights</label>
                                <div class="relative">
                                    <button type="button" @click="if(nights>1) { nights--; calculateTotal() }"
                                        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white p-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 12H4"></path>
                                        </svg>
                                    </button>
                                    <input type="number" name="nights" x-model="nights" readonly
                                        class="neon-input w-full px-12 py-3 rounded-lg text-center font-bold text-lg"
                                        value="1">
                                    <button type="button" @click="if(nights<30) { nights++; calculateTotal() }"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white p-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Total
                                    Rate</label>
                                <div
                                    class="px-4 py-3 rounded-lg bg-neon-blue/10 border border-neon-blue/30 text-neon-blue font-bold text-lg text-right">
                                    ₹ <span x-text="totalAmount">0</span>
                                </div>
                                <input type="hidden" name="total_amount" x-model="totalAmount">
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="glass-panel rounded-2xl p-5 lg:p-6">
                        <div class="flex items-center gap-2 mb-6 text-green-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                </path>
                            </svg>
                            <h2 class="font-orbitron font-semibold text-white tracking-wide uppercase text-sm">Payment
                            </h2>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Advance
                                    (₹)</label>
                                <input type="number" name="advance_payment" x-model="advancePayment" min="0"
                                    @input="calculateTotal()"
                                    class="neon-input w-full px-4 py-3 rounded-lg text-white font-medium focus:border-green-400 focus:ring-green-400/20"
                                    placeholder="0">
                            </div>
                            <div>
                                <label
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 block">Mode</label>
                                <select name="payment_mode"
                                    class="neon-input neon-select w-full px-4 py-3 rounded-lg appearance-none">
                                    <option value="cash" class="bg-gray-900">Cash</option>
                                    <option value="upi" class="bg-gray-900">UPI / GPay</option>
                                    <option value="card" class="bg-gray-900">Credit Card</option>
                                    <option value="online" class="bg-gray-900">Bank Transfer</option>
                                </select>
                            </div>
                        </div>

                        <div class="bg-black/20 rounded-lg p-4 flex justify-between items-center border border-white/5">
                            <span class="text-sm text-gray-400">Balance Due at Checkout</span>
                            <span class="text-xl font-bold text-yellow-500">₹ <span
                                    x-text="totalAmount - advancePayment">0</span></span>
                        </div>
                    </div>
                </div>

                <!-- STICKY MOBILE ACTION BAR / DESKTOP BUTTON -->
                <div
                    class="fixed bottom-0 left-0 w-full lg:static lg:col-span-12 p-4 lg:p-0 glass-panel lg:bg-transparent lg:shadow-none lg:border-none z-40">
                    <button type="submit"
                        class="neon-btn-primary w-full py-4 rounded-xl text-white font-bold font-orbitron tracking-wider text-lg flex items-center justify-center gap-2"
                        :disabled="!roomId || !guestName || !mobile">
                        <span>CONFIRM CHECK-IN</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>

                <!-- Spacer for mobile scroll -->
                <div class="h-20 lg:hidden block w-full"></div>

            </form>
        </main>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION -->
    <nav class="lg:hidden fixed bottom-0 left-0 w-full glass-panel border-x-0 border-b-0 z-50 pb-safe">
        <div class="flex justify-around items-center p-3">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 text-gray-400 hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                <span class="text-[10px] font-medium uppercase">Home</span>
            </a>
            <a href="checkin.php" class="flex flex-col items-center gap-1 text-neon-blue">
                <div class="p-2 bg-neon-blue/10 rounded-full">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                        </path>
                    </svg>
                </div>
                <span class="text-[10px] font-bold uppercase">Check-In</span>
            </a>
            <a href="#" class="flex flex-col items-center gap-1 text-gray-500 cursor-not-allowed">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                    </path>
                </svg>
                <span class="text-[10px] font-medium uppercase">Check-Out</span>
            </a>
            <a href="#" class="flex flex-col items-center gap-1 text-gray-500 cursor-not-allowed">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span class="text-[10px] font-medium uppercase">Profile</span>
            </a>
        </div>
    </nav>
    <style>
        /* Padding for safe area on mobile (iPhone X+) */
        .pb-safe {
            padding-bottom: env(safe-area-inset-bottom);
        }
    </style>

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