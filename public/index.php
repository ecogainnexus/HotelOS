<?php
/**
 * public/index.php
 * HotelOS Enterprise - Antigravity Portal
 * The Gateway to Command Center
 */

// LOGIC LOCK: Keep original logic exactly as is.
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// ... (No PHP logic changes needed for UI refactor, handled by api_login.php request)

require_once 'layout.php';

ob_start();
?>

<!-- SPLIT LAYOUT CONTAINER -->
<div class="flex h-full w-full">

    <!-- LEFT SIDE: Hotel Art / Branding (Desktop Only) -->
    <div class="hidden lg:flex w-[45%] flex-col justify-between p-12 relative overflow-hidden">
        <!-- Background Art -->
        <div
            class="absolute inset-0 z-0 bg-gradient-to-br from-blue-900/20 via-transparent to-purple-900/20 bg-cover bg-center">
        </div>
        <div class="absolute inset-0 z-0 opacity-30"
            style="background-image: url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?q=80&w=2070&auto=format&fit=crop'); background-size: cover; background-position: center; filter: grayscale(100%) contrast(120%);">
        </div>

        <!-- Overlay Gradient -->
        <div class="absolute inset-0 z-0 bg-gradient-to-t from-[var(--bg-main)] via-[var(--bg-main)]/50 to-transparent">
        </div>

        <!-- Content -->
        <div class="relative z-10">
            <div
                class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-xl shadow-blue-500/20 mb-6">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                    </path>
                </svg>
            </div>
            <h1 class="text-4xl font-bold app-text-main tracking-tight leading-tight">
                The Operating System<br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-400">For Modern
                    Hotels</span>
            </h1>
        </div>

        <div class="relative z-10">
            <div class="flex items-center gap-4 mb-8">
                <div class="flex -space-x-3">
                    <img class="w-10 h-10 rounded-full border-2 border-[var(--bg-main)]"
                        src="https://ui-avatars.com/api/?name=J&background=0D8ABC&color=fff" alt="">
                    <img class="w-10 h-10 rounded-full border-2 border-[var(--bg-main)]"
                        src="https://ui-avatars.com/api/?name=A&background=6b21a8&color=fff" alt="">
                    <div
                        class="w-10 h-10 rounded-full border-2 border-[var(--bg-main)] bg-gray-700 flex items-center justify-center text-xs text-white font-bold">
                        +2k</div>
                </div>
                <div>
                    <p class="text-sm font-semibold app-text-main">Trusted by 2,000+ Hoteliers</p>
                    <p class="text-xs app-text-muted">Powering operations across India</p>
                </div>
            </div>
            <p class="text-xs app-text-muted opacity-60">© 2025 HotelOS Inc. All logic secured.</p>
        </div>
    </div>

    <!-- RIGHT SIDE: Login Form -->
    <div class="w-full lg:w-[55%] h-full flex items-center justify-center p-6 relative">

        <div class="absolute inset-0 app-surface opacity-90 lg:opacity-0 pointer-events-none transition-opacity"></div>

        <div class="w-full max-w-md relative z-10" x-data="{ 
                 loading: false, 
                 focused: '',
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

            <!-- Mobile Only Header -->
            <div class="lg:hidden text-center mb-10">
                <div
                    class="w-14 h-14 mx-auto rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-xl shadow-blue-500/20 mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                        </path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold app-text-main">Welcome Back</h2>
                <p class="text-sm app-text-muted">Enter Command Center</p>
            </div>

            <!-- Glass Card Container -->
            <div class="app-card rounded-2xl p-8 lg:p-10 shadow-2xl transition-all duration-300 transform"
                :class="focused ? 'scale-[1.01]' : ''">

                <h2 class="text-2xl font-bold app-text-main mb-1 hidden lg:block">System Access</h2>
                <p class="text-sm app-text-muted mb-8 hidden lg:block">Authenticate to manage your property.</p>

                <form @submit.prevent="submitLogin" class="space-y-6">

                    <!-- Floating Label Input: Hotel Code -->
                    <div class="relative">
                        <input type="text" x-model="formData.hotel_code" required @focus="focused = 'hotel_code'"
                            @blur="focused = ''"
                            class="peer w-full h-12 app-input rounded-lg px-4 pt-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all placeholder-transparent"
                            placeholder="Property Code">
                        <label
                            class="absolute left-4 top-1 text-[10px] uppercase font-bold text-gray-400 transition-all 
                                      peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-500 peer-placeholder-shown:font-normal peer-placeholder-shown:capitalize
                                      peer-focus:top-1 peer-focus:text-[10px] peer-focus:text-blue-500 peer-focus:font-bold peer-focus:uppercase">
                            Property Code
                        </label>
                    </div>

                    <!-- Floating Label Input: Email -->
                    <div class="relative">
                        <input type="email" x-model="formData.email" required @focus="focused = 'email'"
                            @blur="focused = ''"
                            class="peer w-full h-12 app-input rounded-lg px-4 pt-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all placeholder-transparent"
                            placeholder="Access Email">
                        <label
                            class="absolute left-4 top-1 text-[10px] uppercase font-bold text-gray-400 transition-all 
                                      peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-500 peer-placeholder-shown:font-normal peer-placeholder-shown:capitalize
                                      peer-focus:top-1 peer-focus:text-[10px] peer-focus:text-blue-500 peer-focus:font-bold peer-focus:uppercase">
                            Access Email
                        </label>
                    </div>

                    <!-- Floating Label Input: Password -->
                    <div class="relative">
                        <input type="password" x-model="formData.password" required @focus="focused = 'password'"
                            @blur="focused = ''"
                            class="peer w-full h-12 app-input rounded-lg px-4 pt-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all placeholder-transparent"
                            placeholder="Security Key">
                        <label
                            class="absolute left-4 top-1 text-[10px] uppercase font-bold text-gray-400 transition-all 
                                      peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-500 peer-placeholder-shown:font-normal peer-placeholder-shown:capitalize
                                      peer-focus:top-1 peer-focus:text-[10px] peer-focus:text-blue-500 peer-focus:font-bold peer-focus:uppercase">
                            Security Key
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between text-xs">
                        <label
                            class="flex items-center gap-2 cursor-pointer app-text-muted hover:text-blue-500 transition">
                            <input type="checkbox"
                                class="w-4 h-4 rounded border-gray-600 text-blue-600 focus:ring-offset-0 focus:ring-0">
                            Remember Access
                        </label>
                        <a href="#" class="text-blue-500 hover:text-blue-400 font-medium">Reset Key?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" :disabled="loading"
                        class="w-full h-12 app-btn font-bold rounded-lg transition-all shadow-lg flex items-center justify-center disabled:opacity-70 disabled:cursor-not-allowed hover:-translate-y-0.5 mt-2">
                        <span x-show="!loading" class="tracking-wide uppercase text-sm">Initialize Session</span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Authenticating...
                        </span>
                    </button>
                </form>

            </div>

            <p class="text-[10px] text-center mt-8 app-text-muted opacity-50">
                Authorized Personnel Only. Connection Monitored.
            </p>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
renderLayout("Command Center", $content);
?>