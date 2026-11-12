import type { Application } from '../types';
import {
  formatDate,
  formatFileSize,
  hasVisibleHtmlText,
  onlyLinkHref,
} from '../utils';

interface ApplicationDetailsProps {
  application: Application;
  showFullLetter: boolean;
  onToggleLetter: () => void;
  onClearResume: () => void;
}

export default function ApplicationDetails({
  application,
  showFullLetter,
  onToggleLetter,
  onClearResume,
}: ApplicationDetailsProps) {
  const coverLetterLink =
    application.coverLetterLinkUrl ||
    onlyLinkHref(application.coverLetterHtml) ||
    '';
  const coverLetterHasText = hasVisibleHtmlText(application.coverLetterHtml);

  return (
    <div className="application-detail">
      {application.actionDate && (
        <div className="detail-row">
          <span className="detail-label">Action date</span>
          <span>{formatDate(application.actionDate)}</span>
        </div>
      )}
      <div className="detail-row detail-row-stack">
        <span className="detail-label">Resume</span>
        <span className="detail-value">
          {application.resumeKind === 'file' ? (
            <>
              <a
                className="break-word"
                href={`/api/applications/${application.id}/resume/download`}
                target="_blank"
                rel="noreferrer"
              >
                {application.resumeFileName}
              </a>{' '}
              <small>
                ({formatFileSize(application.resumeFileSize)},{' '}
                {application.resumeMimeType})
              </small>
            </>
          ) : application.resumeKind === 'link' ? (
            <a
              className="break-word"
              href={application.resumeLinkUrl ?? '#'}
              target="_blank"
              rel="noreferrer"
            >
              {application.resumeLinkUrl}
            </a>
          ) : (
            <em>None</em>
          )}
          {application.resumeKind && (
            <>
              {' '}
              <button
                type="button"
                className="btn btn-sm btn-ghost"
                onClick={onClearResume}
              >
                Clear
              </button>
            </>
          )}
        </span>
      </div>
      {coverLetterLink ? (
        <div className="detail-row detail-row-stack">
          <span className="detail-label">Cover letter</span>
          <span className="detail-value">
            <a
              className="break-word"
              href={coverLetterLink}
              target="_blank"
              rel="noreferrer"
            >
              {coverLetterLink}
            </a>
          </span>
        </div>
      ) : coverLetterHasText ? (
        <div className="detail-row detail-row-stack">
          <span className="detail-label">Cover letter</span>
          <div>
            <div
              className={`collapsible-preview ${showFullLetter ? 'collapsible-expanded' : 'collapsible-collapsed'}`}
              dangerouslySetInnerHTML={{
                __html: application.coverLetterHtml ?? '',
              }}
            />
            <button
              type="button"
              className="btn btn-sm btn-ghost collapsible-toggle"
              onClick={onToggleLetter}
            >
              {showFullLetter ? 'Show less' : 'Show more'}
            </button>
          </div>
        </div>
      ) : null}
      {application.notes && (
        <div className="detail-row">
          <span className="detail-label">Notes</span>
          <span className="pre-wrap">{application.notes}</span>
        </div>
      )}
    </div>
  );
}
