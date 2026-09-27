import { describe, expect, it } from 'vitest';
import type { Contact, Person } from './types';
import {
  filterPeople,
  needsFollowUpPeople,
  NETWORKING_VIEW_LABELS,
  sortByLastContact,
  toContactPayload,
  toPersonPayload,
  type ContactForm,
  type PersonForm,
} from './networking';

function person(overrides: Partial<Person>): Person {
  return {
    id: 1,
    name: 'Jane Doe',
    email: null,
    linkedInUrl: null,
    notes: null,
    companyId: null,
    companyName: null,
    createdAt: '2026-09-01T00:00:00Z',
    lastContact: null,
    needsFollowUp: false,
    ...overrides,
  };
}

function contact(date: string): Contact {
  return {
    id: 1,
    date,
    description: null,
    needsFollowUp: false,
    personId: 1,
    createdAt: '2026-09-01T00:00:00Z',
  };
}

describe('NETWORKING_VIEW_LABELS', () => {
  it('labels every view', () => {
    expect(NETWORKING_VIEW_LABELS.all).toBe('All contacts');
    expect(NETWORKING_VIEW_LABELS.recent).toBe('Recent activity');
    expect(NETWORKING_VIEW_LABELS.followup).toBe('Needs follow-up');
  });
});

describe('filterPeople', () => {
  const people = [
    person({ id: 1, name: 'Jane Doe', companyName: 'Acme Inc' }),
    person({ id: 2, name: 'John Smith', email: 'john@example.com' }),
    person({ id: 3, name: 'Bob Jones' }),
  ];

  it('returns everything for an empty query', () => {
    expect(filterPeople(people, '')).toHaveLength(3);
    expect(filterPeople(people, '   ')).toHaveLength(3);
  });

  it('matches by name case-insensitively', () => {
    expect(filterPeople(people, 'jane')).toHaveLength(1);
    expect(filterPeople(people, 'JOHN')).toHaveLength(1);
  });

  it('matches by company name', () => {
    const result = filterPeople(people, 'acme');
    expect(result.map((p) => p.id)).toEqual([1]);
  });

  it('matches by email', () => {
    const result = filterPeople(people, 'john@example.com');
    expect(result.map((p) => p.id)).toEqual([2]);
  });

  it('returns nothing when nothing matches', () => {
    expect(filterPeople(people, 'zzz')).toHaveLength(0);
  });
});

describe('needsFollowUpPeople', () => {
  it('keeps only people who need a follow-up', () => {
    const people = [
      person({ id: 1, needsFollowUp: true }),
      person({ id: 2, needsFollowUp: false }),
      person({ id: 3, needsFollowUp: true }),
    ];
    expect(needsFollowUpPeople(people).map((p) => p.id)).toEqual([1, 3]);
  });
});

describe('sortByLastContact', () => {
  it('sorts most recent first and leaves no-contact people at the end', () => {
    const people = [
      person({ id: 1, lastContact: null }),
      person({ id: 2, lastContact: contact('2026-09-01T00:00:00Z') }),
      person({ id: 3, lastContact: contact('2026-09-20T00:00:00Z') }),
      person({ id: 4, lastContact: null }),
    ];
    expect(sortByLastContact(people).map((p) => p.id)).toEqual([3, 2, 1, 4]);
  });

  it('does not mutate the input array', () => {
    const people = [person({ id: 1 }), person({ id: 2 })];
    const sorted = sortByLastContact(people);
    expect(people.map((p) => p.id)).toEqual([1, 2]);
    expect(sorted).not.toBe(people);
  });
});

describe('toPersonPayload', () => {
  function form(overrides: Partial<PersonForm> = {}): PersonForm {
    return {
      name: 'Jane Doe',
      email: '',
      linkedInUrl: '',
      notes: '',
      companyId: '',
      newCompanyName: '',
      ...overrides,
    };
  }

  it('trims and nulls optional fields', () => {
    const payload = toPersonPayload(
      form({ email: '  jane@example.com  ', notes: '  hi  ' }),
    );
    expect(payload).toEqual({
      name: 'Jane Doe',
      email: 'jane@example.com',
      linkedInUrl: null,
      notes: 'hi',
    });
  });

  it('sends an existing company by id when selected', () => {
    const payload = toPersonPayload(form({ companyId: '7' }));
    expect(payload.companyId).toBe(7);
    expect(payload.companyName).toBeUndefined();
  });

  it('prioritizes a typed new company name over a selected one', () => {
    const payload = toPersonPayload(
      form({ companyId: '7', newCompanyName: '  Globex  ' }),
    );
    expect(payload.companyName).toBe('Globex');
    expect(payload.companyId).toBeUndefined();
  });

  it('omits the company entirely when neither is set', () => {
    const payload = toPersonPayload(form());
    expect(payload.companyId).toBeUndefined();
    expect(payload.companyName).toBeUndefined();
  });
});

describe('toContactPayload', () => {
  it('builds a payload from the form', () => {
    const form: ContactForm = {
      date: '2026-09-27',
      description: '  Coffee chat  ',
      needsFollowUp: true,
    };
    expect(toContactPayload(form)).toEqual({
      date: '2026-09-27',
      description: 'Coffee chat',
      needsFollowUp: true,
    });
  });

  it('nulls an empty description', () => {
    expect(
      toContactPayload({
        date: '2026-09-27',
        description: '',
        needsFollowUp: false,
      }).description,
    ).toBeNull();
  });
});
