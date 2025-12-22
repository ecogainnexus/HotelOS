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
    // Check if rooms already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $existingRooms = (int) $stmt->fetchColumn();

    if ($existingRooms > 0) {
        echo "<p>⚠️ Rooms already exist ($existingRooms rooms). Skipping room seeding.</p>";
    } else {
        // Seed 15 Rooms
        $rooms = [
            ['101', '1', 'standard', 'available', 1500],
            ['102', '1', 'standard', 'occupied', 1500],
            ['103', '1', 'standard', 'dirty', 1500],
            ['104', '1', 'deluxe', 'available', 2500],
            ['105', '1', 'deluxe', 'occupied', 2500],
            ['201', '2', 'deluxe', 'available', 2500],
            ['202', '2', 'suite', 'occupied', 4500],
            ['203', '2', 'suite', 'available', 4500],
            ['204', '2', 'standard', 'maintenance', 1500],
            ['205', '2', 'standard', 'available', 1500],
            ['301', '3', 'suite', 'available', 4500],
            ['302', '3', 'deluxe', 'occupied', 2500],
            ['303', '3', 'standard', 'available', 1500],
            ['304', '3', 'standard', 'dirty', 1500],
            ['305', '3', 'dormitory', 'available', 800],
        ];

        $stmt = $pdo->prepare("INSERT INTO rooms (tenant_id, room_number, floor_number, category, status, base_price) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($rooms as $room) {
            $stmt->execute([$tenantId, $room[0], $room[1], $room[2], $room[3], $room[4]]);
        }

        echo "<p>✅ Seeded " . count($rooms) . " rooms successfully!</p>";
    }

    // Check for guests table
    try {
        $pdo->query("SELECT 1 FROM guests LIMIT 1");
        $guestsTableExists = true;
    } catch (Exception $e) {
        $guestsTableExists = false;
        echo "<p>⚠️ Guests table not found. Skipping guest seeding.</p>";
    }

    // Seed a Demo Guest (if table exists)
    if ($guestsTableExists) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existingGuests = (int) $stmt->fetchColumn();

        if ($existingGuests == 0) {
            $stmt = $pdo->prepare("INSERT INTO guests (tenant_id, full_name, phone, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tenantId, 'Rahul Sharma', '9876543210', 'rahul@demo.com']);
            echo "<p>✅ Seeded 1 demo guest!</p>";
        }
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
                    1, // Demo guest ID
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
        }
    }

    echo "<hr>";
    echo "<h2>🎉 Seeding Complete!</h2>";
    echo "<p>Dashboard data is now ready. <a href='dashboard.php' style='color:blue;'>Go to Dashboard →</a></p>";
    echo "<p style='color:red;'><strong>⚠️ DELETE THIS FILE (seed_demo_data.php) IN PRODUCTION!</strong></p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>