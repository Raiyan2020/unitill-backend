import type { ReactElement } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from './layouts/AppLayout';
import { AdDetailsPage } from './pages/AdDetailsPage';
import { AdReportsPage } from './pages/AdReportsPage';
import { ChatReportsPage } from './pages/ChatReportsPage';
import { CouponsPage } from './pages/CouponsPage';
import { AdsPage } from './pages/AdsPage';
import { AdminsPage } from './pages/AdminsPage';
import { CitiesPage } from './pages/CitiesPage';
import { ContactReasonsPage } from './pages/ContactReasonsPage';
import { ContactUsPage } from './pages/ContactUsPage';
import { CountriesPage } from './pages/CountriesPage';
import { CategoriesPage } from './pages/CategoriesPage';
import { DashboardPage } from './pages/DashboardPage';
import { LegalAffairsPage } from './pages/LegalAffairsPage';
import { LanguagesPage } from './pages/LanguagesPage';
import { LoginPage } from './pages/LoginPage';
import { PaymentMethodsPage } from './pages/PaymentMethodsPage';
import { ProfilePage } from './pages/ProfilePage';
import { PushNotificationsPage } from './pages/PushNotificationsPage';
import { RolesPage } from './pages/RolesPage';
import { SettingsPage } from './pages/SettingsPage';
import { SubCategoriesPage } from './pages/SubCategoriesPage';
import { TrustedSellerApplicationsPage } from './pages/TrustedSellerApplicationsPage';
import { UniversitiesPage } from './pages/UniversitiesPage';
import { UserAdsPage } from './pages/UserAdsPage';
import { UserDevicesPage } from './pages/UserDevicesPage';
import { UserDetailsPage } from './pages/UserDetailsPage';
import { UserFavoritesPage } from './pages/UserFavoritesPage';
import { UserVerificationDetailsPage } from './pages/UserVerificationDetailsPage';
import { UsersPage } from './pages/UsersPage';
import { hasPermission, isAuthenticated } from './lib/auth';

function PrivateRoute({ children }: { children: ReactElement }) {
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }

  return children;
}

function PermissionRoute({ permission, children }: { permission: string; children: ReactElement }) {
  if (!hasPermission(permission)) {
    return <Navigate to="/profile" replace />;
  }
  return children;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/"
        element={
          <PrivateRoute>
            <AppLayout />
          </PrivateRoute>
        }
      >
        <Route index element={<PermissionRoute permission="dashboard.view"><DashboardPage /></PermissionRoute>} />
        <Route path="users" element={<PermissionRoute permission="users.view"><UsersPage /></PermissionRoute>} />
        <Route path="ads" element={<PermissionRoute permission="categories.view"><AdsPage /></PermissionRoute>} />
        <Route path="ads/:id" element={<PermissionRoute permission="categories.view"><AdDetailsPage /></PermissionRoute>} />
        <Route path="ads/user/:userId" element={<PermissionRoute permission="categories.view"><UserAdsPage /></PermissionRoute>} />
        <Route path="ad-reports" element={<PermissionRoute permission="ad_reports.view"><AdReportsPage /></PermissionRoute>} />
        <Route path="chat-reports" element={<PermissionRoute permission="chat_reports.view"><ChatReportsPage /></PermissionRoute>} />
        <Route path="coupons" element={<PermissionRoute permission="coupons.view"><CouponsPage /></PermissionRoute>} />
        <Route path="user-verifications" element={<PermissionRoute permission="users.view"><TrustedSellerApplicationsPage /></PermissionRoute>} />
        <Route path="user-verifications/user/:userId" element={<PermissionRoute permission="users.view"><UserVerificationDetailsPage /></PermissionRoute>} />
        <Route path="users/:id" element={<PermissionRoute permission="users.view"><UserDetailsPage /></PermissionRoute>} />
        <Route path="users/:id/devices" element={<PermissionRoute permission="users.view"><UserDevicesPage /></PermissionRoute>} />
        <Route path="users/:id/favorites" element={<PermissionRoute permission="users.view"><UserFavoritesPage /></PermissionRoute>} />
        <Route path="admins" element={<PermissionRoute permission="admins.view"><AdminsPage /></PermissionRoute>} />
        <Route path="roles" element={<PermissionRoute permission="roles.view"><RolesPage /></PermissionRoute>} />
        <Route path="countries" element={<PermissionRoute permission="countries.view"><CountriesPage /></PermissionRoute>} />
        <Route path="categories" element={<PermissionRoute permission="categories.view"><CategoriesPage /></PermissionRoute>} />
        <Route path="categories/:categoryId/subcategories" element={<PermissionRoute permission="subcategories.view"><SubCategoriesPage /></PermissionRoute>} />
        <Route path="languages" element={<PermissionRoute permission="languages.view"><LanguagesPage /></PermissionRoute>} />
        <Route path="legal-affairs" element={<PermissionRoute permission="legal_affairs.view"><LegalAffairsPage /></PermissionRoute>} />
        <Route path="contact-reasons" element={<PermissionRoute permission="contact_reasons.view"><ContactReasonsPage /></PermissionRoute>} />
        <Route path="contact-us" element={<PermissionRoute permission="contact_us.view"><ContactUsPage /></PermissionRoute>} />
        <Route path="push-notifications" element={<PermissionRoute permission="dashboard.view"><PushNotificationsPage /></PermissionRoute>} />
        <Route path="cities" element={<PermissionRoute permission="cities.view"><CitiesPage /></PermissionRoute>} />
        <Route path="universities" element={<PermissionRoute permission="universities.view"><UniversitiesPage /></PermissionRoute>} />
        <Route path="payment-methods" element={<PermissionRoute permission="payment_methods.view"><PaymentMethodsPage /></PermissionRoute>} />
        <Route path="settings" element={<PermissionRoute permission="dashboard.view"><SettingsPage /></PermissionRoute>} />
        <Route path="profile" element={<ProfilePage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
