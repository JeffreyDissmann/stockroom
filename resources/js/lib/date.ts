/**
 * Today as a YYYY-MM-DD string in the user's LOCAL timezone — what an
 * <input type="date"> expects. Not toISOString().slice(0, 10): that is UTC
 * and rolls to yesterday during the late-evening hours east of Greenwich.
 */
export function localToday(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}
