import { describe, expect, it } from 'vitest';
import {
  splitInterviewers,
  toInterviewPayload,
  toReviewInterview,
} from './aiImport';

describe('aiImport helpers', () => {
  it('maps an extracted interview into editable form values', () => {
    const review = toReviewInterview({
      date: '2026-09-20T15:00:00',
      interviewers: ['Jane Doe', 'Bob Smith'],
      notes: 'Video call',
    });

    expect(review).toEqual({
      date: '2026-09-20T15:00',
      interviewers: 'Jane Doe, Bob Smith',
      notes: 'Video call',
    });
  });

  it('returns empty form values when no interview was extracted', () => {
    expect(toReviewInterview(null)).toEqual({
      date: '',
      interviewers: '',
      notes: '',
    });
  });

  it('maps review values back into an interview payload', () => {
    const payload = toInterviewPayload({
      date: '2026-09-20T15:00',
      interviewers: ' Jane Doe , Bob Smith, ',
      notes: '  Zoom  ',
    });

    expect(payload).toEqual({
      date: '2026-09-20T15:00',
      interviewers: ['Jane Doe', 'Bob Smith'],
      notes: 'Zoom',
    });
  });

  it('turns blank notes into null and ignores empty interviewer entries', () => {
    const payload = toInterviewPayload({
      date: '2026-09-20T15:00',
      interviewers: '',
      notes: '   ',
    });

    expect(payload.notes).toBeNull();
    expect(payload.interviewers).toEqual([]);
  });

  it('splits comma separated interviewer lists', () => {
    expect(splitInterviewers('A, B, C,')).toEqual(['A', 'B', 'C']);
  });
});
