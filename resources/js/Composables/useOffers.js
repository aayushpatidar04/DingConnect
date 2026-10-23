import { money } from "@/Composables/useCommission";

const fmtNum = (n) => (Number.isInteger(Number(n)) ? Number(n) : Number(n).toFixed(2));

/** "20% cashback (up to £5.00)" or "£1.00 cashback" */
export function benefitLabel(offer) {
    if (offer.type === "percent") {
        const cap =
            offer.max_cashback !== null && offer.max_cashback !== undefined && offer.max_cashback !== ""
                ? ` (up to ${money(offer.max_cashback)})`
                : "";
        return `${fmtNum(offer.value)}% cashback${cap}`;
    }
    return `${money(offer.value)} cashback`;
}

/** "£10.00 – £30.00", "£10.00 and above", "Any amount" */
export function offerRange(offer) {
    const min = Number(offer.min_amount || 0);
    const max =
        offer.max_amount === null || offer.max_amount === undefined || offer.max_amount === ""
            ? null
            : Number(offer.max_amount);

    if (min === 0 && max === null) return "Any amount";
    if (max === null) return `${money(min)} and above`;
    return `${money(min)} – ${money(max)}`;
}

export function formatOfferDate(iso) {
    if (!iso) return "-";
    return new Date(iso).toLocaleString("en-GB", {
        timeZone: "Europe/London",
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

export const offerStatusStyles = {
    active: "bg-green-500/20 text-green-600",
    scheduled: "bg-blue-500/20 text-blue-600",
    paused: "bg-surface-3 text-ink-500",
    expired: "bg-red-500/15 text-red-600",
    exhausted: "bg-yellow-500/20 text-yellow-600",
};
