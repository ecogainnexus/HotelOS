<?php
/**
 * public/dashboard.php
 * HotelOS Enterprise - Smart Dashboard
 * Phase 3.5: Hybrid Architecture (Logic + Design)
 */
session_start();

// Security: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';

// =====================================================
// ROLE 1: THE BACKEND ENGINEER (Data Fetching)
// =====================================================

$tenantId = $_SESSION['tenant_id'] ?? 1;
$userName = $_SESSION['user_name'] ?? 'Admin';
$hotelName = $_SESSION['hotel_name'] ?? 'HotelOS';

// Initialize stats with safe defaults
$totalRooms = 0;
$occupiedRooms = 0;
$availableRooms = 0;
$dirtyRooms = 0;
$todaysArrivals = 0;
$todaysRevenue = 0.00;
$rooms = [];
$recentBookings = [];

try {
    // 1. Total Rooms
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rooms WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $totalRooms = (int) $stmt->fetchColumn();

    // 2. Occupied Rooms
    $stmt = $pdo->prepare("SELECT COUNT(*) as occupied FROM rooms WHERE tenant_id = ? AND status = 'occupied'");
    $stmt->execute([$tenantId]);
    $occupiedRooms = (int) $stmt->fetchColumn();

    // 3. Available Rooms
    $stmt = $pdo->prepare("SELECT COUNT(*) as available FROM rooms WHERE tenant_id = ? AND status = 'available'");
    $stmt->execute([$tenantId]);
    $availableRooms = (int) $stmt->fetchColumn();

    // 4. Dirty Rooms
    $stmt = $pdo->prepare("SELECT COUNT(*) as dirty FROM rooms WHERE tenant_id = ? AND status = 'dirty'");
    $stmt->execute([$tenantId]);
    $dirtyRooms = (int) $stmt->fetchColumn();

    // 5. Today's Arrivals (Check-ins today)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as arrivals FROM bookings WHERE tenant_id = ? AND DATE(check_in) = ?");
    $stmt->execute([$tenantId, $today]);
    $todaysArrivals = (int) $stmt->fetchColumn();

    // 6. Today's Revenue (Sum of paid_amount for today's bookings)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) as revenue FROM bookings WHERE tenant_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$tenantId, $today]);
    $todaysRevenue = (float) $stmt->fetchColumn();

    // 7. Fetch All Rooms for Room Matrix
    $stmt = $pdo->prepare("SELECT id, room_number, floor_number, category, status, base_price FROM rooms WHERE tenant_id = ? ORDER BY room_number ASC");
    $stmt->execute([$tenantId]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Recent Bookings (Last 5)
    $stmt = $pdo->prepare("
        SELECT b.id, b.unique_booking_id, b.check_in, b.check_out, b.status, b.total_amount, r.room_number
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE b.tenant_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$tenantId]);
    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Silent fail - show zeros instead of crashing
    error_log("Dashboard Error: " . $e->getMessage());
}

// Calculate Occupancy Percentage
$occupancyPercent = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($hotelName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .room-card {
            transition: all 0.2s ease;
        }

        .room-card:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 min-h-screen">

    <!-- =====================================================
         ROLE 2: THE UI DESIGNER (Glassmorphism Layout)
         ===================================================== -->

    <!-- Header -->
    <header class="glass sticky top-0 z-50 px-6 py-4">
        <div class="flex justify-between items-center max-w-7xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($hotelName) ?></h1>
                <p class="text-sm text-gray-500">Command Center</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-gray-700">Welcome, <strong><?= htmlspecialchars($userName) ?></strong></span>
                <a href="logout.php"
                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm transition">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6 space-y-8">

        <!-- Stats Grid (4 Columns) -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            <!-- Total Rooms -->
            <div class="glass rounded-2xl p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Rooms</p>
                        <p class="text-4xl font-bold text-gray-800"><?= $totalRooms ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Occupancy -->
            <div class="glass rounded-2xl p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Occupancy</p>
                        <p class="text-4xl font-bold text-gray-800"><?= $occupancyPercent ?>%</p>
                        <p class="text-xs text-gray-400"><?= $occupiedRooms ?> of <?= $totalRooms ?> occupied</p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Today's Arrivals -->
            <div class="glass rounded-2xl p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Today's Arrivals</p>
                        <p class="text-4xl font-bold text-gray-800"><?= $todaysArrivals ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Today's Revenue -->
            <div class="glass rounded-2xl p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Today's Revenue</p>
                        <p class="text-4xl font-bold text-gray-800">₹<?= number_format($todaysRevenue, 0) ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

        </section>

        <!-- Two Column Layout: Room Matrix + Recent Activity -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Room Matrix (Left - 2/3 Width) -->
            <div class="lg:col-span-2 glass rounded-2xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Room Matrix</h2>

                <!-- Legend -->
                <div class="flex gap-4 mb-4 text-sm">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-400 rounded"></span> Available
                        (<?= $availableRooms ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-400 rounded"></span> Occupied
                        (<?= $occupiedRooms ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-yellow-400 rounded"></span> Dirty
                        (<?= $dirtyRooms ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-gray-400 rounded"></span>
                        Maintenance</span>
                </div>

                <!-- Room Grid -->
                <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-3">
                    <?php if (empty($rooms)): ?>
                        <p class="col-span-full text-gray-500 text-center py-8">No rooms found. Add rooms from Settings.</p>
                    <?php else: ?>
                        <?php foreach ($rooms as $room):
                            $statusColors = [
                                'available' => 'bg-green-100 border-green-400 text-green-800',
                                'occupied' => 'bg-red-100 border-red-400 text-red-800',
                                'dirty' => 'bg-yellow-100 border-yellow-400 text-yellow-800',
                                'maintenance' => 'bg-gray-100 border-gray-400 text-gray-600',
                            ];
                            $colorClass = $statusColors[$room['status']] ?? 'bg-white border-gray-300 text-gray-800';
                            ?>
                            <div x-data="{ hover: false }" @mouseenter="hover = true" @mouseleave="hover = false"
                                class="room-card cursor-pointer rounded-xl border-2 p-3 text-center <?= $colorClass ?>"
                                :class="hover ? 'shadow-lg' : ''">
                                <p class="font-bold text-lg"><?= htmlspecialchars($room['room_number']) ?></p>
                                <p class="text-xs capitalize"><?= $room['category'] ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity (Right - 1/3 Width) -->
            <div class="glass rounded-2xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Recent Activity</h2>

                <?php if (empty($recentBookings)): ?>
                    <p class="text-gray-500 text-center py-8">No bookings yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentBookings as $booking):
                            $statusBadge = [
                                'active' => 'bg-blue-100 text-blue-700',
                                'completed' => 'bg-green-100 text-green-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                'no_show' => 'bg-gray-100 text-gray-700',
                            ];
                            $badgeClass = $statusBadge[$booking['status']] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <div class="bg-white/50 rounded-lg p-3 border border-gray-200">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-800">Room
                                            <?= htmlspecialchars($booking['room_number'] ?? 'N/A') ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?= date('d M, h:i A', strtotime($booking['check_in'])) ?></p>
                                    </div>
                                    <span
                                        class="text-xs px-2 py-1 rounded-full <?= $badgeClass ?>"><?= ucfirst($booking['status']) ?></span>
                                </div>
                                <p class="text-sm font-medium text-gray-700 mt-1">
                                    ₹<?= number_format($booking['total_amount'], 0) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </section>

    </main>

    <!-- Footer -->
    <footer class="text-center py-6 text-gray-400 text-sm">
        <p>Powered by <strong>HotelOS Enterprise</strong> | Antigravity AI</p>
    </footer>

</body>

</html>