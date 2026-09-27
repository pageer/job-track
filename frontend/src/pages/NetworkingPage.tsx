import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { api, ApiError } from '../api';
import type { Company, Contact, Person, PersonDetail } from '../types';
import { formatDate, toDateInputValue } from '../utils';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';
import {
  filterPeople,
  needsFollowUpPeople,
  NETWORKING_VIEWS,
  NETWORKING_VIEW_LABELS,
  sortByLastContact,
  toContactPayload,
  toPersonPayload,
  type ContactForm,
  type NetworkingView,
  type PersonForm,
} from '../networking';

function todayDateKey(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function emptyPersonForm(): PersonForm {
  return {
    name: '',
    email: '',
    linkedInUrl: '',
    notes: '',
    companyId: '',
    newCompanyName: '',
  };
}

function formFromPerson(person: Person): PersonForm {
  return {
    name: person.name,
    email: person.email ?? '',
    linkedInUrl: person.linkedInUrl ?? '',
    notes: person.notes ?? '',
    companyId: person.companyId ? String(person.companyId) : '',
    newCompanyName: '',
  };
}

function emptyContactForm(): ContactForm {
  return { date: todayDateKey(), description: '', needsFollowUp: false };
}

function formFromContact(contact: Contact): ContactForm {
  return {
    date: toDateInputValue(contact.date),
    description: contact.description ?? '',
    needsFollowUp: contact.needsFollowUp,
  };
}

export default function NetworkingPage() {
  const [people, setPeople] = useState<Person[]>([]);
  const [companies, setCompanies] = useState<Company[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [view, setView] = useState<NetworkingView>('all');
  const [query, setQuery] = useState('');

  const [showPersonModal, setShowPersonModal] = useState(false);
  const [editingPerson, setEditingPerson] = useState<Person | null>(null);
  const [personContacts, setPersonContacts] = useState<Contact[]>([]);
  const [personForm, setPersonForm] = useState<PersonForm>(emptyPersonForm());
  const [savingPerson, setSavingPerson] = useState(false);

  const [showContactModal, setShowContactModal] = useState(false);
  const [editingContact, setEditingContact] = useState<Contact | null>(null);
  const [contactForm, setContactForm] =
    useState<ContactForm>(emptyContactForm());
  const [savingContact, setSavingContact] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [peopleData, companiesData] = await Promise.all([
        api.get<Person[]>('/api/people'),
        api.get<Company[]>('/api/companies'),
      ]);
      setPeople(peopleData);
      setCompanies(companiesData);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to load your contacts.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const refreshPersonDetail = useCallback(async (personId: number) => {
    try {
      const detail = await api.get<PersonDetail>(`/api/people/${personId}`);
      setPersonContacts(detail.contacts);
    } catch {
      // The page-level error will surface on the next reload.
    }
  }, []);

  async function openPerson(person: Person | null) {
    setEditingPerson(person);
    setPersonForm(person ? formFromPerson(person) : emptyPersonForm());
    setPersonContacts(person ? [] : []);
    setShowPersonModal(true);
    if (person) {
      await refreshPersonDetail(person.id);
    }
  }

  async function handlePersonSubmit(e: FormEvent) {
    e.preventDefault();
    const name = personForm.name.trim();
    if (!name) {
      return;
    }
    setSavingPerson(true);
    setError(null);
    try {
      const body = toPersonPayload(personForm);
      if (editingPerson) {
        await api.patch(`/api/people/${editingPerson.id}`, body);
      } else {
        await api.post('/api/people', body);
      }
      setShowPersonModal(false);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to save the person.',
      );
    } finally {
      setSavingPerson(false);
    }
  }

  async function handlePersonDelete(person: Person) {
    if (!window.confirm(`Delete contact "${person.name}"?`)) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/people/${person.id}`);
      setShowPersonModal(false);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to delete the person.',
      );
    }
  }

  function openContact(contact: Contact | null) {
    setEditingContact(contact);
    setContactForm(contact ? formFromContact(contact) : emptyContactForm());
    setShowContactModal(true);
  }

  async function handleContactSubmit(e: FormEvent) {
    e.preventDefault();
    if (!editingPerson || !contactForm.date) {
      return;
    }
    setSavingContact(true);
    setError(null);
    try {
      const body = toContactPayload(contactForm);
      const base = `/api/people/${editingPerson.id}/contacts`;
      if (editingContact) {
        await api.patch(`${base}/${editingContact.id}`, body);
      } else {
        await api.post(base, body);
      }
      setShowContactModal(false);
      await load();
      await refreshPersonDetail(editingPerson.id);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to save the contact.',
      );
    } finally {
      setSavingContact(false);
    }
  }

  async function handleContactDelete(contact: Contact) {
    if (!editingPerson) {
      return;
    }
    if (!window.confirm(`Delete this contact from ${editingPerson.name}?`)) {
      return;
    }
    setError(null);
    try {
      await api.delete(
        `/api/people/${editingPerson.id}/contacts/${contact.id}`,
      );
      await load();
      await refreshPersonDetail(editingPerson.id);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to delete the contact.',
      );
    }
  }

  const viewPeople = view === 'followup' ? needsFollowUpPeople(people) : people;
  const filteredPeople = filterPeople(viewPeople, query);
  const listedPeople =
    view === 'recent'
      ? sortByLastContact(filteredPeople)
      : [...filteredPeople].sort((a, b) => a.name.localeCompare(b.name));

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1>Networking</h1>
          <p className="page-subtitle">
            Track the people you are talking to and what you owe them next.
          </p>
        </div>
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => void openPerson(null)}
        >
          Add contact
        </button>
      </div>
      <ErrorBanner message={error} />

      <div className="page-toolbar">
        <div className="filter-bar">
          <div className="btn-group" role="tablist" aria-label="View">
            {NETWORKING_VIEWS.map((item) => (
              <button
                key={item}
                type="button"
                role="tab"
                aria-selected={view === item}
                className={`btn btn-sm ${view === item ? 'btn-primary' : 'btn-ghost'}`}
                onClick={() => setView(item)}
              >
                {NETWORKING_VIEW_LABELS[item]}
              </button>
            ))}
          </div>
        </div>
        <div className="filter-bar">
          <input
            type="search"
            className="input"
            placeholder="Search name, company, email"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
      </div>

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : listedPeople.length === 0 ? (
        <div className="empty-state">
          {people.length === 0 ? (
            <>
              <p>No contacts yet.</p>
              <button
                type="button"
                className="btn btn-primary"
                onClick={() => void openPerson(null)}
              >
                Add your first contact
              </button>
            </>
          ) : (
            <p>No contacts match the selected view or search.</p>
          )}
        </div>
      ) : (
        <ul className="card-list">
          {listedPeople.map((person) => (
            <li key={person.id} className="card">
              <div className="card-title-row">
                <span className="card-title">{person.name}</span>
                <div className="btn-group">
                  {person.needsFollowUp && (
                    <span className="badge badge-no_response">
                      Needs follow-up
                    </span>
                  )}
                  <button
                    type="button"
                    className="btn btn-sm btn-ghost"
                    onClick={() => void openPerson(person)}
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    className="btn btn-sm btn-danger-ghost"
                    onClick={() => void handlePersonDelete(person)}
                  >
                    Delete
                  </button>
                </div>
              </div>
              <div className="card-meta">
                {person.companyName ?? 'No company'}
                {person.email ? ` · ${person.email}` : ''}
              </div>
              <div className="card-dates">
                {person.lastContact ? (
                  <span>
                    Last contact {formatDate(person.lastContact.date)}
                  </span>
                ) : (
                  <span>No contact yet</span>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      {showPersonModal && (
        <Modal
          title={editingPerson ? `Edit ${editingPerson.name}` : 'Add contact'}
          onClose={() => setShowPersonModal(false)}
        >
          <form onSubmit={handlePersonSubmit} className="form">
            <label className="field">
              <span>Name</span>
              <input
                value={personForm.name}
                onChange={(e) =>
                  setPersonForm({ ...personForm, name: e.target.value })
                }
                required
                autoFocus
                placeholder="e.g. Jane Doe"
              />
            </label>
            <label className="field">
              <span>Email (optional)</span>
              <input
                type="email"
                value={personForm.email}
                onChange={(e) =>
                  setPersonForm({ ...personForm, email: e.target.value })
                }
                placeholder="jane@example.com"
              />
            </label>
            <label className="field">
              <span>LinkedIn (optional)</span>
              <input
                type="url"
                value={personForm.linkedInUrl}
                onChange={(e) =>
                  setPersonForm({ ...personForm, linkedInUrl: e.target.value })
                }
                placeholder="https://linkedin.com/in/janedoe"
              />
            </label>
            <label className="field">
              <span>Company</span>
              <select
                value={personForm.companyId}
                onChange={(e) =>
                  setPersonForm({ ...personForm, companyId: e.target.value })
                }
              >
                <option value="">No company</option>
                {companies.map((company) => (
                  <option key={company.id} value={String(company.id)}>
                    {company.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Or add a new company</span>
              <input
                value={personForm.newCompanyName}
                onChange={(e) =>
                  setPersonForm({
                    ...personForm,
                    newCompanyName: e.target.value,
                  })
                }
                placeholder="e.g. Globex (only if not listed above)"
              />
            </label>
            <label className="field">
              <span>Notes (optional)</span>
              <textarea
                rows={3}
                value={personForm.notes}
                onChange={(e) =>
                  setPersonForm({ ...personForm, notes: e.target.value })
                }
                placeholder="How you know them, context, etc."
              />
            </label>
            <div className="form-actions">
              {editingPerson && (
                <button
                  type="button"
                  className="btn btn-danger-ghost"
                  onClick={() => void handlePersonDelete(editingPerson)}
                >
                  Delete
                </button>
              )}
              <button
                type="button"
                className="btn btn-ghost"
                onClick={() => setShowPersonModal(false)}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={savingPerson}
              >
                {savingPerson ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>

          {editingPerson && (
            <section className="modal-section">
              <div className="section-title-row">
                <h3>Contact history</h3>
                <button
                  type="button"
                  className="btn btn-sm btn-ghost"
                  onClick={() => openContact(null)}
                >
                  Add contact
                </button>
              </div>
              {personContacts.length === 0 ? (
                <p className="modal-help">
                  No contacts recorded for {editingPerson.name} yet.
                </p>
              ) : (
                <ul className="card-list">
                  {personContacts.map((contact) => (
                    <li key={contact.id} className="card">
                      <div className="card-title-row">
                        <span className="card-title">
                          {formatDate(contact.date)}
                        </span>
                        <div className="btn-group">
                          {contact.needsFollowUp && (
                            <span className="badge badge-no_response">
                              Follow-up
                            </span>
                          )}
                          <button
                            type="button"
                            className="btn btn-sm btn-ghost"
                            onClick={() => openContact(contact)}
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            className="btn btn-sm btn-danger-ghost"
                            onClick={() => void handleContactDelete(contact)}
                          >
                            Delete
                          </button>
                        </div>
                      </div>
                      {contact.description && (
                        <div className="card-meta">{contact.description}</div>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}
        </Modal>
      )}

      {showContactModal && editingPerson && (
        <Modal
          title={
            editingContact
              ? 'Edit contact'
              : `Add contact for ${editingPerson.name}`
          }
          onClose={() => setShowContactModal(false)}
        >
          <form onSubmit={handleContactSubmit} className="form">
            <label className="field">
              <span>Date</span>
              <input
                type="date"
                value={contactForm.date}
                onChange={(e) =>
                  setContactForm({ ...contactForm, date: e.target.value })
                }
                required
                autoFocus
              />
            </label>
            <label className="field">
              <span>Description (optional)</span>
              <textarea
                rows={3}
                value={contactForm.description}
                onChange={(e) =>
                  setContactForm({
                    ...contactForm,
                    description: e.target.value,
                  })
                }
                placeholder="e.g. Coffee chat, follow-up sent"
              />
            </label>
            <label className="check-field">
              <input
                type="checkbox"
                checked={contactForm.needsFollowUp}
                onChange={(e) =>
                  setContactForm({
                    ...contactForm,
                    needsFollowUp: e.target.checked,
                  })
                }
              />
              <span>This needs a follow-up</span>
            </label>
            <div className="form-actions">
              <button
                type="button"
                className="btn btn-ghost"
                onClick={() => setShowContactModal(false)}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={savingContact}
              >
                {savingContact ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
