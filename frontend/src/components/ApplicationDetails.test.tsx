import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import type { Application } from '../types';
import ApplicationDetails from './ApplicationDetails';

const baseApplication: Application = {
  id: 1,
  resumeKind: null,
  resumeFileName: null,
  resumeMimeType: null,
  resumeFileSize: null,
  resumeLinkUrl: null,
  coverLetterKind: null,
  coverLetterHtml: null,
  coverLetterLinkUrl: null,
  notes: null,
  actionDate: null,
  createdAt: '2026-09-01T10:00:00Z',
  jobId: 7,
  jobTitle: 'Engineer',
  jobCompany: 'Acme',
  interviews: [],
};

function renderDetails(
  overrides: Partial<Application> = {},
  showFullLetter = false,
) {
  const onClearResume = vi.fn();
  const onToggleLetter = vi.fn();
  render(
    <ApplicationDetails
      application={{ ...baseApplication, ...overrides }}
      showFullLetter={showFullLetter}
      onToggleLetter={onToggleLetter}
      onClearResume={onClearResume}
    />,
  );
  return { onClearResume, onToggleLetter };
}

describe('ApplicationDetails', () => {
  it('shows a link cover letter in a stacked row without a toggle', () => {
    renderDetails({
      coverLetterKind: 'link',
      coverLetterLinkUrl: 'https://example.com/letter.pdf',
    });

    const link = screen.getByRole('link', {
      name: 'https://example.com/letter.pdf',
    });
    expect(link).toHaveClass('break-word');
    expect(link.closest('.detail-row')).toHaveClass('detail-row-stack');
    expect(
      screen.queryByRole('button', { name: 'Show more' }),
    ).not.toBeInTheDocument();
  });

  it('prefers a stored link even when empty cover letter HTML is present', () => {
    renderDetails({
      coverLetterKind: null,
      coverLetterLinkUrl: 'https://example.com/letter.pdf',
      coverLetterHtml: '<p></p>',
    });

    expect(
      screen.getByRole('link', { name: 'https://example.com/letter.pdf' }),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', { name: 'Show more' }),
    ).not.toBeInTheDocument();
  });

  it('does not render an empty cover letter preview or toggle', () => {
    renderDetails({
      coverLetterKind: 'text',
      coverLetterHtml: '<p>&nbsp;</p>',
    });

    expect(screen.queryByText('Cover letter')).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', { name: 'Show more' }),
    ).not.toBeInTheDocument();
  });

  it('renders cover letter text with a working toggle', () => {
    const { onToggleLetter } = renderDetails({
      coverLetterKind: 'text',
      coverLetterHtml: '<p>Hello there</p>',
    });

    expect(screen.getByText('Hello there')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Show more' }));
    expect(onToggleLetter).toHaveBeenCalledTimes(1);
  });

  it('shows "Show less" in the expanded state', () => {
    renderDetails(
      { coverLetterKind: 'text', coverLetterHtml: '<p>Hello there</p>' },
      true,
    );

    expect(
      screen.getByRole('button', { name: 'Show less' }),
    ).toBeInTheDocument();
  });

  it('renders a file resume with a clear action', () => {
    const { onClearResume } = renderDetails({
      resumeKind: 'file',
      resumeFileName: 'cv.pdf',
      resumeMimeType: 'application/pdf',
      resumeFileSize: 2048,
    });

    expect(screen.getByRole('link', { name: 'cv.pdf' })).toHaveAttribute(
      'href',
      '/api/applications/1/resume/download',
    );
    fireEvent.click(screen.getByRole('button', { name: 'Clear' }));
    expect(onClearResume).toHaveBeenCalledTimes(1);
  });

  it('renders notes and an empty resume state', () => {
    renderDetails({ notes: 'Follow up on Monday.' });

    expect(screen.getByText('None')).toBeInTheDocument();
    expect(screen.getByText('Follow up on Monday.')).toBeInTheDocument();
  });
});
