/**
 * Display-only grouping for stock-movement audit ledgers.
 *
 * Adjacent entries identical in every audited dimension collapse into one
 * expandable group, so a ten-unit stock-in reads as one line instead of ten.
 * Raw entries are never modified; anything differing stays its own row, so
 * merged rows can never claim something the records do not say.
 *
 * Shared by the custodian Transactions ledger and the school-head audit view
 * so both group by exactly the same rule. The actor label comes from the
 * caller so the key always matches what that view displays.
 */
export function ledgerGroupKey(entry, actorLabel) {
    return [
        entry.inventory?.item_name ?? 'Unknown item',
        entry.movement_type ?? '',
        String(entry.created_at ?? '').slice(0, 16),
        actorLabel,
        entry.display_details || entry.notes || 'No additional details recorded.',
        Number(entry.quantity ?? 0),
    ].join('|');
}

export function groupLedgerEntries(entries, actorLabelFor) {
    const groups = [];

    entries.forEach((entry, index) => {
        const key = ledgerGroupKey(entry, actorLabelFor(entry));
        const current = groups[groups.length - 1];

        if (current && current.baseKey === key) {
            current.entries.push(entry);
            return;
        }

        groups.push({ key: `${index}:${key}`, baseKey: key, entries: [entry] });
    });

    return groups.map((group) => {
        const total = group.entries.reduce((sum, entry) => sum + Number(entry.quantity ?? 0), 0);
        // Oldest record's before → newest record's after, regardless of the
        // API sort direction, so the range stays audit-honest.
        const ordered = [...group.entries].sort((a, b) => String(a.created_at ?? '').localeCompare(String(b.created_at ?? '')));

        return {
            ...group,
            count: group.entries.length,
            totalQuantity: total,
            first: group.entries[0],
            rangeBefore: ordered[0]?.quantity_before,
            rangeAfter: ordered[ordered.length - 1]?.quantity_after,
        };
    });
}
