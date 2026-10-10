<?php

namespace App\Support;

/**
 * Catalog of staff roles (PRD section 4). A staff member belongs to one
 * branch and can only ever work in that branch; the role decides what they
 * may do there:
 *   - can_sell: use the POS
 *   - can_view_reservations: see online orders without changing them
 *   - can_manage_orders: accept, pack and hand over online orders, plus Logistics
 *   - can_approve_voids: may void or refund a sale on their own login, and
 *     approve one for a colleague who cannot (the supervisor at the POS).
 *     The same roles may give any discount; everyone else is capped at
 *     CASHIER_MAX_DISCOUNT_PERCENT unless a supervisor approves more.
 *   - inventory: products, receiving stock, counts, transfers between branches
 *   - reports: the branch's reports
 *   - activity: the branch's activity log
 *   - all_sales: every sale in the branch, not just their own
 */
class StaffRoles
{
    /** The largest discount, as a share of the sale, a non-supervisor may give alone. */
    public const CASHIER_MAX_DISCOUNT_PERCENT = 10;

    public const ROLES = [
        'branch_manager' => [
            'label' => 'Branch Manager',
            'summary' => 'Runs the branch: POS, online orders, stock, reports and approvals.',
            'can_sell' => true,
            'can_view_reservations' => true,
            'can_manage_orders' => true,
            'can_approve_voids' => true,
            'inventory' => true,
            'reports' => true,
            'activity' => true,
            'all_sales' => true,
        ],
        'pharmacist' => [
            'label' => 'Pharmacist',
            'summary' => 'Sells, handles online orders, and can approve voids and refunds.',
            'can_sell' => true,
            'can_view_reservations' => false,
            'can_manage_orders' => true,
            'can_approve_voids' => true,
        ],
        'cashier' => [
            'label' => 'Cashier',
            'summary' => 'Sells at the POS. Needs a supervisor for voids and big discounts.',
            'can_sell' => true,
            'can_view_reservations' => false,
            'can_manage_orders' => false,
            'can_approve_voids' => false,
        ],
        'inventory_officer' => [
            'label' => 'Inventory Officer',
            'summary' => 'Receives stock, adjusts counts and moves stock between branches. Does not sell.',
            'can_sell' => false,
            'can_view_reservations' => false,
            'can_manage_orders' => false,
            'can_approve_voids' => false,
            'inventory' => true,
        ],
        'finance' => [
            'label' => 'Finance Officer',
            'summary' => 'Sees every sale and report. Cannot sell or change stock.',
            'can_sell' => false,
            'can_view_reservations' => false,
            'can_manage_orders' => false,
            'can_approve_voids' => false,
            'reports' => true,
            'all_sales' => true,
        ],
    ];

    public static function label(?string $roleKey): string
    {
        return self::ROLES[$roleKey]['label'] ?? 'Staff';
    }

    /** Whether a role has one of the newer abilities: inventory, reports, activity, all_sales. */
    public static function can(?string $roleKey, string $ability): bool
    {
        return (bool) (self::ROLES[$roleKey][$ability] ?? false);
    }

    /**
     * Accounts created before roles existed have no staff_role — treat them
     * as sell-capable so existing staff aren't locked out of the POS.
     */
    public static function canSell(?string $roleKey): bool
    {
        if ($roleKey === null || $roleKey === '') {
            return true;
        }

        return self::ROLES[$roleKey]['can_sell'] ?? true;
    }

    /**
     * Read-only access to the Online Orders list. A role with full
     * order-management rights implies this too.
     */
    public static function canViewReservations(?string $roleKey): bool
    {
        return self::canManageOrders($roleKey) || (self::ROLES[$roleKey]['can_view_reservations'] ?? false);
    }

    /**
     * Full online-order handling (confirm/ready/fulfill/cancel) plus the
     * Logistics page — the staff-level equivalent of what the owner can
     * already do.
     */
    public static function canManageOrders(?string $roleKey): bool
    {
        return self::ROLES[$roleKey]['can_manage_orders'] ?? false;
    }

    /**
     * Voiding or refunding a sale takes money back out of the cash drawer,
     * so it needs a supervisor. These roles can do it themselves; everyone
     * else needs one of them (or the owner) to enter their login to approve.
     */
    public static function canApproveVoids(?string $roleKey): bool
    {
        return self::ROLES[$roleKey]['can_approve_voids'] ?? false;
    }

    /**
     * How large a discount this staff role may give without approval, as a
     * percentage of the sale. Null means no limit.
     */
    public static function maxDiscountPercent(?string $roleKey): ?float
    {
        return self::canApproveVoids($roleKey) ? null : self::CASHIER_MAX_DISCOUNT_PERCENT;
    }
}
