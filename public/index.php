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
    <title>HotelOS | Antigravity Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --neon-blue: #00d4ff;
            --neon-purple: #a855f7;
            --dark-space: #0a0a0f;
            --card-glass: rgba(15, 15, 25, 0.8);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-space);
            overflow: hidden;
        }

        .font-orbitron {
            font-family: 'Orbitron', sans-serif;
        }

        /* Animated Space Background */
        .space-bg {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 80%, rgba(120, 0, 255, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(0, 212, 255, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(30, 0, 50, 0.8) 0%, var(--dark-space) 70%);
            z-index: 0;
        }

        /* Floating Orbs - Pure CSS */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.4;
            animation: float 20s ease-in-out infinite;
        }

        .orb-1 {
            width: 300px;
            height: 300px;
            background: var(--neon-purple);
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: var(--neon-blue);
            bottom: -150px;
            right: -150px;
            animation-delay: -7s;
        }

        .orb-3 {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -14s;
            opacity: 0.2;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            25% {
                transform: translate(30px, -30px) scale(1.05);
            }

            50% {
                transform: translate(-20px, 20px) scale(0.95);
            }

            75% {
                transform: translate(20px, 30px) scale(1.02);
            }
        }

        /* Stars */
        .stars {
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(2px 2px at 20px 30px, white, transparent),
                radial-gradient(2px 2px at 40px 70px, rgba(255, 255, 255, 0.8), transparent),
                radial-gradient(1px 1px at 90px 40px, white, transparent),
                radial-gradient(2px 2px at 160px 120px, rgba(255, 255, 255, 0.9), transparent),
                radial-gradient(1px 1px at 230px 80px, white, transparent),
                radial-gradient(2px 2px at 300px 150px, rgba(255, 255, 255, 0.7), transparent),
                radial-gradient(1px 1px at 350px 200px, white, transparent),
                radial-gradient(2px 2px at 420px 50px, rgba(255, 255, 255, 0.8), transparent);
            background-size: 450px 300px;
            animation: twinkle 8s ease-in-out infinite alternate;
            opacity: 0.6;
            z-index: 1;
        }

        @keyframes twinkle {
            0% {
                opacity: 0.4;
            }

            100% {
                opacity: 0.8;
            }
        }

        /* Glass Card */
        .portal-card {
            background: var(--card-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        /* Neon Glow Input */
        .neon-input {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }

        .neon-input:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3), inset 0 0 20px rgba(0, 212, 255, 0.05);
        }

        .neon-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        /* Neon Button */
        .neon-btn {
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .neon-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 40%, rgba(255, 255, 255, 0.2) 50%, transparent 60%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .neon-btn:hover::before {
            transform: translateX(100%);
        }

        .neon-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(0, 212, 255, 0.4);
        }

        .neon-btn:active {
            transform: translateY(0);
        }

        /* Logo Glow */
        .logo-glow {
            text-shadow: 0 0 30px rgba(0, 212, 255, 0.5);
        }

        /* Pulse Ring */
        .pulse-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 2px solid var(--neon-blue);
            border-radius: 50%;
            animation: pulse 2s ease-out infinite;
            opacity: 0;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.8);
                opacity: 0.8;
            }

            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        /* Autofill Fix */
        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px rgba(10, 10, 15, 1) inset !important;
            -webkit-text-fill-color: white !important;
            caret-color: white;
        }

        /* Label Style */
        .input-label {
            font-size: 0.7rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 500;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'neon-blue': '#00d4ff',
                        'neon-purple': '#a855f7',
                        'dark-space': '#0a0a0f',
                    }
                }
            }
        }
    </script>
</head>

<body class="h-screen w-full flex items-center justify-center">

    <!-- Animated Background -->
    <div class="space-bg"></div>
    <div class="stars"></div>

    <!-- Floating Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Login Card -->
    <div class="portal-card z-10 w-full max-w-md p-8 rounded-3xl mx-4 relative" x-data="{ 
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
                     console.log(data);
                     if(data.status === 'success') {
                         window.location.href = data.redirect || 'dashboard.php';
                     } else {
                         alert('Access Denied: ' + data.message);
                     }
                 } catch(e) {
                     console.error(e);
                     alert('Connection to Command Center failed');
                 } finally {
                     this.loading = false;
                 }
             }
         }">

        <!-- Logo Section -->
        <div class="text-center mb-10 relative">
            <!-- Pulse Effect Behind Logo -->
            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20">
                <div class="pulse-ring" style="animation-delay: 0s;"></div>
                <div class="pulse-ring" style="animation-delay: 0.5s;"></div>
                <div class="pulse-ring" style="animation-delay: 1s;"></div>
            </div>

            <!-- Logo Icon -->
            <div class="relative inline-block mb-4">
                <div
                    class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-neon-blue to-neon-purple flex items-center justify-center shadow-lg shadow-neon-blue/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>

            <h1 class="font-orbitron text-3xl font-bold text-white tracking-wider logo-glow mb-2">
                HOTELOS
            </h1>
            <p class="text-xs text-neon-blue/70 tracking-[0.3em] uppercase">Antigravity Command Center</p>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="submitLogin" class="space-y-6">

            <!-- Hotel Code -->
            <div>
                <label class="input-label block mb-2">Property Code</label>
                <input type="text" x-model="formData.hotel_code" required
                    class="neon-input w-full px-4 py-3.5 rounded-xl" placeholder="GRAND_HYATT">
            </div>

            <!-- Email -->
            <div>
                <label class="input-label block mb-2">Access Email</label>
                <input type="email" x-model="formData.email" required class="neon-input w-full px-4 py-3.5 rounded-xl"
                    placeholder="admin@property.com">
            </div>

            <!-- Password -->
            <div>
                <label class="input-label block mb-2">Security Key</label>
                <input type="password" x-model="formData.password" required
                    class="neon-input w-full px-4 py-3.5 rounded-xl" placeholder="••••••••••••">
            </div>

            <!-- Submit Button -->
            <button type="submit" :disabled="loading"
                class="neon-btn w-full text-white font-semibold py-4 px-4 rounded-xl shadow-lg flex justify-center items-center gap-3 mt-8">
                <span x-show="!loading" class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    Enter Command Center
                </span>
                <span x-show="loading" class="flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Establishing Link...
                </span>
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-xs text-white/30">
                Powered by <span class="text-neon-blue/60 font-medium">Antigravity AI</span>
            </p>
        </div>
    </div>

    <!-- Version Badge -->
    <div class="fixed bottom-4 right-4 z-10 text-xs text-white/20">
        v2.0 Enterprise
    </div>

</body>

</html>