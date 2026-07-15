<?php
/**
 * Shared helpers for Utility CRUD pages
 */

function utility_toggle_status(PDO $pdo, string $table, int $id): void
{
    $allowed = [
        'countries','states','cities','banks','bank_accounts','deductions','news','plans','package_plans',
        'product_categories','product_subcategories','product_sizes','product_colors','subcategory_settings',
        'products','product_vendors','commodity_prices',
    ];
    if (!in_array($table, $allowed, true) || $id < 1) {
        flash('error', 'Invalid request.');
        return;
    }
    $pdo->prepare("UPDATE `$table` SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
    log_activity('utility_toggle', "$table #$id status toggled");
    flash('success', 'Status updated.');
}

function utility_delete(PDO $pdo, string $table, int $id): bool
{
    $allowed = [
        'countries','states','cities','banks','bank_accounts','deductions','news','plans','package_plans',
        'product_categories','product_subcategories','product_sizes','product_colors','subcategory_settings',
        'products','product_vendors','commodity_prices','stock_purchases',
    ];
    if (!in_array($table, $allowed, true) || $id < 1) {
        flash('error', 'Invalid request.');
        return false;
    }
    try {
        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        log_activity('utility_delete', "Deleted $table #$id");
        flash('success', 'Record deleted.');
        return true;
    } catch (PDOException $e) {
        flash('error', 'Cannot delete: record is in use by other data.');
        return false;
    }
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e($status) . '</span>';
}

function icon_svg(string $name): string
{
    $icons = [
        'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
        'toggle-on' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="5" width="22" height="14" rx="7" fill="currentColor" fill-opacity="0.2"/><circle cx="16" cy="12" r="3" fill="currentColor"/></svg>',
        'toggle-off' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="8" cy="12" r="3" fill="currentColor"/></svg>',
        'toggle' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="16" cy="12" r="3"/></svg>',
        'delete' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>',
        'view' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'package' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function action_edit(string $href): string
{
    return '<a href="' . e($href) . '" class="btn-icon btn-icon-edit" title="Edit" aria-label="Edit">' . icon_svg('edit') . '</a>';
}

function action_toggle(string $href, string $status = 'active'): string
{
    $on = ($status === 'active');
    $cls = $on ? 'btn-icon btn-icon-toggle is-on' : 'btn-icon btn-icon-toggle is-off';
    $title = $on ? 'Active — click to deactivate' : 'Inactive — click to activate';
    $icon = $on ? 'toggle-on' : 'toggle-off';
    return '<a href="' . e($href) . '" class="' . $cls . '" title="' . e($title) . '" aria-label="' . e($title) . '">' . icon_svg($icon) . '</a>';
}

function action_delete(string $href, string $confirm = 'Delete this record?'): string
{
    return '<a href="' . e($href) . '" class="btn-icon btn-icon-delete" title="Delete" aria-label="Delete" data-confirm="' . e($confirm) . '">' . icon_svg('delete') . '</a>';
}

function action_buttons(int $id, string $deleteConfirm = 'Delete this record?', string $extraQuery = '', string $status = 'active'): string
{
    $q = $extraQuery !== '' ? '&' . ltrim($extraQuery, '&') : '';
    return '<div class="action-icons">'
        . action_edit('?edit=' . $id)
        . action_toggle('?toggle=' . $id . $q, $status)
        . action_delete('?delete=' . $id, $deleteConfirm)
        . '</div>';
}
