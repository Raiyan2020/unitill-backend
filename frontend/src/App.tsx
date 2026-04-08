import type { ReactElement } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from './layouts/AppLayout';
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
import { SubCategoriesPage } from './pages/SubCategoriesPage';
import { UserDetailsPage } from './pages/UserDetailsPage';
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
        <Route path="users/:id" element={<UserDetailsPage />} />
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
        <Route path="profile" element={<ProfilePage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
