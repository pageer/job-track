import type { Person } from './types';

export type NetworkingView = 'all' | 'recent' | 'followup';

export const NETWORKING_VIEWS: NetworkingView[] = ['all', 'recent', 'followup'];

export const NETWORKING_VIEW_LABELS: Record<NetworkingView, string> = {
  all: 'All contacts',
  recent: 'Recent activity',
  followup: 'Needs follow-up',
};

export function filterPeople(people: Person[], query: string): Person[] {
  const q = query.trim().toLowerCase();
  if (!q) {
    return people;
  }
  return people.filter(
    (person) =>
      person.name.toLowerCase().includes(q) ||
      (person.companyName ?? '').toLowerCase().includes(q) ||
      (person.email ?? '').toLowerCase().includes(q),
  );
}

export function needsFollowUpPeople(people: Person[]): Person[] {
  return people.filter((person) => person.needsFollowUp);
}

/** Most recently contacted first; people with no recorded contact come last. */
export function sortByLastContact(people: Person[]): Person[] {
  return [...people].sort((a, b) => {
    const timeA = a.lastContact ? Date.parse(a.lastContact.date) : -Infinity;
    const timeB = b.lastContact ? Date.parse(b.lastContact.date) : -Infinity;
    return timeB - timeA;
  });
}

export interface PersonForm {
  name: string;
  email: string;
  linkedInUrl: string;
  notes: string;
  companyId: string;
  newCompanyName: string;
}

export function toPersonPayload(form: PersonForm): {
  name: string;
  email: string | null;
  linkedInUrl: string | null;
  notes: string | null;
  companyId?: number;
  companyName?: string;
} {
  const payload: {
    name: string;
    email: string | null;
    linkedInUrl: string | null;
    notes: string | null;
  } = {
    name: form.name.trim(),
    email: form.email.trim() ? form.email.trim() : null,
    linkedInUrl: form.linkedInUrl.trim() ? form.linkedInUrl.trim() : null,
    notes: form.notes.trim() ? form.notes.trim() : null,
  };

  const companyName = form.newCompanyName.trim();
  if (companyName) {
    return { ...payload, companyName };
  }
  if (form.companyId) {
    return { ...payload, companyId: Number(form.companyId) };
  }

  return payload;
}

export interface ContactForm {
  date: string;
  description: string;
  needsFollowUp: boolean;
}

export function toContactPayload(form: ContactForm): {
  date: string;
  description: string | null;
  needsFollowUp: boolean;
} {
  return {
    date: form.date,
    description: form.description.trim() ? form.description.trim() : null,
    needsFollowUp: form.needsFollowUp,
  };
}
