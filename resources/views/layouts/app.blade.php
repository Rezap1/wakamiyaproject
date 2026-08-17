<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen WAKAMIYA V1.0</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Dark Scrollbar for Sidebar */
        .dark-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .dark-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .dark-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155; /* slate-700 */
            border-radius: 20px;
        }
        .dark-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #475569; /* slate-600 */
        }
    </style>
    <script>
        // Force light mode to prevent white text on the forced light background
        document.documentElement.classList.remove('dark');
        localStorage.setItem('color-theme', 'light');
    </script>
</head>
<body class="text-slate-800 bg-gray-300 antialiased selection:bg-blue-600 selection:text-white">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar Overlay (Visible on all screens when sidebar is open) -->
        <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-30 hidden transition-opacity duration-300 opacity-0" onclick="toggleSidebar()"></div>

        <!-- Framework Component: Sidebar -->
        <x-dashboard.sidebar :userRole="$userRole ?? 'Unknown'" />

        <!-- Main Content -->
        <main class="main-content flex-1 flex flex-col relative z-0 overflow-y-auto bg-gray-300">
            
            <!-- Framework Component: Topbar -->
            <x-dashboard.topbar :userRole="$userRole ?? 'Unknown'">
                <x-slot:header>
                    @yield('header')
                </x-slot:header>
            </x-dashboard.topbar>

            <!-- Content -->
            <div class="p-6 lg:p-8 w-full max-w-[1600px] mx-auto">


                @yield('content')
            </div>
        </main>
    </div>

    <!-- Sidebar Toggle Logic -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-sidebar-overlay');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => {
                    overlay.classList.remove('opacity-0');
                }, 10);
            } else {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 300);
            }
        }
        
        // Global Double-Submit Protection removed due to browser compatibility issues
    </script>
    
    <x-mobile-bottom-nav :userRole="$userRole ?? 'STUDENT'" />
    <x-floating-qr-button />
    <x-toast />
    <x-confirm-dialog />
</body>
</html>



