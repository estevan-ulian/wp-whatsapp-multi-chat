/* ------------------------------------------------------------------
   Business hours — computed in the visitor's browser against the site's
   timezone, so the status stays right on cached pages and changes on time
   while the page is open.
------------------------------------------------------------------ */

export type DayKey = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';
export interface DayHours { enabled: 0 | 1; open: string; close: string }
export type WeeklyHours = Record<DayKey, DayHours>;
export type Availability =
  | { mode: 'always_online' }
  | { mode: 'always_offline' }
  | { mode: 'schedule'; hours: WeeklyHours };

export interface Status {
  online: boolean;
  /** The status could not be computed; shown as online so contact stays possible. */
  unknown?: true;
  /** Next opening: dayOffset 0 = today … 7 = same weekday next week. */
  next?: { dayOffset: number; day: DayKey; time: string };
}

export interface StatusTexts { online: string; offline: string; back: string; today: string; tomorrow: string }

const DAYS: DayKey[] = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
const INTL_WEEKDAYS: Record<string, DayKey> = {
  Mon: 'mon', Tue: 'tue', Wed: 'wed', Thu: 'thu', Fri: 'fri', Sat: 'sat', Sun: 'sun',
};
const FIXED_OFFSET = /^([+-])(\d{2}):(\d{2})$/;

function toMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number);
  return h * 60 + m;
}

/** Day and minutes since midnight in the site's timezone. Throws on an invalid timezone. */
export function siteNow(timezone: string, instant: Date): { day: DayKey; minutes: number } {
  // WordPress allows a manual "UTC-3" setting, sent as "-03:00": a fixed offset without DST.
  const offset = FIXED_OFFSET.exec(timezone);
  if (offset) {
    const sign = offset[1] === '-' ? -1 : 1;
    const shifted = new Date(instant.getTime() + sign * (Number(offset[2]) * 60 + Number(offset[3])) * 60000);
    return {
      day: DAYS[(shifted.getUTCDay() + 6) % 7],
      minutes: shifted.getUTCHours() * 60 + shifted.getUTCMinutes(),
    };
  }

  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: timezone,
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(instant);

  const get = (type: string) => parts.find(p => p.type === type)?.value ?? '';
  const day = INTL_WEEKDAYS[get('weekday')];
  if (!day) throw new Error(`Unexpected weekday for timezone ${timezone}`);

  return { day, minutes: Number(get('hour')) * 60 + Number(get('minute')) };
}

export function getStatus(availability: Availability, timezone: string, instant: Date): Status {
  if (availability.mode === 'always_online') return { online: true };
  if (availability.mode === 'always_offline') return { online: false };

  let now: { day: DayKey; minutes: number };
  try {
    now = siteNow(timezone, instant);
  } catch {
    return { online: true, unknown: true };
  }

  const { hours } = availability;
  const today = hours[now.day];
  if (today.enabled && toMinutes(today.open) <= now.minutes && now.minutes < toMinutes(today.close)) {
    return { online: true };
  }

  const todayIndex = DAYS.indexOf(now.day);
  for (let dayOffset = 0; dayOffset <= 7; dayOffset++) {
    const day = DAYS[(todayIndex + dayOffset) % 7];
    const candidate = hours[day];
    if (!candidate.enabled) continue;
    if (dayOffset === 0 && now.minutes >= toMinutes(candidate.open)) continue;
    return { online: false, next: { dayOffset, day, time: candidate.open } };
  }

  return { online: false };
}

/** Status line under the account name; null keeps the line hidden, as before business hours existed. */
export function formatStatus(
  availability: Availability,
  status: Status,
  texts: StatusTexts,
  weekdays: Record<DayKey, string>,
): string | null {
  if (availability.mode === 'always_online' || status.unknown) return null;
  if (status.online) return texts.online;
  if (!status.next) return texts.offline;

  const { dayOffset, day, time } = status.next;
  const dayLabel = dayOffset === 0 ? texts.today : dayOffset === 1 ? texts.tomorrow : weekdays[day];
  return texts.back.replace('{dia}', dayLabel).replace('{hora}', time);
}

/** Online accounts first; the admin-defined order is kept inside each group. */
export function sortByStatus<T>(entries: Array<{ item: T; status: Status }>): Array<{ item: T; status: Status }> {
  return [...entries.filter(e => e.status.online), ...entries.filter(e => !e.status.online)];
}

export function allOffline(statuses: Status[]): boolean {
  return statuses.length > 0 && statuses.every(s => !s.online);
}
