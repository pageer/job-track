import type { WeeklyChartPoint } from '../reports';

interface WeeklyBarChartProps {
  points: WeeklyChartPoint[];
  ariaLabel: string;
  emptyMessage: string;
}

export default function WeeklyBarChart({
  points,
  ariaLabel,
  emptyMessage,
}: WeeklyBarChartProps) {
  if (points.length === 0) {
    return <p className="muted report-chart-empty">{emptyMessage}</p>;
  }

  const maxValue = Math.max(1, ...points.map((point) => point.value));

  return (
    <div className="bar-chart-scroll">
      <div className="bar-chart" role="img" aria-label={ariaLabel}>
        {points.map((point) => {
          const height =
            point.value <= 0 ? 0 : Math.max(2, (point.value / maxValue) * 100);
          return (
            <div
              className="bar-chart-col"
              key={point.key}
              title={`${point.label}: ${point.displayValue}`}
            >
              <span className="bar-chart-value">{point.displayValue}</span>
              <div className="bar-chart-track">
                <div
                  className="bar-chart-bar"
                  style={{ height: `${height}%` }}
                />
              </div>
              <span className="bar-chart-label">{point.label}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
