<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HotelOS | Enterprise Login</title>

    <!-- Tailwind CSS (via CDN for Shared Hosting speed) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js (for interactivity without Node.js) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Google Fonts: Inter (The "Apple" Standard) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            /* Fallback dark blue */
        }

        /* Glassmorphism Classes */
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        /* Input Autofill Fix for Dark Theme */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-background-clip: text;
            -webkit-text-fill-color: #ffffff;
            transition: background-color 5000s ease-in-out 0s;
            box-shadow: inset 0 0 20px 20px rgba(255, 255, 255, 0);
        }
    </style>

    <!-- Tailwind Custom Config for Brand Colors -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#3b82f6', // Apple Blue
                            600: '#2563eb',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="h-screen w-full flex items-center justify-center overflow-hidden relative">

    <!-- High-res Background Image -->
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80"
            class="w-full h-full object-cover opacity-60" alt="Luxury Hotel Lobby">
        <!-- Dark Overlay Gradient -->
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900/80 via-gray-900/60 to-gray-900/40"></div>
    </div>

    <!-- Login Card (Glassmorphism) -->
    <div class="glass z-10 w-full max-w-md p-8 rounded-2xl mx-4 transform transition-all duration-300 hover:scale-[1.01]"
        x-data="{ 
             loading: false, 
             submitLogin() {
                 this.loading = true;
                 // Simulate request (The actual POST happens via form action)
                 document.getElementById('loginForm').submit();
             }
         }">

        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white tracking-tight mb-2">HotelOS</h1>
            <p class="text-blue-200 text-sm font-light">Enterprise Property Management</p>
        </div>

        <!-- Login Form -->
        <form id="loginForm" action="auth_login.php" method="POST" class="space-y-6">

            <!-- Hotel Code (Subdomain/Tenant ID) -->
            <div>
                <label for="hotel_code"
                    class="block text-xs font-medium text-blue-200 uppercase tracking-widest mb-2">Hotel Code</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <!-- Building Icon -->
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <input type="text" name="hotel_code" id="hotel_code" required
                        class="glass w-full pl-10 pr-4 py-3 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
                        placeholder="e.g. GRAND_HYATT">
                </div>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-xs font-medium text-blue-200 uppercase tracking-widest mb-2">Email
                    Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <!-- Mail Icon -->
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                    </div>
                    <input type="email" name="email" id="email" required
                        class="glass w-full pl-10 pr-4 py-3 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
                        placeholder="admin@hotelos.in">
                </div>
            </div>

            <!-- Password -->
            <div>
                <label for="password"
                    class="block text-xs font-medium text-blue-200 uppercase tracking-widest mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <!-- Lock Icon -->
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" name="password" id="password" required
                        class="glass w-full pl-10 pr-4 py-3 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
                        placeholder="••••••••">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" @click.prevent="submitLogin()" :disabled="loading"
                class="w-full bg-brand-600 hover:bg-brand-500 text-white font-semibold py-3 px-4 rounded-xl shadow-lg transform transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 focus:ring-brand-500 flex justify-center items-center">

                <span x-show="!loading">Sign In</span>

                <!-- Loading Spinner -->
                <svg x-show="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="display: none;">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
            </button>

            <div class="flex items-center justify-between mt-4">
                <a href="#" class="text-xs text-blue-300 hover:text-white transition-colors">Forgot Password?</a>
                <a href="#" class="text-xs text-blue-300 hover:text-white transition-colors">Contact Support</a>
            </div>
        </form>
    </div>

    <!-- Footer Credit -->
    <div class="absolute bottom-4 w-full text-center z-10">
        <p class="text-xs text-gray-500">Powered by <span class="text-brand-500 font-semibold">Antigravity AI</span></p>
    </div>

</body>

</html>