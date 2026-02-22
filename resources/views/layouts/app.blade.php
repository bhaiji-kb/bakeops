<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BakeOps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 270px;
            --sidebar-collapsed-width: 88px;
            --shell-bg: #f6f7fb;
            --sidebar-bg: #ffffff;
            --sidebar-border: #d9dee8;
            --nav-hover: #eef3ff;
            --nav-active-bg: #dfe9ff;
            --nav-active-text: #1f4aa8;
        }

        body {
            background: var(--shell-bg);
        }

        .app-shell {
            min-height: 100vh;
        }

        .app-sidebar {
            width: var(--sidebar-width);
            flex-shrink: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            transition: width 0.2s ease;
            overflow-x: hidden;
        }

        .app-main {
            min-width: 0;
        }

        .app-topbar {
            background: #fff;
            border-bottom: 1px solid var(--sidebar-border);
        }

        .brand-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: #1f4aa8;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .brand-logo {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid #d6deee;
            object-fit: cover;
            flex-shrink: 0;
            background: #fff;
        }

        .module-accordion .accordion-item {
            border: none;
            border-bottom: 1px solid #edf0f5;
            border-radius: 0;
        }

        .module-accordion .accordion-button {
            background: transparent;
            box-shadow: none;
            font-size: 0.86rem;
            font-weight: 600;
            color: #334155;
            padding: 0.8rem 0.9rem;
            gap: 0.6rem;
        }

        .module-accordion .accordion-button:not(.collapsed) {
            background: #f8faff;
            color: #1f4aa8;
        }

        .module-accordion .accordion-button::after {
            transform: scale(0.85);
        }

        .module-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 1px solid #d6deee;
            background: #f7f9ff;
            color: #1f4aa8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .module-icon i {
            font-size: 0.82rem;
        }

        .module-links {
            padding: 0.35rem 0.7rem 0.75rem;
            display: grid;
            gap: 0.2rem;
        }

        .app-nav-link {
            text-decoration: none;
            color: #344256;
            font-size: 0.84rem;
            padding: 0.45rem 0.55rem;
            border-radius: 0.45rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .app-nav-link:hover {
            background: var(--nav-hover);
            color: #1d355c;
        }

        .app-nav-link.active {
            background: var(--nav-active-bg);
            color: var(--nav-active-text);
            font-weight: 600;
        }

        .link-dot {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 1px solid #d6deee;
            background: #fff;
            color: #2a4f9d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.63rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .link-dot i {
            font-size: 0.7rem;
        }

        .shell-footer {
            border-top: 1px solid var(--sidebar-border);
            background: #fff;
        }

        body.sidebar-collapsed .app-sidebar {
            width: var(--sidebar-collapsed-width);
        }

        body.sidebar-collapsed .brand-text,
        body.sidebar-collapsed .module-label,
        body.sidebar-collapsed .link-label,
        body.sidebar-collapsed .accordion-button::after {
            display: none !important;
        }

        body.sidebar-collapsed .module-accordion .accordion-collapse {
            display: block !important;
            height: auto !important;
            visibility: visible;
        }

        @media (max-width: 992px) {
            .app-sidebar {
                width: 100%;
                max-width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--sidebar-border);
            }

            body.sidebar-collapsed .app-sidebar {
                width: 100%;
            }

            body.sidebar-collapsed .brand-text,
            body.sidebar-collapsed .module-label,
            body.sidebar-collapsed .link-label,
            body.sidebar-collapsed .accordion-button::after {
                display: inline !important;
            }
        }
    </style>
</head>
<body>
@auth
    @php
        $layoutSettings = app(\App\Services\BusinessSettingsService::class)->get();
        $brandName = trim((string) ($layoutSettings['business_name'] ?? 'BakeOps')) ?: 'BakeOps';
        $brandLogoUrl = trim((string) ($layoutSettings['business_logo_url'] ?? ''));
        $operationsOpen = request()->routeIs('pos.*') || request()->routeIs('orders.*') || request()->routeIs('customers.*');
        $ordersNavActive = request()->routeIs('orders.*');
        $inventoryOpen = request()->routeIs('products.*') || request()->routeIs('recipes.*') || request()->routeIs('production.*') || request()->routeIs('inventory.*');
        $accountsOpen = request()->routeIs('purchases.*') || request()->routeIs('suppliers.*') || request()->routeIs('reports.suppliers.*') || request()->routeIs('accounts.*') || request()->routeIs('expenses.*') || request()->routeIs('reports.sales.*') || request()->routeIs('reports.profit_loss.*');
        $auditOpen = request()->routeIs('logs.*');
        $adminOpen = request()->routeIs('users.*') || request()->routeIs('admin.settings.*') || request()->routeIs('integrations.connectors.*');
    @endphp

    <div class="app-shell d-flex">
        <aside id="app-sidebar" class="app-sidebar d-flex flex-column">
            <div class="d-flex align-items-center gap-2 px-3 py-3 border-bottom">
                @if($brandLogoUrl !== '')
                    <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }} Logo" class="brand-logo">
                @else
                    <span class="brand-icon"><i class="bi bi-cup-hot-fill"></i></span>
                @endif
                <div class="brand-text">
                    <div class="fw-semibold small">{{ $brandName }}</div>
                    <div class="text-muted" style="font-size: 11px;">Bakery ERP</div>
                </div>
            </div>

            <div class="module-accordion accordion" id="module-nav">
                @if(auth()->user()->hasAnyRole(['owner', 'manager', 'cashier']))
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="module-operations-heading">
                            <button class="accordion-button {{ $operationsOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#module-operations" aria-expanded="{{ $operationsOpen ? 'true' : 'false' }}" aria-controls="module-operations">
                                <span class="module-icon"><i class="bi bi-diagram-3"></i></span>
                                <span class="module-label">Operations</span>
                            </button>
                        </h2>
                        <div id="module-operations" class="accordion-collapse collapse {{ $operationsOpen ? 'show' : '' }}" aria-labelledby="module-operations-heading" data-bs-parent="#module-nav">
                            <div class="module-links">
                                <a href="{{ route('pos.online_orders.index') }}" class="app-nav-link {{ request()->routeIs('pos.online_orders.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-phone-vibrate"></i></span><span class="link-label">Online Queue</span></a>
                                <a href="{{ route('orders.index') }}" class="app-nav-link {{ $ordersNavActive ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-card-checklist"></i></span><span class="link-label">Order Management</span></a>
                                <a href="{{ route('pos.sales.index') }}" class="app-nav-link {{ request()->routeIs('pos.sales.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-journal-text"></i></span><span class="link-label">Invoice History</span></a>
                                <a href="{{ route('customers.index') }}" class="app-nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-people"></i></span><span class="link-label">Customers</span></a>
                            </div>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->hasAnyRole(['owner', 'manager', 'purchase']))
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="module-inventory-heading">
                            <button class="accordion-button {{ $inventoryOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#module-inventory" aria-expanded="{{ $inventoryOpen ? 'true' : 'false' }}" aria-controls="module-inventory">
                                <span class="module-icon"><i class="bi bi-box-seam"></i></span>
                                <span class="module-label">Inventory</span>
                            </button>
                        </h2>
                        <div id="module-inventory" class="accordion-collapse collapse {{ $inventoryOpen ? 'show' : '' }}" aria-labelledby="module-inventory-heading" data-bs-parent="#module-nav">
                            <div class="module-links">
                                <a href="{{ route('products.index') }}" class="app-nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-tags"></i></span><span class="link-label">Products</span></a>
                                <a href="{{ route('recipes.index') }}" class="app-nav-link {{ request()->routeIs('recipes.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-list-check"></i></span><span class="link-label">Recipes</span></a>
                                <a href="{{ route('production.index') }}" class="app-nav-link {{ request()->routeIs('production.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-gear-wide-connected"></i></span><span class="link-label">Production</span></a>
                                <a href="{{ route('inventory.index') }}" class="app-nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-boxes"></i></span><span class="link-label">Stock</span></a>
                            </div>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->hasAnyRole(['owner', 'manager', 'purchase']))
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="module-accounts-heading">
                            <button class="accordion-button {{ $accountsOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#module-accounts" aria-expanded="{{ $accountsOpen ? 'true' : 'false' }}" aria-controls="module-accounts">
                                <span class="module-icon"><i class="bi bi-wallet2"></i></span>
                                <span class="module-label">Accounts</span>
                            </button>
                        </h2>
                        <div id="module-accounts" class="accordion-collapse collapse {{ $accountsOpen ? 'show' : '' }}" aria-labelledby="module-accounts-heading" data-bs-parent="#module-nav">
                            <div class="module-links">
                                <a href="{{ route('purchases.index') }}" class="app-nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-cart-check"></i></span><span class="link-label">Purchases</span></a>
                                <a href="{{ route('suppliers.index') }}" class="app-nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-truck"></i></span><span class="link-label">Suppliers</span></a>
                                <a href="{{ route('reports.suppliers.ledger') }}" class="app-nav-link {{ request()->routeIs('reports.suppliers.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-hourglass-split"></i></span><span class="link-label">Payables</span></a>
                                @if(auth()->user()->hasAnyRole(['owner', 'manager']))
                                    <a href="{{ route('accounts.dashboard') }}" class="app-nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-speedometer2"></i></span><span class="link-label">Dashboard</span></a>
                                    <a href="{{ route('expenses.index') }}" class="app-nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-cash-stack"></i></span><span class="link-label">Expenses</span></a>
                                    <a href="{{ route('reports.sales.daily') }}" class="app-nav-link {{ request()->routeIs('reports.sales.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-graph-up"></i></span><span class="link-label">Sales Report</span></a>
                                    <a href="{{ route('reports.profit_loss.monthly') }}" class="app-nav-link {{ request()->routeIs('reports.profit_loss.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-pie-chart"></i></span><span class="link-label">P&amp;L</span></a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->hasAnyRole(['owner', 'manager']))
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="module-audit-heading">
                            <button class="accordion-button {{ $auditOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#module-audit" aria-expanded="{{ $auditOpen ? 'true' : 'false' }}" aria-controls="module-audit">
                                <span class="module-icon"><i class="bi bi-shield-check"></i></span>
                                <span class="module-label">Audit</span>
                            </button>
                        </h2>
                        <div id="module-audit" class="accordion-collapse collapse {{ $auditOpen ? 'show' : '' }}" aria-labelledby="module-audit-heading" data-bs-parent="#module-nav">
                            <div class="module-links">
                                <a href="{{ route('logs.index') }}" class="app-nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-clock-history"></i></span><span class="link-label">Activity Logs</span></a>
                            </div>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->hasAnyRole(['owner']))
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="module-admin-heading">
                            <button class="accordion-button {{ $adminOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#module-admin" aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="module-admin">
                                <span class="module-icon"><i class="bi bi-sliders"></i></span>
                                <span class="module-label">Admin</span>
                            </button>
                        </h2>
                        <div id="module-admin" class="accordion-collapse collapse {{ $adminOpen ? 'show' : '' }}" aria-labelledby="module-admin-heading" data-bs-parent="#module-nav">
                            <div class="module-links">
                                <a href="{{ route('users.index') }}" class="app-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-person-badge"></i></span><span class="link-label">Users</span></a>
                                <a href="{{ route('admin.settings.index') }}" class="app-nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-gear"></i></span><span class="link-label">Settings</span></a>
                                <a href="{{ route('integrations.connectors.index') }}" class="app-nav-link {{ request()->routeIs('integrations.connectors.*') ? 'active' : '' }}"><span class="link-dot"><i class="bi bi-plug"></i></span><span class="link-label">Connectors</span></a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </aside>

        <div class="app-main d-flex flex-column flex-grow-1">
            <header class="app-topbar px-3 py-2 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="sidebar-toggle">&lt;&lt;</button>
                    <span class="small text-muted">Welcome {{ auth()->user()->name }}</span>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Logout</button>
                </form>
            </header>

            <main class="px-3 py-3 flex-grow-1">
                @yield('content')
            </main>

            <footer class="shell-footer text-center text-muted py-2">
                <p class="mb-0 small">&copy; 2026 BakeOps</p>
            </footer>
        </div>
    </div>
@else
    @php
        $layoutSettings = app(\App\Services\BusinessSettingsService::class)->get();
        $brandName = trim((string) ($layoutSettings['business_name'] ?? 'BakeOps')) ?: 'BakeOps';
        $brandLogoUrl = trim((string) ($layoutSettings['business_logo_url'] ?? ''));
    @endphp
    <div class="container py-3">
        <header class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
            <h1 class="h4 mb-0 d-flex align-items-center gap-2">
                @if($brandLogoUrl !== '')
                    <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }} Logo" class="brand-logo">
                @endif
                <span>{{ $brandName }}</span>
            </h1>
            <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary">Login</a>
        </header>

        <main class="py-2">
            @yield('content')
        </main>

        <footer class="text-center text-muted border-top pt-3 mt-4">
            <p class="mb-0">&copy; 2026 BakeOps</p>
        </footer>
    </div>
@endauth

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const key = 'bakeops_sidebar_collapsed';
    const toggle = document.getElementById('sidebar-toggle');
    if (!toggle) return;

    const applyState = (collapsed) => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
    };

    try {
        applyState(localStorage.getItem(key) === '1');
    } catch (e) {
        applyState(false);
    }

    toggle.addEventListener('click', () => {
        const next = !document.body.classList.contains('sidebar-collapsed');
        applyState(next);
        try {
            localStorage.setItem(key, next ? '1' : '0');
        } catch (e) {
        }
    });
})();
</script>
@yield('scripts')
</body>
</html>
