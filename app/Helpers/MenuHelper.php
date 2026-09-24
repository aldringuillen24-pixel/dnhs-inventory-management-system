<?php

namespace App\Helpers;

class MenuHelper
{
    public static function getMainNavItems()
    {
        return [
            [
                'icon' => 'dashboard',
                'name' => 'Dashboard',
                'path' => 'dashboard',
            ],
            [
                'icon' => 'calendar',
                'name' => 'Calendar',
                'path' => '/calendar',
            ],
            [
                'icon' => 'user-profile',
                'name' => 'User Profile',
                'path' => '/profile',
            ],
            [
                'name' => 'Forms',
                'icon' => 'forms',
                'subItems' => [
                    ['name' => 'Form Elements', 'path' => '/form-elements', 'pro' => false],
                ],
            ],
            [
                'name' => 'Tables',
                'icon' => 'tables',
                'subItems' => [
                    ['name' => 'Basic Tables', 'path' => '/basic-tables', 'pro' => false]
                ],
            ],
            [
                'name' => 'Pages',
                'icon' => 'pages',
                'subItems' => [
                    ['name' => 'Blank Page', 'path' => '/blank', 'pro' => false],
                    ['name' => '404 Error', 'path' => '/error-404', 'pro' => false]
                ],
            ],
        ];
    }

    public static function getOthersItems()
    {
        return [
            [
                'icon' => 'charts',
                'name' => 'Charts',
                'subItems' => [
                    ['name' => 'Line Chart', 'path' => '/line-chart', 'pro' => false],
                    ['name' => 'Bar Chart', 'path' => '/bar-chart', 'pro' => false]
                ],
            ],
            [
                'icon' => 'ui-elements',
                'name' => 'UI Elements',
                'subItems' => [
                    ['name' => 'Alerts', 'path' => '/alerts', 'pro' => false],
                    ['name' => 'Avatar', 'path' => '/avatars', 'pro' => false],
                    ['name' => 'Badge', 'path' => '/badge', 'pro' => false],
                    ['name' => 'Buttons', 'path' => '/buttons', 'pro' => false],
                    ['name' => 'Images', 'path' => '/image', 'pro' => false],
                    ['name' => 'Videos', 'path' => '/videos', 'pro' => false],
                ],
            ],
            [
                'icon' => 'authentication',
                'name' => 'Authentication',
                'subItems' => [
                    ['name' => 'Sign In', 'path' => '/signin', 'pro' => false],
                    ['name' => 'Sign Up', 'path' => '/signup', 'pro' => false],
                ],
            ],
        ];
    }

    public static function getMenuGroups($roleName = null)
    {
        if (is_string($roleName) && trim($roleName) !== '') {
            return [
                [
                    'title' => 'Menu',
                    'items' => self::getMenuItemsForRole($roleName),
                ],
            ];
        }

        return [
            [
                'title' => 'Menu',
                'items' => self::getMainNavItems()
            ],
            [
                'title' => 'Others',
                'items' => self::getOthersItems()
            ]
        ];
    }

    protected static function getMenuItemsForRole($roleName)
    {
        $roleKey = strtolower(trim($roleName));

        return match ($roleKey) {
            'administrator' => [
                ['icon' => 'dashboard', 'name' => 'Dashboard', 'path' => '/admin/dashboard'],
                ['icon' => 'users', 'name' => 'User Management', 'path' => '/admin/users-management'],
                ['icon' => 'reports', 'name' => 'Reports', 'path' => '/admin/reports'],
                ['icon' => 'settings', 'name' => 'System Settings', 'path' => '/admin/settings'],
            ],
            'school head' => [
                ['icon' => 'dashboard', 'name' => 'Dashboard', 'path' => '/school-head/dashboard'],
                ['icon' => 'inventory', 'name' => 'Inventory Overview', 'path' => '/school-head/inventory/overview'],
                ['icon' => 'reports', 'name' => 'Reports', 'path' => '/school-head/reports'],
                ['icon' => 'user-profile', 'name' => 'Profile', 'path' => '/school-head/profile'],
            ],
            'property custodian' => [
                ['icon' => 'dashboard', 'name' => 'Dashboard', 'path' => '/property-custodian/dashboard'],
                ['icon' => 'inventory', 'name' => 'Inventory', 'path' => '/property-custodian/inventory'],
                ['icon' => 'transactions', 'name' => 'Transaction', 'path' => '/property-custodian/transactions'],
                ['icon' => 'reports', 'name' => 'Reports', 'path' => '/property-custodian/reports'],
                ['icon' => 'user-profile', 'name' => 'Profile', 'path' => '/property-custodian/profile'],
            ],
            'inspector' => [
                ['icon' => 'dashboard', 'name' => 'Dashboard', 'path' => '/'],
                ['icon' => 'inspection', 'name' => 'Items Inspection', 'path' => '/inspection'],
                ['icon' => 'history', 'name' => 'Inspection History', 'path' => '/inspection/history'],
                ['icon' => 'reports', 'name' => 'Reports', 'path' => '/reports'],
                ['icon' => 'user-profile', 'name' => 'Profile', 'path' => '/profile'],
            ],
            'end user' => [
                ['icon' => 'dashboard', 'name' => 'Dashboard', 'path' => '/end-user/dashboard'],
                ['icon' => 'requests', 'name' => 'Requests', 'path' => '/end-user/requests'],
                ['icon' => 'assigned-items', 'name' => 'My Assigned Items', 'path' => '/end-user/assigned-items'],
                ['icon' => 'user-profile', 'name' => 'Profile', 'path' => '/end-user/profile'],
            ],
            default => self::getMainNavItems(),
        };
    }

    public static function isActive($path)
    {
        return request()->is(ltrim($path, '/'));
    }

    public static function getIconSvg($iconName)
    {
        $icons = [
            // Dashboard (Layout Dashboard)
            'dashboard' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',

            // Inventory & Assets (Boxes / Package)
            'inventory' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',

            // Transactions & Transfers (Arrow Left-Right)
            'transactions' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>',

            // Requests & Approvals (Clipboard Check)
            'requests' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',

            // Assigned Items (Shield Check / Verified Possession)
            'assigned-items' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>',

            // Inspections (Scan / Search Audit)
            'inspection' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/><path d="m16 16-1.9-1.9"/></svg>',

            // History / Past Inspections (History Clock)
            'history' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>',

            // Reports & Analytics (Bar Chart 3)
            'reports' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>',

            // User Management (Users Group)
            'users' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',

            // User Profile (User Circle)
            'user-profile' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/></svg>',

            // System Settings (Sliders / Cog)
            'settings' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',

            // Calendar
            'calendar' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>',

            // Task / Checklist
            'task' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>',

            // Forms
            'forms' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',

            // Tables
            'tables' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/></svg>',

            // Pages
            'pages' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h14a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v4"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M2 15h10"/><path d="m9 18 3-3-3-3"/></svg>',

            // Charts
            'charts' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>',

            // UI Elements
            'ui-elements' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.9a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>',

            // Authentication / Lock
            'authentication' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        ];

        return $icons[$iconName] ?? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m12 8 4 4-4 4M8 12h8"/></svg>';
    }
}
