import { describe, expect, it } from 'vitest';
import { buildTimeline } from './timeline';
import type { JobDetail } from './types';

function makeJob(partial: Partial<JobDetail>): JobDetail {
  return {
    id: 1,
    title: 'Engineer',
    company: 'Acme',
    status: 'in_progress',
    jobSearchId: 1,
    actionDate: null,
    createdAt: '2026-09-01T10:00:00+00:00',
    descriptionHtml: null,
    descriptionUrl: null,
    application: null,
    notes: [],
    ...partial,
  };
}

describe('buildTimeline', () => {
  it('combines notes and interviews sorted chronologically', () => {
    const job = makeJob({
      notes: [
        {
          id: 1,
          content: 'Applied via referral',
          createdAt: '2026-09-02T09:00:00+00:00',
        },
        {
          id: 2,
          content: 'Received offer',
          createdAt: '2026-09-10T11:00:00+00:00',
        },
      ],
      application: {
        id: 10,
        resumeKind: null,
        resumeFileName: null,
        resumeMimeType: null,
        resumeFileSize: null,
        resumeLinkUrl: null,
        coverLetterHtml: null,
        notes: null,
        actionDate: '2026-09-02',
        createdAt: '2026-09-02T09:05:00+00:00',
        jobId: 1,
        jobTitle: 'Engineer',
        jobCompany: 'Acme',
        interviews: [
          {
            id: 5,
            date: '2026-09-05T15:00:00+00:00',
            interviewers: ['Jane'],
            notes: 'Went well',
            createdAt: '2026-09-02T10:00:00+00:00',
          },
        ],
      },
    });

    const timeline = buildTimeline(job);

    expect(timeline.map((e) => e.key)).toEqual([
      'note-1',
      'interview-5',
      'note-2',
    ]);
    expect(timeline[1].kind).toBe('interview');
    expect(timeline[1].title).toBe('Interview with Jane');
    expect(timeline[1].body).toBe('Went well');
  });

  it('returns empty when no notes or interviews exist', () => {
    expect(buildTimeline(makeJob({}))).toEqual([]);
  });

  it('handles an interview without interviewers or notes', () => {
    const job = makeJob({
      application: {
        id: 10,
        resumeKind: null,
        resumeFileName: null,
        resumeMimeType: null,
        resumeFileSize: null,
        resumeLinkUrl: null,
        coverLetterHtml: null,
        notes: null,
        actionDate: null,
        createdAt: '2026-09-02T09:05:00+00:00',
        jobId: 1,
        jobTitle: 'Engineer',
        jobCompany: 'Acme',
        interviews: [
          {
            id: 5,
            date: '2026-09-05T15:00:00+00:00',
            interviewers: [],
            notes: null,
            createdAt: '2026-09-02T10:00:00+00:00',
          },
        ],
      },
    });

    const timeline = buildTimeline(job);

    expect(timeline).toHaveLength(1);
    expect(timeline[0].title).toBe('Interview');
    expect(timeline[0].body).toBeNull();
  });
});
