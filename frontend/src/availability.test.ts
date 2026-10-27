import { describe, it, expect } from 'vitest';
import {
  siteNow, getStatus, formatStatus, sortByStatus, allOffline,
  type Availability, type DayKey, type Status, type WeeklyHours,
} from './availability';

const SP = 'America/Sao_Paulo';
const MON = '2026-10-26'; // Monday
const TUE = '2026-10-27';
const FRI = '2026-10-30';

function week(overrides: Partial<WeeklyHours> = {}): WeeklyHours {
  const open = { enabled: 1 as const, open: '08:00', close: '18:00' };
  const closed = { enabled: 0 as const, open: '08:00', close: '18:00' };
  return {
    mon: open, tue: open, wed: open, thu: open, fri: open, sat: closed, sun: closed,
    ...overrides,
  };
}

const schedule = (hours: WeeklyHours = week()): Availability => ({ mode: 'schedule', hours });
const at = (iso: string) => new Date(iso);

const texts = {
  online: 'Online agora',
  offline: 'Offline',
  back: 'Volta {dia} às {hora}',
  today: 'hoje',
  tomorrow: 'amanhã',
};
const weekdays: Record<DayKey, string> = {
  mon: 'seg', tue: 'ter', wed: 'qua', thu: 'qui', fri: 'sex', sat: 'sáb', sun: 'dom',
};

describe('boundaries (open ≤ now < close)', () => {
  it('is offline one minute before opening, with today as next opening', () => {
    expect(getStatus(schedule(), SP, at(`${MON}T10:59:00Z`)))
      .toEqual({ online: false, next: { dayOffset: 0, day: 'mon', time: '08:00' } });
  });

  it('is online at the opening minute', () => {
    expect(getStatus(schedule(), SP, at(`${MON}T11:00:00Z`)).online).toBe(true);
  });

  it('is online one minute before closing', () => {
    expect(getStatus(schedule(), SP, at(`${MON}T20:59:00Z`)).online).toBe(true);
  });

  it('is offline at the closing minute', () => {
    expect(getStatus(schedule(), SP, at(`${MON}T21:00:00Z`)).online).toBe(false);
  });
});

describe('next opening', () => {
  it('skips the closed weekend after Friday closing', () => {
    expect(getStatus(schedule(), SP, at(`${FRI}T21:00:00Z`)).next)
      .toEqual({ dayOffset: 3, day: 'mon', time: '08:00' });
  });

  it('is tomorrow after closing on a weekday', () => {
    expect(getStatus(schedule(), SP, at(`${TUE}T21:00:00Z`)).next)
      .toEqual({ dayOffset: 1, day: 'wed', time: '08:00' });
  });

  it('is the same weekday next week when only that day opens', () => {
    const closed = { enabled: 0 as const, open: '08:00', close: '18:00' };
    const onlyMonday = week({ tue: closed, wed: closed, thu: closed, fri: closed });
    expect(getStatus(schedule(onlyMonday), SP, at(`${MON}T21:00:00Z`)).next)
      .toEqual({ dayOffset: 7, day: 'mon', time: '08:00' });
  });

  it('has no next opening when every day is closed', () => {
    const closed = { enabled: 0 as const, open: '08:00', close: '18:00' };
    const allClosed = week({ mon: closed, tue: closed, wed: closed, thu: closed, fri: closed });
    expect(getStatus(schedule(allClosed), SP, at(`${MON}T12:00:00Z`))).toEqual({ online: false });
  });
});

describe('siteNow uses the site timezone, never the visitor\'s', () => {
  // The test script runs with TZ=Europe/Lisbon: an implementation reading the
  // process timezone (getHours, getDay) would return Lisbon time here.
  it('reads São Paulo wall-clock time from a Lisbon process', () => {
    expect(siteNow(SP, at(`${MON}T11:00:00Z`))).toEqual({ day: 'mon', minutes: 480 });
  });

  it('follows daylight saving time in New York', () => {
    expect(siteNow('America/New_York', at('2026-03-08T13:30:00Z'))).toEqual({ day: 'sun', minutes: 570 });
    expect(siteNow('America/New_York', at('2026-03-07T14:30:00Z'))).toEqual({ day: 'sat', minutes: 570 });
  });

  it('supports fixed offsets and UTC', () => {
    expect(siteNow('-03:00', at(`${MON}T11:00:00Z`))).toEqual({ day: 'mon', minutes: 480 });
    expect(siteNow('+05:30', at(`${MON}T03:00:00Z`))).toEqual({ day: 'mon', minutes: 510 });
    expect(siteNow('UTC', at(`${MON}T08:00:00Z`))).toEqual({ day: 'mon', minutes: 480 });
  });

  it('throws on an invalid timezone', () => {
    expect(() => siteNow('Not/AZone', at(`${MON}T11:00:00Z`))).toThrow();
  });
});

describe('fallback', () => {
  it('treats an invalid timezone as online with no status text', () => {
    const status = getStatus(schedule(), 'Not/AZone', at(`${MON}T22:00:00Z`));
    expect(status).toEqual({ online: true, unknown: true });
    expect(formatStatus(schedule(), status, texts, weekdays)).toBeNull();
  });
});

describe('fixed modes', () => {
  it('always online has no status text (same as before business hours existed)', () => {
    const availability: Availability = { mode: 'always_online' };
    const status = getStatus(availability, SP, at(`${MON}T03:00:00Z`));
    expect(status).toEqual({ online: true });
    expect(formatStatus(availability, status, texts, weekdays)).toBeNull();
  });

  it('always offline shows "Offline" with no next opening', () => {
    const availability: Availability = { mode: 'always_offline' };
    const status = getStatus(availability, SP, at(`${MON}T12:00:00Z`));
    expect(status).toEqual({ online: false });
    expect(formatStatus(availability, status, texts, weekdays)).toBe('Offline');
  });
});

describe('formatStatus', () => {
  const next = (dayOffset: number, day: DayKey): Status => ({ online: false, next: { dayOffset, day, time: '08:00' } });

  it('shows online text inside business hours', () => {
    expect(formatStatus(schedule(), { online: true }, texts, weekdays)).toBe('Online agora');
  });

  it('uses "hoje", "amanhã" and the weekday abbreviation', () => {
    expect(formatStatus(schedule(), next(0, 'mon'), texts, weekdays)).toBe('Volta hoje às 08:00');
    expect(formatStatus(schedule(), next(1, 'tue'), texts, weekdays)).toBe('Volta amanhã às 08:00');
    expect(formatStatus(schedule(), next(3, 'mon'), texts, weekdays)).toBe('Volta seg às 08:00');
  });

  it('falls back to "Offline" when there is no next opening', () => {
    expect(formatStatus(schedule(), { online: false }, texts, weekdays)).toBe('Offline');
  });
});

describe('sortByStatus', () => {
  it('puts online first and keeps the original order within each group', () => {
    const on: Status = { online: true };
    const off: Status = { online: false };
    const sorted = sortByStatus([
      { item: 'A', status: off },
      { item: 'B', status: on },
      { item: 'C', status: off },
      { item: 'D', status: on },
    ]);
    expect(sorted.map(e => e.item)).toEqual(['B', 'D', 'A', 'C']);
  });
});

describe('allOffline', () => {
  it('is false for an empty list', () => {
    expect(allOffline([])).toBe(false);
  });

  it('is true only when every account is offline', () => {
    expect(allOffline([{ online: false }, { online: false }])).toBe(true);
    expect(allOffline([{ online: false }, { online: true }])).toBe(false);
  });
});
