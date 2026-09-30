import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './auth';
import { JobSearchesProvider } from './jobSearches';
import Layout from './components/Layout';
import SetupPage from './pages/SetupPage';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import SearchDetailPage from './pages/SearchDetailPage';
import JobDetailPage from './pages/JobDetailPage';
import ActivityTrackerPage from './pages/ActivityTrackerPage';
import TodoPage from './pages/TodoPage';
import ResumesPage from './pages/ResumesPage';
import CoverLettersPage from './pages/CoverLettersPage';
import NetworkingPage from './pages/NetworkingPage';
import UsersPage from './pages/UsersPage';

function FullPageLoader() {
  return (
    <div className="fullscreen-loader">
      <div className="spinner" aria-label="Loading" />
    </div>
  );
}

export default function App() {
  const { user, needsSetup, loading } = useAuth();

  if (loading) {
    return <FullPageLoader />;
  }

  if (needsSetup) {
    return <SetupPage />;
  }

  if (!user) {
    return <LoginPage />;
  }

  return (
    <JobSearchesProvider>
      <Layout>
        <Routes>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/searches/:searchId" element={<SearchDetailPage />} />
          <Route path="/jobs/:jobId" element={<JobDetailPage />} />
          <Route path="/activities" element={<ActivityTrackerPage />} />
          <Route path="/todos" element={<TodoPage />} />
          <Route path="/resumes" element={<ResumesPage />} />
          <Route path="/cover-letters" element={<CoverLettersPage />} />
          <Route path="/networking" element={<NetworkingPage />} />
          <Route path="/users" element={<UsersPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Layout>
    </JobSearchesProvider>
  );
}
