<?php

namespace App\Services;

use App\Models\User;

class AiCapabilityPolicy
{
    public const VIEW_OWN_ASSIGNMENTS = 'view_own_assignments';
    public const VIEW_OWN_REQUESTS = 'view_own_requests';
    public const VIEW_WAREHOUSE_AVAILABILITY = 'view_warehouse_availability';
    public const VIEW_INVENTORY_STOCK = 'view_inventory_stock';
    public const VIEW_INVENTORY_LOCATION = 'view_inventory_location';
    public const VIEW_PENDING_REQUESTS = 'view_pending_requests';
    public const VIEW_LOW_STOCK = 'view_low_stock';
    public const VIEW_DEMAND_FORECAST = 'view_demand_forecast';
    public const VIEW_PROCUREMENT_PRIORITIES = 'view_procurement_priorities';
    public const VIEW_EXECUTIVE_REPORTS = 'view_executive_reports';
    public const VIEW_SYSTEM_SUMMARY = 'view_system_summary';
    public const VIEW_INVENTORY_VALUATION = 'view_inventory_valuation';
    public const VIEW_ASSIGNMENTS = 'view_assignments';
    public const VIEW_MAINTENANCE = 'view_maintenance';
    public const VIEW_DISPOSAL = 'view_disposal';
    public const VIEW_READY_TO_DISPOSE = 'view_ready_to_dispose';
    public const VIEW_PURCHASE_HISTORY = 'view_purchase_history';
    public const VIEW_ITEM_STATUS = 'view_item_status';

    private const CAPABILITIES = [
        self::VIEW_OWN_ASSIGNMENTS,
        self::VIEW_OWN_REQUESTS,
        self::VIEW_WAREHOUSE_AVAILABILITY,
        self::VIEW_INVENTORY_STOCK,
        self::VIEW_INVENTORY_LOCATION,
        self::VIEW_PENDING_REQUESTS,
        self::VIEW_LOW_STOCK,
        self::VIEW_DEMAND_FORECAST,
        self::VIEW_PROCUREMENT_PRIORITIES,
        self::VIEW_EXECUTIVE_REPORTS,
        self::VIEW_SYSTEM_SUMMARY,
        self::VIEW_INVENTORY_VALUATION,
        self::VIEW_ASSIGNMENTS,
        self::VIEW_MAINTENANCE,
        self::VIEW_DISPOSAL,
        self::VIEW_READY_TO_DISPOSE,
        self::VIEW_PURCHASE_HISTORY,
        self::VIEW_ITEM_STATUS,
    ];

    private const PROPERTY_CUSTODIAN_CAPABILITIES = [
        self::VIEW_OWN_ASSIGNMENTS,
        self::VIEW_OWN_REQUESTS,
        self::VIEW_WAREHOUSE_AVAILABILITY,
        self::VIEW_INVENTORY_STOCK,
        self::VIEW_INVENTORY_LOCATION,
        self::VIEW_PENDING_REQUESTS,
        self::VIEW_LOW_STOCK,
        self::VIEW_DEMAND_FORECAST,
        self::VIEW_PROCUREMENT_PRIORITIES,
        self::VIEW_EXECUTIVE_REPORTS,
        self::VIEW_INVENTORY_VALUATION,
        self::VIEW_ASSIGNMENTS,
        self::VIEW_MAINTENANCE,
        self::VIEW_DISPOSAL,
        self::VIEW_READY_TO_DISPOSE,
        self::VIEW_PURCHASE_HISTORY,
        self::VIEW_ITEM_STATUS,
    ];

    private const ROLE_CAPABILITIES = [
        'Property Custodian' => self::PROPERTY_CUSTODIAN_CAPABILITIES,
    ];

    public function allows(User $user, string $capability): bool
    {
        if (!in_array($capability, self::CAPABILITIES, true)) {
            return false;
        }

        $roleName = $user->role?->role_name;

        return $roleName !== null
            && in_array($capability, self::ROLE_CAPABILITIES[$roleName] ?? [], true);
    }

    public function capabilityForIntent(string $intent): ?string
    {
        return match ($intent) {
            'availability' => self::VIEW_WAREHOUSE_AVAILABILITY,
            'stock' => self::VIEW_INVENTORY_STOCK,
            'pending_requests' => self::VIEW_PENDING_REQUESTS,
            'low_stock' => self::VIEW_LOW_STOCK,
            'forecast' => self::VIEW_DEMAND_FORECAST,
            'executive_reports' => self::VIEW_EXECUTIVE_REPORTS,
            'system_summary' => self::VIEW_SYSTEM_SUMMARY,
            'inventory_valuation' => self::VIEW_INVENTORY_VALUATION,
            'own_assignments' => self::VIEW_OWN_ASSIGNMENTS,
            'own_requests' => self::VIEW_OWN_REQUESTS,
            'item_status' => self::VIEW_ITEM_STATUS,
            'assignments' => self::VIEW_ASSIGNMENTS,
            'maintenance' => self::VIEW_MAINTENANCE,
            'disposal' => self::VIEW_DISPOSAL,
            'ready_to_dispose' => self::VIEW_READY_TO_DISPOSE,
            'location' => self::VIEW_INVENTORY_LOCATION,
            'purchase_history' => self::VIEW_PURCHASE_HISTORY,
            default => null,
        };
    }

    public function isKnownCapability(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    public function capabilitiesForRole(?string $roleName): array
    {
        return $roleName === null ? [] : (self::ROLE_CAPABILITIES[$roleName] ?? []);
    }

    public function canUseAssistant(User $user): bool
    {
        return $this->capabilitiesForRole($user->role?->role_name) !== [];
    }

    public function assistantHelpText(?User $user): string
    {
        $capabilities = $user === null
            ? []
            : array_fill_keys($this->capabilitiesForRole($user->role?->role_name), true);

        if ($capabilities === []) {
            return 'I can only provide information available for your role.';
        }

        if ($user?->role?->role_name === 'End User'
            && isset($capabilities[self::VIEW_OWN_ASSIGNMENTS])
            && isset($capabilities[self::VIEW_OWN_REQUESTS])
            && !isset($capabilities[self::VIEW_SYSTEM_SUMMARY])) {
            return 'I can help you check your assigned items, your requests, pending requests, and available inventory.';
        }

        if (isset($capabilities[self::VIEW_OWN_ASSIGNMENTS])
            && isset($capabilities[self::VIEW_SYSTEM_SUMMARY])
            && isset($capabilities[self::VIEW_INVENTORY_VALUATION])) {
            return 'I can help with inventory availability, assigned items, requests, inventory locations, low-stock items, forecasts, assignment records, maintenance, disposal, purchase history, item status, reports, system summaries, and inventory valuation. For what to buy first, open Demand Forecast and use AI Decision Support.';
        }

        if (isset($capabilities[self::VIEW_EXECUTIVE_REPORTS]) && count($capabilities) === 1) {
            return 'I can help with approved inventory summaries and reports.';
        }

        $help = [];

        if (isset($capabilities[self::VIEW_WAREHOUSE_AVAILABILITY])) {
            $help[] = isset($capabilities[self::VIEW_INVENTORY_STOCK])
                ? 'stock availability'
                : 'available inventory';
        }
        if (isset($capabilities[self::VIEW_INVENTORY_STOCK])) {
            $help[] = 'inventory stock';
        }
        if (isset($capabilities[self::VIEW_INVENTORY_LOCATION])) {
            $help[] = 'inventory locations';
        }
        if (isset($capabilities[self::VIEW_PENDING_REQUESTS])) {
            $help[] = 'pending requests';
        }
        if (isset($capabilities[self::VIEW_LOW_STOCK])) {
            $help[] = 'low-stock items';
        }
        if (isset($capabilities[self::VIEW_DEMAND_FORECAST])) {
            $help[] = 'demand forecasts';
        }
        // Procurement priorities are intentionally absent from this list. They
        // are answered by AI Decision Support on the Demand Forecast page, so
        // advertising them here would send users to a panel that no longer
        // handles the question. The capability itself is retained because that
        // panel authorises against it.
        if (isset($capabilities[self::VIEW_EXECUTIVE_REPORTS])) {
            $help[] = 'reports';
        }
        if (isset($capabilities[self::VIEW_SYSTEM_SUMMARY])) {
            $help[] = 'system summaries';
        }
        if (isset($capabilities[self::VIEW_INVENTORY_VALUATION])) {
            $help[] = 'inventory valuation';
        }
        if (isset($capabilities[self::VIEW_ASSIGNMENTS])) {
            $help[] = 'assignment records';
        }
        if (isset($capabilities[self::VIEW_MAINTENANCE])) {
            $help[] = 'maintenance records';
        }
        if (isset($capabilities[self::VIEW_DISPOSAL])) {
            $help[] = 'disposal records';
        }
        if (isset($capabilities[self::VIEW_READY_TO_DISPOSE])) {
            $help[] = 'items ready for disposal';
        }
        if (isset($capabilities[self::VIEW_PURCHASE_HISTORY])) {
            $help[] = 'purchase history';
        }
        if (isset($capabilities[self::VIEW_ITEM_STATUS])) {
            $help[] = 'item status';
        }

        if (isset($capabilities[self::VIEW_OWN_REQUESTS])) {
            $help[] = 'requests';
        }

        if (isset($capabilities[self::VIEW_OWN_ASSIGNMENTS])) {
            $help[] = 'assigned items';
        }

        if ($help === []) {
            return 'I can only provide information available for your role.';
        }

        $last = array_pop($help);
        $helpText = $help === [] ? $last : implode(', ', $help) . ', and ' . $last;

        return 'I can help with ' . $helpText . '.';
    }
}