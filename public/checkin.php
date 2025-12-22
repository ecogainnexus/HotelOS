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
 * UI SECTION - THE SKIN SWAP (ENTERPRISE SCI-FI + RESPONSIVE SIDEBAR)
 * ============================================================================
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
            
            /* Glass */
            --glass-surface: rgba(21, 26, 35, 0.95);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-deep); 
            color: var(--text-main);
            overflow: hidden; /* For Sticky Sidebar */
        }

        .font-tech { font-family: 'Rajdhani', sans-serif; }

        /* Subtle Background Mesh */
        .bg-mesh {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.05), transparent 25%);
            z-index: -1;
            pointer-events: none;
        }

        /* Responsive Layout Components */
        
        /* 1. Sidebar (Desktop Only) */
        .sidebar {
            background-color: var(--bg-card);
            border-right: 1px solid var(--border-subtle);
        }

        /* 2. Bottom Nav (Mobile Only) */
        .bottom-nav {
            background-color: var(--bg-card);
            border-top: 1px solid var(--border-subtle);
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* Enterprise Card */
        .ent-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }

        /* Disciplined Inputs */
        .ent-input {
            background-color: #0F131A;
            border: 1px solid var(--border-subtle);
            color: white;
            transition: all 0.2s ease;
        }
        
        .ent-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 1px var(--accent-primary);
            background-color: #131820;
        }

        .ent-input::placeholder { color: var(--text-muted); opacity: 0.7; }
        
        .ent-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
        }

        /* Primary Action Button */
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

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-deep); }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    </style>
</head>
<body class="flex h-screen w-full">
    <div class="bg-mesh"></div>

    <!-- SIDEBAR (Desktop) -->
    <aside class="hidden lg:flex flex-col w-64 sidebar h-full z-20 shrink-0">
        <div class="p-6 border-b border-[#1E293B] flex items-center gap-3">
             <div class="w-8 h-8 rounded bg-blue-600/20 flex items-center justify-center text-blue-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <h1 class="font-tech font-bold text-white tracking-wide text-lg">HOTELOS</h1>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-semibold">Enterprise v2.0</p>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded text-gray-400 hover:text-white hover:bg-white/5 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            <a href="checkin.php" class="flex items-center gap-3 px-3 py-2.5 rounded bg-blue-600/10 text-blue-400 border border-blue-600/20 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <span class="text-sm font-bold">New Check-In</span>
            </a>
             <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded text-gray-500 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span class="text-sm font-medium">Check-Out</span>
            </a>
        </nav>

        <div class="p-4 border-t border-[#1E293B]">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs font-bold text-white">
                    <?= substr($userName, 0, 1) ?>
                </div>
                <div>
                    <p class="text-xs text-white font-medium truncat"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[10px] text-green-500">System Online</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENT AREA -->
    <div class="flex-1 flex flex-col h-full relative overflow-hidden">
        
        <!-- Mobile Header (Visible only on small screens) -->
        <header class="lg:hidden flex items-center justify-between p-4 bg-[#151A23] border-b border-[#1E293B] shrink-0">
            <span class="font-tech font-bold text-lg tracking-wider text-white">CHECK-IN</span>
            <div class="h-8 w-8 rounded-full bg-gray-800 flex items-center justify-center text-xs text-white font-bold border border-gray-700">
                <?= substr($userName, 0, 1) ?>
            </div>
        </header>

        <!-- Scrollable Main Content -->
        <main class="flex-1 overflow-y-auto w-full p-4 lg:p-8 pb-32 lg:pb-8" x-data="checkinApp()">
            
            <?php if ($error): ?>
                <div class="max-w-6xl mx-auto mb-6 bg-red-500/10 border border-red-500/20 text-red-200 px-4 py-3 rounded-lg flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="guest_id" x-model="guestId">

                <!-- LEFT COLUMN: GUEST IDENTITY (5/12) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="ent-card rounded-xl p-5 md:p-6">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[#1E293B]">
                             <div class="w-8 h-8 rounded bg-blue-500/10 flex items-center justify-center text-blue-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                             </div>
                             <h2 class="font-tech text-base font-bold text-white tracking-wide">GUEST IDENTITY</h2>
                        </div>

                        <!-- Mobile Number -->
                        <div class="mb-5">
                            <label class="label-text block mb-2">Mobile Number <span class="text-red-400">*</span></label>
                            <div class="flex gap-0 border border-[#1E293B] rounded-lg overflow-hidden focus-within:ring-1 focus-within:ring-blue-500 focus-within:border-blue-500">
                                <span class="bg-[#0F131A] text-gray-500 px-3 py-3 font-mono text-sm flex items-center border-r border-[#1E293B]">+91</span>
                                <input type="tel" name="mobile" x-model="mobile" required maxlength="10" 
                                    class="flex-1 bg-[#0F131A] text-white px-4 py-3 outline-none font-mono tracking-wide placeholder-gray-700"
                                    placeholder="98765 43210">
                                <button type="button" @click="searchGuest()" 
                                    class="bg-[#1E293B] hover:bg-blue-600 hover:text-white text-blue-400 px-4 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </button>
                            </div>
                            <div class="mt-2 h-4">
                                <p x-show="guestStatus === 'found'" class="text-green-500 text-[10px] font-bold flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> RETURNING GUEST</p>
                                <p x-show="guestStatus === 'not_found'" class="text-blue-400 text-[10px] font-bold flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-blue-400 rounded-full"></span> NEW ENTRY</p>
                            </div>
                        </div>

                        <!-- Full Name -->
                        <div class="mb-5">
                            <label class="label-text block mb-2">Full Name <span class="text-red-400">*</span></label>
                            <input type="text" name="guest_name" x-model="guestName" required
                                class="ent-input w-full px-4 py-3 rounded-lg font-medium" placeholder="Guest Name">
                        </div>

                         <!-- Email -->
                        <div class="mb-5">
                            <label class="label-text block mb-2">Email</label>
                            <input type="email" name="email" x-model="email"
                                class="ent-input w-full px-4 py-3 rounded-lg" placeholder="Email Address">
                        </div>

                        <!-- ID Proof -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-1">
                                <label class="label-text block mb-2">ID Type</label>
                                <select name="id_type" x-model="idType" class="ent-input ent-select w-full px-2 py-3 rounded-lg text-sm">
                                    <option value="">None</option>
                                    <option value="Aadhaar">Aadhaar</option>
                                    <option value="PAN">PAN</option>
                                    <option value="Passport">Passport</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="label-text block mb-2">ID Number</label>
                                <input type="text" name="id_number" x-model="idNumber" class="ent-input w-full px-4 py-3 rounded-lg" placeholder="XXXX">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: ROOM & BILLING (7/12) -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="ent-card rounded-xl p-5 md:p-6">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-[#1E293B]">
                             <div class="flex items-center gap-3">
                                 <div class="w-8 h-8 rounded bg-purple-500/10 flex items-center justify-center text-purple-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                 </div>
                                 <h2 class="font-tech text-base font-bold text-white tracking-wide">ROOM ALLOCATION</h2>
                             </div>
                             <span class="bg-[#1E293B] text-[10px] font-bold px-2 py-1 rounded text-gray-400 border border-[#2D3748]">
                                <?= count($availableRooms) ?> AVAIL
                            </span>
                        </div>

                        <div class="mb-6">
                            <label class="label-text block mb-2">Select Unit <span class="text-red-400">*</span></label>
                            <select name="room_id" x-model="roomId" @change="updatePrice()" required
                                class="ent-input ent-select w-full px-4 py-4 rounded-lg text-lg font-bold text-white">
                                <option value="" class="text-gray-500">-- Select Available Unit --</option>
                                <?php foreach ($availableRooms as $room): ?>
                                <option value="<?= $room['id'] ?>" data-price="<?= $room['base_price'] ?>">
                                    Room <?= htmlspecialchars($room['room_number']) ?> &nbsp;•&nbsp; <?= ucfirst($room['category']) ?> &nbsp;•&nbsp; ₹<?= number_format($room['base_price'], 0) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($availableRooms)): ?>
                                <p class="text-red-400 text-xs mt-2 font-bold">⚠️ All rooms occupied</p>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                             <div class="col-span-1">
                                <label class="label-text block mb-2">Duration</label>
                                <div class="flex items-center bg-[#151A23] rounded-lg border border-[#1E293B]">
                                    <button type="button" @click="if(nights>1) { nights--; calculateTotal() }" class="px-3 py-3 text-gray-400 hover:text-white">-</button>
                                    <input type="number" name="nights" x-model="nights" readonly class="w-full bg-transparent text-center font-bold text-white outline-none" value="1">
                                    <button type="button" @click="if(nights<30) { nights++; calculateTotal() }" class="px-3 py-3 text-gray-400 hover:text-white">+</button>
                                </div>
                             </div>
                             <div class="col-span-1">
                                <label class="label-text block mb-2">Total Rate</label>
                                <div class="px-4 py-3 rounded-lg bg-[#0F131A] border border-[#1E293B] text-white font-mono font-bold text-center">
                                    ₹<span x-text="totalAmount">0</span>
                                </div>
                                <input type="hidden" name="total_amount" x-model="totalAmount">
                             </div>
                        </div>

                        <div class="bg-[#0F131A] rounded-lg p-4 border border-[#1E293B]">
                             <label class="label-text block mb-3 text-green-500">PAYMENT & ADVANCE</label>
                             <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <input type="number" name="advance_payment" x-model="advancePayment" min="0" @input="calculateTotal()"
                                        class="ent-input w-full px-4 py-2 rounded text-sm font-bold placeholder-gray-600" placeholder="Advance ₹">
                                </div>
                                <div>
                                    <select name="payment_mode" class="ent-input ent-select w-full px-4 py-2 rounded text-sm">
                                        <option value="cash">Cash</option>
                                        <option value="upi">UPI</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>
                             </div>
                             <div class="mt-3 pt-3 border-t border-[#1E293B] flex justify-between items-center">
                                <span class="text-xs text-gray-500 font-bold uppercase">Pending Due</span>
                                <span class="text-lg font-bold text-yellow-500 font-mono">₹<span x-text="totalAmount - advancePayment">0</span></span>
                             </div>
                        </div>
                    </div>
                </div>

                <!-- DESKTOP SUBMIT (Hidden on Mobile) -->
                <div class="hidden lg:block lg:col-span-12 text-right">
                    <button type="submit" 
                        class="btn-primary px-8 py-4 rounded-lg flex items-center justify-center gap-3 text-white float-right w-64"
                        :disabled="!roomId || !guestName || !mobile">
                        <span class="uppercase tracking-widest text-sm font-bold">Proceed Check-In</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
                
                <!-- MOBILE STICKY FOOTER (Hidden on Desktop) -->
                <div class="lg:hidden fixed bottom-14 left-0 w-full p-4 bg-[#151A23]/95 backdrop-blur border-t border-[#1E293B] z-40">
                    <button type="submit" 
                        class="btn-primary w-full py-3.5 rounded-lg flex items-center justify-center gap-2 text-white shadow-lg shadow-blue-900/20"
                        :disabled="!roomId || !guestName || !mobile">
                        <span class="uppercase tracking-widest text-sm font-bold">Confirm Check-In</span>
                    </button>
                </div>

            </form>
        </main>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION -->
    <nav class="lg:hidden fixed bottom-0 left-0 w-full bg-[#0F131A] border-t border-[#1E293B] z-50 pb-safe">
        <div class="grid grid-cols-4 h-14">
            <a href="dashboard.php" class="flex flex-col items-center justify-center text-gray-500 hover:text-white transition-colors">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="text-[9px] font-bold uppercase tracking-wide">Home</span>
            </a>
            <a href="checkin.php" class="flex flex-col items-center justify-center text-blue-500 bg-blue-500/5 relative">
                <div class="absolute top-0 left-0 w-full h-0.5 bg-blue-500"></div>
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <span class="text-[9px] font-bold uppercase tracking-wide">Check-In</span>
            </a>
            <a href="#" class="flex flex-col items-center justify-center text-gray-600 cursor-not-allowed">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span class="text-[9px] font-bold uppercase tracking-wide">Check-Out</span>
            </a>
             <a href="#" class="flex flex-col items-center justify-center text-gray-600 cursor-not-allowed">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span class="text-[9px] font-bold uppercase tracking-wide">Rooms</span>
            </a>
        </div>
    </nav>
    <style>
        .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
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
                            this.idType = data.guest.identity_card_type || '';
                            this.idNumber = data.guest.identity_card_number || '';
                            this.city = data.guest.city || '';
                            this.state = data.guest.state || '';
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
</body>
</html>