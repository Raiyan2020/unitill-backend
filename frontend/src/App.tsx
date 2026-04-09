import type { ReactElement } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from './layouts/AppLayout';
import { AdDetailsPage } from './pages/AdDetailsPage';
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
import { PermissionsPage } from './pages/PermissionsPage';
import { ProfilePage } from './pages/ProfilePage';
import { RolesPage } from './pages/RolesPage';
import { SettingsPage } from './pages/SettingsPage';
import { SubCategoriesPage } from './pages/SubCategoriesPage';
import { TrustedSellerApplicationsPage } from './pages/TrustedSellerApplicationsPage';
import { UserAdsPage } from './pages/UserAdsPage';
import { UserDevicesPage } from './pages/UserDevicesPage';
import { UserDetailsPage } from './pages/UserDetailsPage';
import { UserFavoritesPage } from './pages/UserFavoritesPage';
import { UserVerificationDetailsPage } from './pages/UserVerificationDetailsPage';
import { UsersPage } from './pages/UsersPage';
import { isAuthenticated } from './lib/auth';

function PrivateRoute({ children }: { children: ReactElement }) {
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />;
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
        <Route index element={<DashboardPage />} />
        <Route path="users" element={<UsersPage />} />
        <Route path="ads" element={<AdsPage />} />
        <Route path="ads/:id" element={<AdDetailsPage />} />
        <Route path="ads/user/:userId" element={<UserAdsPage />} />
        <Route path="user-verifications" element={<TrustedSellerApplicationsPage />} />
        <Route path="user-verifications/user/:userId" element={<UserVerificationDetailsPage />} />
        <Route path="users/:id" element={<UserDetailsPage />} />
        <Route path="users/:id/devices" element={<UserDevicesPage />} />
        <Route path="users/:id/favorites" element={<UserFavoritesPage />} />
        <Route path="admins" element={<AdminsPage />} />
        <Route path="roles" element={<RolesPage />} />
        <Route path="permissions" element={<PermissionsPage />} />
        <Route path="countries" element={<CountriesPage />} />
        <Route path="categories" element={<CategoriesPage />} />
        <Route path="categories/:categoryId/subcategories" element={<SubCategoriesPage />} />
        <Route path="languages" element={<LanguagesPage />} />
        <Route path="legal-affairs" element={<LegalAffairsPage />} />
        <Route path="contact-reasons" element={<ContactReasonsPage />} />
        <Route path="contact-us" element={<ContactUsPage />} />
        <Route path="cities" element={<CitiesPage />} />
        <Route path="payment-methods" element={<PaymentMethodsPage />} />
        <Route path="settings" element={<SettingsPage />} />
        <Route path="profile" element={<ProfilePage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
