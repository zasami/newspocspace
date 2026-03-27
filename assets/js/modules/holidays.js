/**
 * Swiss public holidays — National + Canton de Genève (GE)
 * Includes moveable feasts (Easter-based)
 */

// Compute Easter Sunday using Anonymous Gregorian algorithm
function easterSunday(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;
    return new Date(year, month - 1, day);
}

function addDays(date, n) {
    const d = new Date(date);
    d.setDate(d.getDate() + n);
    return d;
}

function fmt(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/**
 * Returns a Map of 'YYYY-MM-DD' → { name, type, icon }
 * type: 'national' | 'cantonal'
 */
export function getHolidays(year) {
    const easter = easterSunday(year);
    const holidays = new Map();

    function add(date, name, type, icon) {
        holidays.set(fmt(date), { name, type, icon });
    }

    // ── Jours fériés nationaux ──
    add(new Date(year, 0, 1),   'Nouvel An',             'national', '🎆');
    add(new Date(year, 7, 1),   'Fête nationale suisse', 'national', '🇨🇭');
    add(new Date(year, 11, 25), 'Noël',                  'national', '🎄');

    // ── Fêtes mobiles (basées sur Pâques) ──
    add(addDays(easter, -2),  'Vendredi Saint',       'cantonal', '✝');
    add(addDays(easter, 1),   'Lundi de Pâques',     'national', '🐣');
    add(addDays(easter, 39),  'Ascension',            'national', '☁');
    add(addDays(easter, 50),  'Lundi de Pentecôte',   'national', '🕊');

    // ── Jours fériés cantonaux — Genève ──
    add(new Date(year, 0, 2),   'Lendemain de Nouvel An', 'cantonal', '🎆');
    add(new Date(year, 11, 31), 'Restauration de la République', 'cantonal', '🏛');

    // Jeûne genevois — jeudi après le premier dimanche de septembre
    const sept1 = new Date(year, 8, 1);
    let firstSundaySept = new Date(sept1);
    while (firstSundaySept.getDay() !== 0) {
        firstSundaySept.setDate(firstSundaySept.getDate() + 1);
    }
    add(addDays(firstSundaySept, 4), 'Jeûne genevois', 'cantonal', '🙏');

    return holidays;
}

/**
 * Get holidays within a date range for display
 */
export function getHolidaysInRange(days) {
    if (!days.length) return new Map();
    const years = new Set(days.map(d => d.getFullYear()));
    const all = new Map();
    for (const y of years) {
        for (const [k, v] of getHolidays(y)) all.set(k, v);
    }
    return all;
}

/**
 * Get upcoming holidays (next 60 days) for banner
 */
export function getUpcomingHolidays(fromDate, maxDays = 60) {
    const result = [];
    const years = new Set([fromDate.getFullYear(), fromDate.getFullYear() + 1]);
    const all = new Map();
    for (const y of years) {
        for (const [k, v] of getHolidays(y)) all.set(k, v);
    }

    for (const [dateStr, info] of all) {
        const [y, m, d] = dateStr.split('-').map(Number);
        const hDate = new Date(y, m - 1, d);
        const diff = Math.floor((hDate - fromDate) / 86400000);
        if (diff >= 0 && diff <= maxDays) {
            result.push({ date: dateStr, ...info, daysUntil: diff });
        }
    }

    return result.sort((a, b) => a.daysUntil - b.daysUntil);
}
