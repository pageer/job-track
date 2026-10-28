import { useCallback, useEffect, useState } from 'react';
import { api, ApiError } from '../api';
import type { ReportSummary } from '../types';
import { compactNumber, weeklyChartPoints } from '../reports';
import ErrorBanner from '../components/ErrorBanner';
import WeeklyBarChart from '../components/WeeklyBarChart';

export default function ReportsPage() {
  const [report, setReport] = useState<ReportSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<ReportSummary>('/api/reports');
      setReport(data);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to load reports.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const activityPoints = report
    ? weeklyChartPoints(report.weekly, 'activities')
    : [];
  const hourPoints = report ? weeklyChartPoints(report.weekly, 'hours') : [];

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1>Reports</h1>
          <p className="page-subtitle">Your job search activity at a glance.</p>
        </div>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : report ? (
        <>
          <section className="report-stats" aria-label="Job search totals">
            <div className="report-stat">
              <span className="report-stat-label">Jobs applied to</span>
              <span className="report-stat-value">
                {report.totalApplications}
              </span>
            </div>
            <div className="report-stat">
              <span className="report-stat-label">
                Applications with interviews
              </span>
              <span className="report-stat-value">
                {report.applicationsWithInterview}
              </span>
            </div>
            <div className="report-stat">
              <span className="report-stat-label">Activities recorded</span>
              <span className="report-stat-value">
                {report.totalActivities}
              </span>
            </div>
            <div className="report-stat">
              <span className="report-stat-label">Hours logged</span>
              <span className="report-stat-value">
                {compactNumber(report.totalHours)}
                <span className="report-stat-unit">h</span>
              </span>
            </div>
          </section>

          <section className="card report-chart">
            <div className="report-chart-header">
              <h2>Activities by week</h2>
              <span className="report-chart-total">
                {report.totalActivities} total
              </span>
            </div>
            <WeeklyBarChart
              points={activityPoints}
              ariaLabel="Weekly activity counts"
              emptyMessage="No activities logged yet."
            />
          </section>

          <section className="card report-chart">
            <div className="report-chart-header">
              <h2>Hours by week</h2>
              <span className="report-chart-total">
                {compactNumber(report.totalHours)} h total
              </span>
            </div>
            <WeeklyBarChart
              points={hourPoints}
              ariaLabel="Weekly hours logged"
              emptyMessage="No hours logged yet."
            />
          </section>
        </>
      ) : null}
    </div>
  );
}
