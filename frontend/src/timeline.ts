import type { JobDetail } from './types';

export interface TimelineEntry {
  key: string;
  kind: 'note' | 'interview';
  date: string;
  title: string;
  body: string | null;
}

export function buildTimeline(job: JobDetail): TimelineEntry[] {
  const entries: TimelineEntry[] = [];

  for (const note of job.notes) {
    entries.push({
      key: `note-${note.id}`,
      kind: 'note',
      date: note.createdAt,
      title: 'Job note',
      body: note.content,
    });
  }

  for (const interview of job.application?.interviews ?? []) {
    entries.push({
      key: `interview-${interview.id}`,
      kind: 'interview',
      date: interview.date,
      title:
        interview.interviewers.length > 0
          ? `Interview with ${interview.interviewers.join(', ')}`
          : 'Interview',
      body: interview.notes,
    });
  }

  return entries.sort(
    (a, b) => new Date(a.date).getTime() - new Date(b.date).getTime(),
  );
}
