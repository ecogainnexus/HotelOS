<?php
/**
 * public/seed_demo_data.php
 * ONE-TIME USE: Seeds the database with demo rooms and bookings.
 * DELETE THIS FILE AFTER USE IN PRODUCTION!
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db_connect.php';

$tenantId = 1; // Demo tenant

echo "<h1>🌱 HotelOS Demo Data Seeder</h1>";

try {
    // Seed a Demo Guest (FIXED: using 'mobile' instead of 'phone')
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $existingGuests = (int) $stmt->fetchColumn();

    if ($existingGuests == 0) {
        $stmt = $pdo->prepare("INSERT INTO guests (tenant_id, full_name, mobile, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tenantId, 'Rahul Sharma', '9876543210', 'rahul@demo.com']);
        echo "<p>✅ Seeded 1 demo guest!</p>";
        $guestId = $pdo->lastInsertId();
    } else {
        echo "<p>⚠️ Guest already exists. Using existing guest.</p>";
        $stmt = $pdo->prepare("SELECT id FROM guests WHERE tenant_id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $guestId = (int) $stmt->fetchColumn();
    }

    // Seed Demo Bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $existingBookings = (int) $stmt->fetchColumn();

    if ($existingBookings > 0) {
        echo "<p>⚠️ Bookings already exist ($existingBookings bookings). Skipping.</p>";
    } else {
        // Get room IDs for occupied rooms
        $stmt = $pdo->prepare("SELECT id FROM rooms WHERE tenant_id = ? AND status = 'occupied' LIMIT 4");
        $stmt->execute([$tenantId]);
        $occupiedRoomIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($occupiedRoomIds)) {
            $today = date('Y-m-d H:i:s');
            $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));

            $bookingStmt = $pdo->prepare("INSERT INTO bookings (tenant_id, guest_id, room_id, unique_booking_id, check_in, check_out, status, total_amount, paid_amount, booking_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $i = 1;
            foreach ($occupiedRoomIds as $roomId) {
                $bookingId = 'BK' . date('Ymd') . str_pad($i, 3, '0', STR_PAD_LEFT);
                $amount = rand(1500, 5000);
                $paid = rand(0, 1) ? $amount : $amount / 2;
                $bookingStmt->execute([
                    $tenantId,
                    $guestId,
                    $roomId,
                    $bookingId,
                    $today,
                    $tomorrow,
                    'active',
                    $amount,
                    $paid,
                    'walk-in'
                ]);
                $i++;
            }

            echo "<p>✅ Seeded " . count($occupiedRoomIds) . " demo bookings!</p>";
        } else {
            echo "<p>⚠️ No occupied rooms found to create bookings.</p>";
        }
    }

    echo "<hr>";
    echo "<h2>🎉 Seeding Complete!</h2>";
    echo "<p>Dashboard data is now ready. <a href='dashboard.php' style='color:blue; font-weight:bold;'>Go to Dashboard →</a></p>";
    echo "<p style='color:red;'><strong>⚠️ DELETE THIS FILE (seed_demo_data.php) IN PRODUCTION!</strong></p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>