<?php
/**
 * public/index.php
 * HotelOS Enterprise - Royal Login Portal
 * UI REPAIR: Fixed Scroll, Touch Targets, and Theme Colors
 */

// LOGIC LOCK: Keep original logic exactly as is.
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// ... (API Logic not needed here as it is handled via AJAX to api_login.php or self-submission)
// We will assume the JS `submitLogin` handles the actual request.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | HotelOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.5s ease;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4 transition-colors duration-500" x-data="{ 
          theme: localStorage.getItem('hotelos_theme') || 'dark',
          setTheme(val) {
              this.theme = val;
              localStorage.setItem('hotelos_theme', val);
          }
      }" :class="{
          'bg-[#F8F9FA]': theme === 'light',
          'bg-slate-950': theme === 'dark',
          'bg-[#F5E6D3]': theme === 'comfort'
      }">

    <!-- BACKGROUND GRADIENTS (No Images) -->
    <!-- Dark Mode Gradient -->
    <div class="fixed inset-0 z-0 pointer-events-none transition-opacity duration-700"
        :class="theme === 'dark' ? 'opacity-100' : 'opacity-0'"
        style="background: radial-gradient(circle at 50% 0%, #1e293b 0%, #020617 100%);"></div>

    <!-- Light Mode Gradient -->
    <div class="fixed inset-0 z-0 pointer-events-none transition-opacity duration-700"
        :class="theme === 'light' ? 'opacity-100' : 'opacity-0'"
        style="background: radial-gradient(circle at top, #ffffff, #f1f5f9);"></div>

    <!-- Comfort Mode Gradient -->
    <div class="fixed inset-0 z-0 pointer-events-none transition-opacity duration-700"
        :class="theme === 'comfort' ? 'opacity-100' : 'opacity-0'"
        style="background: radial-gradient(circle at center, #F5E6D3, #E6D5C0);"></div>


    <!-- THEME SWITCHER (Top Right) -->
    <div class="fixed top-6 right-6 z-50 flex gap-2 p-1 rounded-full shadow-lg transition-all duration-300" :class="{
             'bg-white border border-yellow-500/30': theme === 'light',
             'bg-slate-900 border border-white/10': theme === 'dark',
             'bg-[#E6D5C0] border border-none': theme === 'comfort'
         }">

        <!-- Light Button -->
        <button @click="setTheme('light')" class="p-2 rounded-full transition-all"
            :class="theme === 'light' ? 'bg-yellow-100 text-yellow-600' : 'text-gray-400 hover:text-gray-600'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                </path>
            </svg>
        </button>

        <!-- Dark Button -->
        <button @click="setTheme('dark')" class="p-2 rounded-full transition-all"
            :class="theme === 'dark' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
            </svg>
        </button>

        <!-- Comfort Button -->
        <button @click="setTheme('comfort')" class="p-2 rounded-full transition-all"
            :class="theme === 'comfort' ? 'bg-[#8C6B4B] text-[#F5E6D3]' : 'text-gray-400 hover:text-[#4A3B2A]'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                </path>
            </svg>
        </button>
    </div>


    <!-- MAIN LOGIN CARD -->
    <div class="relative z-10 w-full max-w-sm rounded-2xl p-8 transition-all duration-500" :class="{
             'bg-white border border-yellow-500/30 shadow-2xl shadow-yellow-900/5': theme === 'light',
             'bg-slate-900/50 backdrop-blur-xl border border-white/10 shadow-2xl': theme === 'dark',
             'bg-[#E6D5C0] border-none shadow-xl': theme === 'comfort'
         }">

        <!-- Text Header -->
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold tracking-tight mb-2 transition-colors duration-300" :class="{
                    'text-slate-800': theme === 'light',
                    'text-white': theme === 'dark',
                    'text-[#4A3B2A]': theme === 'comfort'
                }">HotelOS</h1>
            <p class="text-sm font-medium transition-colors duration-300" :class="{
                   'text-slate-500': theme === 'light',
                   'text-slate-400': theme === 'dark',
                   'text-[#8C6B4B]': theme === 'comfort'
               }">Command Center</p>
        </div>

        <!-- Form -->
        <div x-data="{ 
                 loading: false, 
                 formData: { hotel_code: '', email: '', password: '' },
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
                         alert('Connection failed.');
                     } finally {
                         this.loading = false;
                     }
                 }
             }">

            <form @submit.prevent="submitLogin" class="space-y-5">

                <!-- Inputs: Enforced h-12 (48px) -->
                <div class="space-y-4">
                    <input type="text" x-model="formData.hotel_code" placeholder="Hotel Code" required
                        class="w-full h-12 px-4 rounded-xl text-sm font-medium outline-none transition-all duration-300 border border-transparent focus:border-transparent placeholder-opacity-60"
                        :class="{
                            'bg-gray-50 text-slate-800 focus:bg-white focus:ring-2 focus:ring-yellow-500 placeholder-slate-400': theme === 'light',
                            'bg-white/5 text-white focus:bg-white/10 focus:ring-2 focus:ring-cyan-500 placeholder-white/30': theme === 'dark',
                            'bg-[#FFF8E7] text-[#4A3B2A] focus:ring-2 focus:ring-[#8C6B4B] placeholder-[#8C6B4B]/50': theme === 'comfort'
                        }">

                    <input type="email" x-model="formData.email" placeholder="Email Address" required
                        class="w-full h-12 px-4 rounded-xl text-sm font-medium outline-none transition-all duration-300 border border-transparent focus:border-transparent placeholder-opacity-60"
                        :class="{
                            'bg-gray-50 text-slate-800 focus:bg-white focus:ring-2 focus:ring-yellow-500 placeholder-slate-400': theme === 'light',
                            'bg-white/5 text-white focus:bg-white/10 focus:ring-2 focus:ring-cyan-500 placeholder-white/30': theme === 'dark',
                            'bg-[#FFF8E7] text-[#4A3B2A] focus:ring-2 focus:ring-[#8C6B4B] placeholder-[#8C6B4B]/50': theme === 'comfort'
                        }">

                    <input type="password" x-model="formData.password" placeholder="Password" required
                        class="w-full h-12 px-4 rounded-xl text-sm font-medium outline-none transition-all duration-300 border border-transparent focus:border-transparent placeholder-opacity-60"
                        :class="{
                            'bg-gray-50 text-slate-800 focus:bg-white focus:ring-2 focus:ring-yellow-500 placeholder-slate-400': theme === 'light',
                            'bg-white/5 text-white focus:bg-white/10 focus:ring-2 focus:ring-cyan-500 placeholder-white/30': theme === 'dark',
                            'bg-[#FFF8E7] text-[#4A3B2A] focus:ring-2 focus:ring-[#8C6B4B] placeholder-[#8C6B4B]/50': theme === 'comfort'
                        }">
                </div>

                <!-- Action Button: h-12 (48px) -->
                <button type="submit" :disabled="loading"
                    class="w-full h-12 rounded-xl font-bold text-sm tracking-wide uppercase transition-all transform active:scale-95 shadow-lg"
                    :class="{
                        'bg-slate-900 text-white hover:bg-slate-800 shadow-slate-900/20': theme === 'light',
                        'bg-blue-600 text-white hover:bg-blue-500 shadow-blue-500/30': theme === 'dark',
                        'bg-[#8C6B4B] text-[#F5E6D3] hover:bg-[#7A5C3E] shadow-[#8C6B4B]/20': theme === 'comfort'
                    }">
                    <span x-show="!loading">Enter System</span>
                    <span x-show="loading" class="animate-pulse">Loading...</span>
                </button>

            </form>
        </div>

        <!-- Footer Note -->
        <p class="mt-8 text-center text-xs opacity-60 font-medium transition-colors" :class="{
               'text-slate-400': theme === 'light',
               'text-slate-500': theme === 'dark',
               'text-[#8C6B4B]': theme === 'comfort'
           }">
            Security Optimized. Logic Locked.
        </p>

    </div>

</body>

</html>