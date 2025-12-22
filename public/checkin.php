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
    $roomId = (int)($_POST['room_id'] ?? 0);
    $nights = (int)($_POST['nights'] ?? 1);
    $totalAmount = (float)($_POST['total_amount'] ?? 0);
    $advancePayment = (float)($_POST['advance_payment'] ?? 0);
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

/**
 * ============================================================================
 * UI SECTION - THE SKIN SWAP
 * ============================================================================
 * Design System: Enterprise Sci-Fi (Deep Space, High Contrast, Low Noise)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In Command | <?= htmlspecialchars($hotelName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Enterprise Palette */
            --bg-deep: #0B0E14;
            --bg-card: #151A23;
            --accent-primary: #3B82F6; /* Professional Blue */
            --accent-glow: rgba(59, 130, 246, 0.4);
            --text-main: #E2E8F0;
            --text-muted: #64748B;
            --border-subtle: #1E293B;
            --success-green: #10B981;
            
            /* Glass */
            --glass-surface: rgba(21, 26, 35, 0.85);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-deep); 
            color: var(--text-main);
            overflow-x: hidden;
        }

        .font-tech { font-family: 'Rajdhani', sans-serif; }

        /* Subtle Background Mesh - No loud gradients */
        .bg-mesh {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.05), transparent 25%);
            z-index: -1;
            pointer-events: none;
        }

        /* Enterprise Card */
        .ent-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.15);
        }

        /* Disciplined Inputs */
        .ent-input {
            background-color: #0F131A; /* Darker than card */
            border: 1px solid var(--border-subtle);
            color: white;
            transition: all 0.2s ease;
        }
        
        .ent-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 1px var(--accent-primary), 0 0 15px var(--accent-glow);
            background-color: #131820;
        }

        .ent-input::placeholder { color: var(--text-muted); opacity: 0.7; }

        /* Primary Action Button - Confident, not flashy */
        .btn-primary {
            background: var(--accent-primary);
            color: white;
            font-weight: 600;
            letter-spacing: 0.02em;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }
        
        .btn-primary:active { transform: translateY(1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }

        /* Select Chevron Fix */
        .ent-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
        }

        /* Utility Classes */
        .label-text {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-deep); }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Floating Panel Animation */
        .fade-in-up { animation: fadeInUp 0.4s ease-out; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden">
    <div class="bg-mesh"></div>

    <!-- DESKTOP TOP BAR (Receptionist Mode) -->
    <header class="hidden md:flex items-center justify-between px-6 py-4 bg-[#151A23] border-b border-[#1E293B] z-20">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-blue-600/10 flex items-center justify-center border border-blue-600/20">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <h1 class="font-tech text-xl font-bold text-white tracking-wide">HOTELOS <span class="text-blue-500 font-normal">CMD</span></h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold">Enterprise Edition</p>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <a href="dashboard.php" class="text-gray-400 hover:text-white text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Return to Dashboard
            </a>
            <div class="h-8 w-[1px] bg-gray-700"></div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <p class="text-sm text-white font-medium"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-xs text-green-500">• Online</p>
                </div>
                <div class="w-9 h-9 rounded-full bg-gray-700 flex items-center justify-center text-xs font-bold ring-2 ring-[#0B0E14]">
                    <?= substr($userName, 0, 1) ?>
                </div>
            </div>
        </div>
    </header>

    <!-- MOBILE HEADER (Owner Mode) -->
    <header class="md:hidden flex items-center justify-between p-4 bg-[#151A23] border-b border-[#1E293B]">
        <a href="dashboard.php" class="p-2 -ml-2 text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <span class="font-tech font-bold text-lg tracking-wider">NEW CHECK-IN</span>
        <div class="w-8"></div>
    </header>

    <!-- FLUID LAYOUT CONTAINER -->
    <div class="flex-1 overflow-y-auto" x-data="checkinApp()">
        <main class="w-full max-w-7xl mx-auto p-4 md:p-8 pb-32 md:pb-12">
            
            <?php if ($error): ?>
                <div class="fade-in-up mb-6 bg-red-500/10 border border-red-500/20 text-red-200 px-4 py-4 rounded-lg flex items-start gap-3 shadow-lg">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <h4 class="font-bold text-sm text-red-400">Submission Error</h4>
                        <p class="text-sm opacity-80 mt-1"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="guest_id" x-model="guestId">

                <!-- LEFT COLUMN: IDENTIFICATION (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- Search Panel -->
                    <div class="ent-card rounded-xl p-5 md:p-6 fade-in-up" style="animation-delay: 0ms;">
                        <h2 class="font-tech text-lg font-bold text-white mb-6 flex items-center gap-2">
                             <span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>
                             GUEST IDENTITY
                        </h2>

                        <!-- Mobile Search -->
                        <div class="mb-5">
                            <label class="label-text block mb-2">Mobile Number <span class="text-red-400">*</span></label>
                            <div class="flex gap-0 border border-[#1E293B] rounded-lg overflow-hidden focus-within:ring-1 focus-within:ring-blue-500 focus-within:border-blue-500 transition-all">
                                <span class="bg-[#0F131A] text-gray-500 px-3 py-3 font-mono text-sm flex items-center border-r border-[#1E293B]">+91</span>
                                <input type="tel" name="mobile" x-model="mobile" required maxlength="10" 
                                    class="flex-1 bg-[#0F131A] text-white px-4 py-3 outline-none font-mono text-lg tracking-wide placeholder-gray-700"
                                    placeholder="98765 43210">
                                <button type="button" @click="searchGuest()" 
                                    class="bg-[#1E293B] hover:bg-blue-600 hover:text-white text-blue-400 px-4 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </button>
                            </div>
                            
                            <!-- Status Indicators -->
                            <div class="mt-2 h-5">
                                <p x-show="guestStatus === 'found'" x-transition class="text-green-500 text-xs font-bold flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> RETURNING GUEST DETECTED
                                </p>
                                <p x-show="guestStatus === 'not_found'" x-transition class="text-blue-400 text-xs font-bold flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 bg-blue-400 rounded-full"></span> NEW GUEST ENTRY
                                </p>
                            </div>
                        </div>

                        <!-- Basic Details -->
                        <div class="space-y-4">
                            <div>
                                <label class="label-text block mb-2">Full Name <span class="text-red-400">*</span></label>
                                <input type="text" name="guest_name" x-model="guestName" required
                                    class="ent-input w-full px-4 py-3 rounded-lg font-medium"
                                    placeholder="e.g. Rahul Sharma">
                            </div>
                            <div>
                                <label class="label-text block mb-2">Email Address</label>
                                <input type="email" name="email" x-model="email"
                                    class="ent-input w-full px-4 py-3 rounded-lg"
                                    placeholder="name@company.com">
                            </div>
                        </div>
                    </div>

                    <!-- KYC Panel -->
                    <div class="ent-card rounded-xl p-5 md:p-6 fade-in-up" style="animation-delay: 50ms;">
                         <h3 class="label-text text-blue-400 mb-4 border-b border-[#1E293B] pb-2">LEGAL / KYC</h3>
                         
                         <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="label-text block mb-2">ID Type</label>
                                <div class="relative">
                                    <select name="id_type" x-model="idType"
                                        class="ent-input ent-select w-full px-4 py-3 rounded-lg appearance-none">
                                        <option value="">None</option>
                                        <option value="Aadhaar">Aadhaar</option>
                                        <option value="PAN">PAN Card</option>
                                        <option value="Passport">Passport</option>
                                        <option value="Driving">License</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="label-text block mb-2">ID Number</label>
                                <input type="text" name="id_number" x-model="idNumber"
                                    class="ent-input w-full px-4 py-3 rounded-lg uppercase placeholder-gray-700"
                                    placeholder="XXXX XXXX">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <input type="text" name="city" x-model="city" class="ent-input w-full px-4 py-3 rounded-lg" placeholder="City">
                            <input type="text" name="state" x-model="state" class="ent-input w-full px-4 py-3 rounded-lg" placeholder="State">
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: BUSINESS LOGIC (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Room Allocation -->
                    <div class="ent-card rounded-xl p-5 md:p-6 fade-in-up" style="animation-delay: 100ms;">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="font-tech text-lg font-bold text-white flex items-center gap-2">
                                <span class="w-1.5 h-6 bg-purple-500 rounded-full"></span>
                                ROOM ALLOCATION
                            </h2>
                            <span class="bg-[#1E293B] text-xs font-bold px-3 py-1 rounded text-gray-400">
                                <?= count($availableRooms) ?> AVAILABLE
                            </span>
                        </div>

                        <div class="mb-6">
                            <label class="label-text block mb-2">Select Room Unit <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <select name="room_id" x-model="roomId" @change="updatePrice()" required
                                    class="ent-input ent-select w-full px-4 py-4 rounded-lg text-lg appearance-none font-semibold text-white">
                                    <option value="" class="text-gray-500">-- Select Available Unit --</option>
                                    <?php foreach ($availableRooms as $room): ?>
                                    <option value="<?= $room['id'] ?>" data-price="<?= $room['base_price'] ?>">
                                        Room <?= htmlspecialchars($room['room_number']) ?> &nbsp;•&nbsp; <?= ucfirst($room['category']) ?> &nbsp;•&nbsp; ₹<?= number_format($room['base_price'], 0) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute left-0 top-0 h-full w-1 bg-blue-500 rounded-l-lg" x-show="roomId"></div>
                            </div>
                            <?php if (empty($availableRooms)): ?>
                                <p class="text-red-400 text-xs mt-2 font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    NO VACANCIES - ALL ROOMS OCCUPIED
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Duration & Cost Calculator -->
                        <div class="bg-[#0F131A] rounded-lg p-4 border border-[#1E293B] grid grid-cols-12 gap-4 items-center">
                            
                            <!-- Nights Stepper -->
                            <div class="col-span-5 md:col-span-4">
                                <label class="label-text block mb-2">Duration (Nights)</label>
                                <div class="flex items-center bg-[#151A23] rounded-lg border border-[#1E293B]">
                                    <button type="button" @click="if(nights>1) { nights--; calculateTotal() }" class="px-3 py-2 text-gray-400 hover:text-white hover:bg-[#1E293B] transition">-</button>
                                    <input type="number" name="nights" x-model="nights" readonly class="w-full bg-transparent text-center font-bold text-white outline-none" value="1">
                                    <button type="button" @click="if(nights<30) { nights++; calculateTotal() }" class="px-3 py-2 text-gray-400 hover:text-white hover:bg-[#1E293B] transition">+</button>
                                </div>
                            </div>

                            <div class="col-span-2 md:col-span-1 flex justify-center pt-6 text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </div>

                            <!-- Rate Info -->
                            <div class="col-span-5 md:col-span-3">
                                <label class="label-text block mb-2">Rate / Night</label>
                                <div class="font-mono text-gray-400">₹<span x-text="roomPrice">0</span></div>
                            </div>

                            <!-- Total -->
                            <div class="col-span-12 md:col-span-4 mt-2 md:mt-0 pt-2 md:pt-0 border-t md:border-t-0 border-[#1E293B] md:border-l md:pl-4 text-right md:text-left">
                                <label class="label-text block mb-1 text-blue-400">Total Calculation</label>
                                <div class="text-2xl font-bold text-white font-mono">₹<span x-text="totalAmount">0</span></div>
                                <input type="hidden" name="total_amount" x-model="totalAmount">
                            </div>

                        </div>
                    </div>

                    <!-- Financials -->
                    <div class="ent-card rounded-xl p-5 md:p-6 fade-in-up" style="animation-delay: 150ms;">
                        <h3 class="label-text text-green-500 mb-4 border-b border-[#1E293B] pb-2">PAYMENT & SETTLEMENT</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="label-text block mb-2">Advance Collected</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-500">₹</span>
                                    <input type="number" name="advance_payment" x-model="advancePayment" min="0" @input="calculateTotal()"
                                        class="ent-input w-full pl-8 pr-4 py-3 rounded-lg font-bold text-white"
                                        placeholder="0">
                                </div>
                            </div>
                            <div>
                                <label class="label-text block mb-2">Payment Mode</label>
                                <select name="payment_mode" class="ent-input ent-select w-full px-4 py-3 rounded-lg appearance-none">
                                    <option value="cash">Cash Settlement</option>
                                    <option value="upi">UPI / QR Scan</option>
                                    <option value="card">Credit/Debit Card</option>
                                    <option value="online">Wire Transfer</option>
                                </select>
                            </div>
                        </div>

                         <div class="mt-4 p-4 rounded-lg bg-[#0F131A] border border-[#1E293B] flex justify-between items-center">
                            <div class="text-sm text-gray-400 font-medium">PENDING BALANCE</div>
                            <div class="text-xl font-bold text-yellow-500 font-mono">₹<span x-text="totalAmount - advancePayment">0</span></div>
                        </div>
                    </div>
                </div>

                <!-- STICKY ACTION FOOTER (Mobile) / SUBMIT BUTTON (Desktop) -->
                <div class="fixed bottom-0 left-0 w-full p-4 bg-[#151A23]/90 backdrop-blur border-t border-[#1E293B] md:static md:bg-transparent md:border-none md:p-0 lg:col-span-12 z-40">
                    <button type="submit" 
                        class="btn-primary w-full md:w-auto md:float-right px-8 py-4 rounded-lg flex items-center justify-center gap-3 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!roomId || !guestName || !mobile">
                        <span class="uppercase tracking-widest text-sm font-bold">Initiate Check-In & Lock Room</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                    <!-- Mobile Safe Area -->
                    <div class="h-4 md:hidden"></div> 
                </div>

            </form>
        </main>
    </div>

    <!-- MOBILE BOTTOM NAVBAR (Alternative to Sidebar) -->
    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-[#151A23] border-t border-[#1E293B] z-50 pb-safe hidden">
        <!-- Hidden for this specific page design as we use sticky button, 
             but kept structure for consistency if needed later -->
    </nav>

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
                        // Optional: Show toast
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