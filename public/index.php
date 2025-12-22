<?php
/**
 * public/index.php
 * 
 * The entry point for the frontend.
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
            background-color: #0f172a;
        }

        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px #0f172a inset !important;
            -webkit-text-fill-color: white !important;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { brand: { 500: '#3b82f6', 600: '#2563eb' } } }
            }
        }
    </script>
</head>

<body class="h-screen w-full flex items-center justify-center overflow-hidden relative">

    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80"
            class="w-full h-full object-cover opacity-60" alt="Luxury Hotel Lobby">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900/80 via-gray-900/60 to-gray-900/40"></div>
    </div>

    <div class="glass z-10 w-full max-w-md p-8 rounded-2xl mx-4 transform transition-all duration-300 hover:scale-[1.01]"
        x-data="{ 
             loading: false, 
             formData: {
                 hotel_code: '',
                 email: '',
                 password: ''
             },
             async submitLogin() {
                 this.loading = true;
                 
                 // Using Fetch API for smooth experience
                 try {
                     const response = await fetch('../auth/login_logic.php', {
                         method: 'POST',
                         headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                         body: new URLSearchParams(this.formData)
                     });
                     const data = await response.json();
                     console.log(data);
                     if(data.status === 'success') {
                         alert('Login logic connected! (Placeholder)');
                     } else {
                         alert('Error: ' + data.message);
                     }
                 } catch(e) {
                     console.error(e);
                     alert('Connection failed');
                 } finally {
                     this.loading = false;
                 }
             }
         }">

        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white tracking-tight mb-2">HotelOS</h1>
            <p class="text-blue-200 text-sm font-light">Enterprise Property Management</p>
        </div>

        <form @submit.prevent="submitLogin" class="space-y-6">

            <div>
                <label class="block text-xs font-medium text-blue-200 uppercase tracking-widest mb-2">Hotel Code</label>
                <input type="text" x-model="formData.hotel_code" required
                    class="glass w-full px-4 py-3 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
                    placeholder="e.g. GRAND_HYATT">
            </div>

            <div>
                <label class="block text-xs font-medium text-blue-200 uppercase tracking-widest mb-2">Email</label>
                <input type="email" x-model="formData.email" required
                    class="glass w-full px-4 py-3 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
                    placeholder="admin@hotelos.in">
            </div>

            <div>
                <label class="block text-xs font-medium text-blue-200 uppercase tracking-widest mb-2">Password</label>
                <input type="password" x-model="formData.password" required
                    class="glass w-full px-4 py-3 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
                    placeholder="••••••••">
            </div>

            <button type="submit" :disabled="loading"
                class="w-full bg-brand-600 hover:bg-brand-500 text-white font-semibold py-3 px-4 rounded-xl shadow-lg transform transition hover:-translate-y-0.5 focus:ring-2 focus:ring-brand-500 flex justify-center items-center">
                <span x-show="!loading">Sign In</span>
                <span x-show="loading">Processing...</span>
            </button>
        </form>
    </div>

    <div class="absolute bottom-4 w-full text-center z-10">
        <p class="text-xs text-gray-500">Powered by <span class="text-brand-500 font-semibold">Antigravity AI</span></p>
    </div>
</body>

</html>