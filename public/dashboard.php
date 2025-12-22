<?php
session_start();
// Simple Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard | HotelOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col">
            <div class="p-6">
                <h1 class="text-2xl font-bold text-blue-600">HotelOS</h1>
                <p class="text-xs text-gray-500 uppercase tracking-wider mt-1">
                    <?php echo htmlspecialchars($_SESSION['hotel_name'] ?? 'Enterprise'); ?></p>
            </div>

            <nav class="flex-1 px-4 space-y-1">
                <a href="#"
                    class="flex items-center px-4 py-3 bg-blue-50 text-blue-700 rounded-xl font-medium">Dashboard</a>
                <a href="#"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl font-medium">Bookings</a>
                <a href="#"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl font-medium">Guests</a>
                <a href="#"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl font-medium">Rooms</a>
                <a href="#"
                    class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl font-medium">Finance</a>
            </nav>

            <div class="p-4 border-t border-gray-200">
                <a href="logout.php"
                    class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg text-sm font-medium">Sign
                    Out</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-gray-50 p-8">

            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Overview</h2>
                    <p class="text-gray-500">Welcome back,
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow-sm hover:bg-blue-700 transition">+
                        New Booking</button>
                </div>
            </header>

            <!-- KPI Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Card 1 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-gray-500 text-sm font-medium uppercase">Today's Occupancy</h3>
                    <div class="mt-2 flex items-baseline">
                        <span class="text-4xl font-bold text-gray-900">85%</span>
                        <span class="ml-2 text-sm text-green-500 font-medium">↑ 12%</span>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-gray-500 text-sm font-medium uppercase">Revenue (Today)</h3>
                    <div class="mt-2 flex items-baseline">
                        <span class="text-4xl font-bold text-gray-900">₹42,500</span>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-gray-500 text-sm font-medium uppercase">Check-Ins Pending</h3>
                    <div class="mt-2 flex items-baseline">
                        <span class="text-4xl font-bold text-gray-900">4</span>
                        <span class="ml-2 text-sm text-gray-400">Guests arriving</span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Placeholder -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Recent Bookings</h3>
                </div>
                <div class="p-6 text-center text-gray-500 py-12">
                    No recent bookings found via API.
                </div>
            </div>

        </main>
    </div>

</body>

</html>