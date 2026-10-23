export const money = (value) => `£${Number(value ?? 0).toFixed(2)}`;

const isOpen = (v) => v === null || v === undefined || v === "";

export function amountRange(rate) {
    const min = Number(rate.min_amount ?? 0);
    const max = isOpen(rate.max_amount) ? null : Number(rate.max_amount);

    if (min === 0 && max === null) return "Any amount";
    if (max === null) return `${money(min)}+`;
    return `${money(min)} – ${money(max)}`;
}

export function stepLabel(step) {
    const from = Number(step.from_sequence);
    if (isOpen(step.to_sequence)) return `#${from}+`;
    const to = Number(step.to_sequence);
    return from === to ? `#${from}` : `#${from}–${to}`;
}

export function stepsText(rate) {
    if (!rate.steps?.length) return "—";
    return rate.steps
        .map((s) => `${stepLabel(s)}: ${money(s.amount)}`)
        .join(" · ");
}

export function firstTotal(rate) {
    return (
        Number(rate.first_topup_amount || 0) +
        Number(rate.first_bonus_amount || 0)
    );
}

/** Total a connection can earn over its lifetime, or null when a band has no end. */
export function lifetimeTotal(rate) {
    const steps = rate.steps ?? [];
    if (steps.some((s) => isOpen(s.to_sequence))) return null;

    return steps.reduce((sum, s) => {
        const count = Number(s.to_sequence) - Number(s.from_sequence) + 1;
        return sum + Math.max(count, 0) * Number(s.amount || 0);
    }, firstTotal(rate));
}

export const skipReasons = {
    number_not_in_allowed_list: "Number not in allowed list",
    connection_owned_by_other_retailer: "SIM belongs to another retailer",
    no_level: "Retailer has no level",
    no_rate_for_operator_or_amount: "No rate for this operator / amount",
    no_ongoing_rate_for_sequence: "No ongoing rate for this top-up number",
    zero_commission: "Rate is £0",
};

export const changeTypes = {
    assigned: "Level assigned",
    upgrade: "Upgraded",
    downgrade: "Downgraded",
    removed: "Level removed",
    auto_upgrade: "Auto upgrade",
    settings: "Settings changed",
};
