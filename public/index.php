<?php
/**
 * public/index.php
 * HotelOS Enterprise - Antigravity Portal
 * The Gateway to Command Center
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HotelOS | Enterprise Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Functional Glassmorphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            /* Very subtle */
            backdrop-filter: blur(12px);
            /* Medium blur */
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Autofill Transition Override */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-transition: background-color 5000s ease-in-out 0s;
            transition: background-color 5000s ease-in-out 0s;
            -webkit-text-fill-color: white !important;
            color: white !important;
        }
    </style>
</head>

<body
    class="bg-slate-900 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-800 via-slate-900 to-black h-screen w-full flex items-center justify-center p-4">

    <!-- Login Container -->
    <div class="glass-card shadow-2xl w-full max-w-sm rounded-2xl p-8 relative overflow-hidden" x-data="{ 
             loading: false, 
             formData: {
                 hotel_code: '',
                 email: '',
                 password: ''
             },
             async submitLogin() {
                 this.loading = true;
                 
                 try {
                     const response = await fetch('api_login.php', {
                         method: 'POST',
                         headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                         body: new URLSearchParams(this.formData)
                     });
                     const data = await response.json();
                     
                     if(data.status === 'success') {
                         window.location.href = data.redirect || 'dashboard.php';
                     } else {
                         alert('Access Denied: ' + data.message);
                     }
                 } catch(e) {
                     alert('Connection failed. Please check your internet.');
                 } finally {
                     this.loading = false;
                 }
             }
         }">

        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white tracking-tight">HotelOS</h1>
            <p class="text-sm text-slate-400 font-medium mt-1">Enterprise Command Center</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submitLogin" class="space-y-5">

            <!-- Hotel Code -->
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Property
                    Code</label>
                <input type="text" x-model="formData.hotel_code" required
                    class="w-full h-11 bg-slate-950/50 border border-slate-700 rounded-lg px-4 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    placeholder="e.g. GRAND_HYATT">
            </div>

            <!-- Email -->
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Access
                    Email</label>
                <input type="email" x-model="formData.email" required
                    class="w-full h-11 bg-slate-950/50 border border-slate-700 rounded-lg px-4 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    placeholder="admin@hotelos.in">
            </div>

            <!-- Password -->
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Security
                    Key</label>
                <input type="password" x-model="formData.password" required
                    class="w-full h-11 bg-slate-950/50 border border-slate-700 rounded-lg px-4 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    placeholder="••••••••••••">
            </div>

            <!-- Submit Button -->
            <button type="submit" :disabled="loading"
                class="w-full h-11 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-all shadow-lg flex items-center justify-center disabled:opacity-70 disabled:cursor-not-allowed mt-2">
                <span x-show="!loading">Secure Login</span>
                <span x-show="loading" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Verifying...
                </span>
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-8 text-center border-t border-white/5 pt-4">
            <p class="text-xs text-slate-500">
                Protected by <span class="text-slate-400 font-medium">Antigravity Shield</span>
            </p>
        </div>

    </div>

</body>

</html>