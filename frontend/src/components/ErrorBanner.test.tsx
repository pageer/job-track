import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import ErrorBanner from './ErrorBanner';

describe('ErrorBanner', () => {
  it('renders nothing when there is no message', () => {
    const { container } = render(<ErrorBanner message={null} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('renders the message in an alert', () => {
    render(<ErrorBanner message="Something broke" />);
    expect(screen.getByRole('alert')).toHaveTextContent('Something broke');
  });
});
