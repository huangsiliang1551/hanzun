import { lazy } from 'react';
import { Navigate } from 'react-router-dom';
import AuthGuard, { LoginRedirectGuard } from '@/components/AuthGuard';
import AdminLayout from '@/layouts/AdminLayout';

const CasesPage = lazy(() => import('@/pages/CasesPage'));
const CertificatesPage = lazy(() => import('@/pages/CertificatesPage'));
const CompanyPage = lazy(() => import('@/pages/CompanyPage'));
const ContactsPage = lazy(() => import('@/pages/ContactsPage'));
const AdsPage = lazy(() => import('@/pages/AdsPage'));
const DashboardPage = lazy(() => import('@/pages/DashboardPage'));
const HomepagePage = lazy(() => import('@/pages/HomepagePage'));
const InquiriesPage = lazy(() => import('@/pages/InquiriesPage'));
const KnowledgePage = lazy(() => import('@/pages/KnowledgePage'));
const LoginPage = lazy(() => import('@/pages/LoginPage'));
const NavigationPage = lazy(() => import('@/pages/NavigationPage'));
const NewsPage = lazy(() => import('@/pages/NewsPage'));
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage'));
const PagesPage = lazy(() => import('@/pages/PagesPage'));
const ProductsPage = lazy(() => import('@/pages/ProductsPage'));
const ResourcesPage = lazy(() => import('@/pages/ResourcesPage'));
const SeoDashboardPage = lazy(() => import('@/pages/SeoDashboardPage'));
const SettingsPage = lazy(() => import('@/pages/SettingsPage'));
const SolutionsPage = lazy(() => import('@/pages/SolutionsPage'));
const TasksPage = lazy(() => import('@/pages/TasksPage'));
const TeamPage = lazy(() => import('@/pages/TeamPage'));

export const routes = [
  {
    path: '/login',
    element: (
      <LoginRedirectGuard>
        <LoginPage />
      </LoginRedirectGuard>
    ),
  },
  {
    path: '/',
    element: <AuthGuard />,
    children: [
      {
        element: <AdminLayout />,
        children: [
          {
            index: true,
            element: <Navigate to="/dashboard" replace />,
          },
          {
            path: 'dashboard',
            element: <DashboardPage />,
          },
          {
            path: 'products',
            element: <ProductsPage />,
          },
          {
            path: 'solutions',
            element: <SolutionsPage />,
          },
          {
            path: 'news',
            element: <NewsPage />,
          },
          {
            path: 'cases',
            element: <CasesPage />,
          },
          {
            path: 'certificates',
            element: <CertificatesPage />,
          },
          {
            path: 'team',
            element: <TeamPage />,
          },
          {
            path: 'pages',
            element: <PagesPage />,
          },
          {
            path: 'homepage',
            element: <HomepagePage />,
          },
          {
            path: 'contacts',
            element: <ContactsPage />,
          },
          {
            path: 'ads',
            element: <AdsPage />,
          },
          {
            path: 'company',
            element: <CompanyPage />,
          },
          {
            path: 'navigation',
            element: <NavigationPage />,
          },
          {
            path: 'resources',
            element: <ResourcesPage />,
          },
          {
            path: 'inquiries',
            element: <InquiriesPage />,
          },
          {
            path: 'seo-dashboard',
            element: <SeoDashboardPage />,
          },
          {
            path: 'seo-center',
            element: <SeoDashboardPage />,
          },
          {
            path: 'tasks',
            element: <TasksPage />,
          },
          {
            path: 'knowledge',
            element: <KnowledgePage />,
          },
          {
            path: 'settings',
            element: <SettingsPage />,
          },
          {
            path: '*',
            element: <NotFoundPage />,
          },
        ],
      },
    ],
  },
];
