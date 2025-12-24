<?php
/**
 * public/dashboard.php
 * HotelOS Enterprise - Smart Dashboard
 * Phase 3.5: Hybrid Architecture (Logic + Design)
 * THEME ENGINE: INTEGRATED (Phase 3)
 */
session_start();

// Security: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../');
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once 'layout.php';

// Flash Messages
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// =====================================================
// BACKEND ENGINE
// =====================================================

$tenantId = $_SESSION['tenant_id'] ?? 1;
$userName = $_SESSION['user_name'] ?? 'Admin';
$hotelName = $_SESSION['hotel_name'] ?? 'HotelOS';

// Initialize stats
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

    // 5. Today's Arrivals
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as arrivals FROM bookings WHERE tenant_id = ? AND DATE(check_in) = ?");
    $stmt->execute([$tenantId, $today]);
    $todaysArrivals = (int) $stmt->fetchColumn();

    // 6. Today's Revenue
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) as revenue FROM bookings WHERE tenant_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$tenantId, $today]);
    $todaysRevenue = (float) $stmt->fetchColumn();

    // 7. Room Matrix
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
    error_log("Dashboard Error: " . $e->getMessage());
}

$occupancyPercent = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;

ob_start();
?>

<!-- Mobile Header -->
<header
    class="lg:hidden flex items-center justify-between p-4 app-card border-b-0 border-r-0 border-l-0 border-[var(--glass-border)] shrink-0 sticky top-0 z-30">
    <span class="font-tech font-bold text-lg tracking-wider app-text-main">DASHBOARD</span>
    <div class="flex items-center gap-3">
        <span class="text-xs app-text-muted font-medium"><?= htmlspecialchars($userName) ?></span>
        <a href="logout.php"
            class="h-8 px-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-500 flex items-center gap-1 text-xs font-bold hover:bg-red-500/20 transition">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
            Logout
        </a>
    </div>
</header>

<main class="w-full p-4 lg:p-8 space-y-8 pb-32 lg:pb-8 overflow-y-auto">

    <!-- Flash Success Message -->
    <?php if ($flashSuccess): ?>
        <div class="bg-green-500/10 border border-green-500/20 text-green-500 px-4 py-3 rounded-xl flex items-center gap-3"
            x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span class="font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <section class="flex flex-wrap gap-4">
        <a href="checkin.php"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 transition hover:-translate-y-0.5">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            New Check-In
        </a>
        <a href="checkout.php"
            class="app-card hover:bg-[var(--glass-border)] app-text-main font-semibold px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 transition hover:-translate-y-0.5 border border-[var(--glass-border)]">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Check-Out
        </a>
        <a href="#"
            class="app-card hover:bg-[var(--glass-border)] app-text-main font-semibold px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 transition hover:-translate-y-0.5 border border-[var(--glass-border)]">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            Rooms
        </a>
    </section>

    <!-- Stats Grid (4 Columns) -->
    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <!-- Total Rooms -->
        <div class="app-card rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="app-text-muted text-sm font-medium">Total Rooms</p>
                    <p class="text-4xl font-bold app-text-main"><?= $totalRooms ?></p>
                </div>
                <div class="bg-blue-500/10 p-3 rounded-full border border-blue-500/20">
                    <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Occupancy -->
        <div class="app-card rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="app-text-muted text-sm font-medium">Occupancy</p>
                    <p class="text-4xl font-bold app-text-main"><?= $occupancyPercent ?>%</p>
                    <p class="text-xs app-text-muted"><?= $occupiedRooms ?> / <?= $totalRooms ?> filled</p>
                </div>
                <div class="bg-emerald-500/10 p-3 rounded-full border border-emerald-500/20">
                    <svg class="h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Today's Arrivals -->
        <div class="app-card rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="app-text-muted text-sm font-medium">Today's Arrivals</p>
                    <p class="text-4xl font-bold app-text-main"><?= $todaysArrivals ?></p>
                </div>
                <div class="bg-purple-500/10 p-3 rounded-full border border-purple-500/20">
                    <svg class="h-8 w-8 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Today's Revenue -->
        <div class="app-card rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="app-text-muted text-sm font-medium">Today's Revenue</p>
                    <p class="text-4xl font-bold app-text-main">₹<?= number_format($todaysRevenue, 0) ?></p>
                </div>
                <div class="bg-yellow-500/10 p-3 rounded-full border border-yellow-500/20">
                    <svg class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
        <div class="lg:col-span-2 app-card rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-bold app-text-main mb-4">Room Matrix</h2>

            <!-- Legend -->
            <div class="flex gap-4 mb-4 text-sm app-text-muted">
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-500 rounded"></span> Avail
                    (<?= $availableRooms ?>)</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-500 rounded"></span> Occ
                    (<?= $occupiedRooms ?>)</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-yellow-500 rounded"></span> Dirty
                    (<?= $dirtyRooms ?>)</span>
            </div>

            <!-- Room Grid -->
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-3">
                <?php if (empty($rooms)): ?>
                    <p class="col-span-full app-text-muted text-center py-8">No rooms found. Add rooms from settings.</p>
                <?php else: ?>
                    <?php foreach ($rooms as $room):
                        $statusColors = [
                            'available' => 'bg-green-500/10 border-green-500/40 text-green-500',
                            'occupied' => 'bg-red-500/10 border-red-500/40 text-red-500',
                            'dirty' => 'bg-yellow-500/10 border-yellow-500/40 text-yellow-500',
                            'maintenance' => 'bg-gray-500/10 border-gray-500/40 text-gray-500',
                        ];
                        $colorClass = $statusColors[$room['status']] ?? 'bg-white border-gray-300 text-gray-800';
                        ?>
                        <div x-data="{ hover: false }" @mouseenter="hover = true" @mouseleave="hover = false"
                            class="cursor-pointer rounded-lg border p-2 text-center transition <?= $colorClass ?>"
                            :class="hover ? 'scale-110 shadow-lg relative z-10' : ''">
                            <p class="font-bold text-lg"><?= htmlspecialchars($room['room_number']) ?></p>
                            <p class="text-[10px] uppercase font-bold opacity-70"><?= substr($room['category'], 0, 3) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity (Right - 1/3 Width) -->
        <div class="app-card rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-bold app-text-main mb-4">Live Feed</h2>

            <?php if (empty($recentBookings)): ?>
                <p class="app-text-muted text-center py-8">No bookings yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentBookings as $booking):
                        $statusBadge = [
                            'active' => 'bg-blue-500/10 text-blue-500 border border-blue-500/20',
                            'completed' => 'bg-green-500/10 text-green-500 border border-green-500/20',
                            'cancelled' => 'bg-red-500/10 text-red-500 border border-red-500/20',
                            'no_show' => 'bg-gray-500/10 text-gray-500 border border-gray-500/20',
                        ];
                        $badgeClass = $statusBadge[$booking['status']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <div class="bg-[var(--surface-color)]/30 rounded-lg p-3 border border-[var(--glass-border)]">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-bold app-text-main">Room
                                        <?= htmlspecialchars($booking['room_number'] ?? 'N/A') ?>
                                    </p>
                                    <p class="text-[10px] app-text-muted uppercase font-semibold">
                                        <?= date('d M, h:i A', strtotime($booking['check_in'])) ?>
                                    </p>
                                </div>
                                <span
                                    class="text-[10px] font-bold px-2 py-1 rounded <?= $badgeClass ?>"><?= strtoupper($booking['status']) ?></span>
                            </div>
                            <p class="text-sm font-mono font-medium app-text-main mt-1 opacity-80">
                                ₹<?= number_format($booking['total_amount'], 0) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </section>

</main>

<?php
$content = ob_get_clean();
renderLayout("Dashboard", $content, true);
?>