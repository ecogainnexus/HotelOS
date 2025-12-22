<?php
/**
 * public/layout.php
 * HotelOS Enterprise - Global Layout Shell
 * Contains: Theme Engine, Global Styles, Alpine.js Setup
 */

function renderLayout($title, $content)
{
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> | HotelOS</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Inter', sans-serif;
                transition: background-color 0.3s ease, color 0.3s ease;
            }

            /* THEME: DARK (Default - Antigravity) */
            .theme-dark {
                --bg-main: #0f172a;
                /* Slate 900 */
                --bg-grad-start: #1e293b;
                --bg-grad-end: #020617;
                --glass-bg: rgba(255, 255, 255, 0.05);
                --glass-border: rgba(255, 255, 255, 0.1);
                --text-primary: #f8fafc;
                --text-secondary: #94a3b8;
                --input-bg: rgba(2, 6, 23, 0.5);
                --input-border: #334155;
                --btn-primary: #2563eb;
                --btn-hover: #1d4ed8;
                --surface-color: #1e293b;
            }

            /* THEME: LIGHT (Royal Luxury) */
            .theme-light {
                --bg-main: #f8fafc;
                /* Slate 50 */
                --bg-grad-start: #f1f5f9;
                --bg-grad-end: #e2e8f0;
                --glass-bg: rgba(255, 255, 255, 0.8);
                --glass-border: rgba(203, 213, 225, 0.6);
                --text-primary: #0f172a;
                --text-secondary: #64748b;
                --input-bg: #ffffff;
                --input-border: #cbd5e1;
                --btn-primary: #0f172a;
                /* Solid Navy */
                --btn-hover: #334155;
                --surface-color: #ffffff;
            }

            /* THEME: COMFORT (Eye Care) */
            .theme-comfort {
                --bg-main: #fef3c7;
                /* Amber 100 */
                --bg-grad-start: #fffbeb;
                --bg-grad-end: #fde68a;
                --glass-bg: rgba(255, 251, 235, 0.8);
                --glass-border: rgba(217, 119, 6, 0.2);
                --text-primary: #451a03;
                /* Amber 950 */
                --text-secondary: #92400e;
                --input-bg: #fffbeb;
                --input-border: #d97706;
                --btn-primary: #78350f;
                /* Amber 900 */
                --btn-hover: #92400e;
                --surface-color: #fff8e1;
            }

            /* Dynamic Utilities */
            .app-bg {
                background-color: var(--bg-main);
                background-image: radial-gradient(ellipse at top, var(--bg-grad-start), var(--bg-grad-end));
            }

            .app-card {
                background: var(--glass-bg);
                border: 1px solid var(--glass-border);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
            }

            .app-text-main {
                color: var(--text-primary);
            }

            .app-text-muted {
                color: var(--text-secondary);
            }

            .app-input {
                background-color: var(--input-bg);
                border: 1px solid var(--input-border);
                color: var(--text-primary);
            }

            .app-btn {
                background-color: var(--btn-primary);
                color: white;
            }

            .app-btn:hover {
                background-color: var(--btn-hover);
            }

            .app-surface {
                background-color: var(--surface-color);
            }

            /* Autofill Override */
            input:-webkit-autofill {
                -webkit-box-shadow: 0 0 0 1000px var(--input-bg) inset !important;
                -webkit-text-fill-color: var(--text-primary) !important;
                caret-color: var(--text-primary);
            }
        </style>
    </head>

    <body x-data="{ 
        theme: localStorage.getItem('hotelos_theme') || 'dark',
        setTheme(val) {
            this.theme = val;
            localStorage.setItem('hotelos_theme', val);
        }
      }" :class="{
          'theme-dark': theme === 'dark',
          'theme-light': theme === 'light',
          'theme-comfort': theme === 'comfort'
      }" class="app-bg h-screen w-full overflow-hidden text-base">

        <!-- Theme Switcher (Global) -->
        <div class="fixed top-6 right-6 flex items-center gap-1.5 app-card p-1 rounded-full shadow-lg z-50">
            <button @click="setTheme('dark')"
                :class="theme === 'dark' ? 'bg-white/20 text-white' : 'text-gray-500 hover:text-gray-700'"
                class="p-2 rounded-full transition-all" title="Dark Mode">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
            </button>
            <button @click="setTheme('light')"
                :class="theme === 'light' ? 'bg-black/10 text-black' : 'text-gray-400 hover:text-gray-200'"
                class="p-2 rounded-full transition-all" title="Light Mode">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>
            </button>
            <button @click="setTheme('comfort')"
                :class="theme === 'comfort' ? 'bg-amber-500/20 text-amber-800' : 'text-gray-400 hover:text-amber-800'"
                class="p-2 rounded-full transition-all" title="Eye Comfort">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                    </path>
                </svg>
            </button>
        </div>

        <!-- MAIN CONTENT -->
        <?= $content ?>

    </body>

    </html>
    <?php
}
?>