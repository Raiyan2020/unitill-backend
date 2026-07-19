import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

type Locale = 'en' | 'ar';
type Theme = 'light' | 'dark';

type Dictionary = {
  appName: string;
  dashboard: string;
  users: string;
  ads: string;
  adReports: string;
  userVerifications: string;
  admins: string;
  roles: string;
  permissions: string;
  profile: string;
  settings: string;
  countries: string;
  cities: string;
  universities: string;
  paymentMethods: string;
  languages: string;
  categories: string;
  subCategories: string;
  legalAffairs: string;
  contactReasons: string;
  contactUs: string;
  pushNotifications: string;
  sendNotification: string;
  sendToAll: string;
  sendToUser: string;
  audience: string;
  notificationTitle: string;
  notificationBody: string;
  noDataFound: string;
  login: string;
  logout: string;
  email: string;
  password: string;
  welcomeBack: string;
  signInSubtitle: string;
  searchUsers: string;
  refresh: string;
  actions: string;
  details: string;
  edit: string;
  delete: string;
  status: string;
  active: string;
  pending: string;
  disabled: string;
  firstName: string;
  lastName: string;
  phone: string;
  countryCode: string;
  save: string;
  cancel: string;
  userDetails: string;
  backToUsers: string;
  createdAt: string;
  language: string;
  theme: string;
};

const dictionaries: Record<Locale, Dictionary> = {
  en: {
    appName: 'Unitill Admin',
    dashboard: 'Dashboard',
    users: 'Users',
    ads: 'Ads',
    adReports: 'Ad Reports',
    userVerifications: 'User Verifications',
    admins: 'Admins',
    roles: 'Roles',
    permissions: 'Permissions',
    profile: 'Profile',
    settings: 'Settings',
    countries: 'Countries',
    cities: 'Cities',
    universities: 'Universities',
    paymentMethods: 'Payment Methods',
    languages: 'Languages',
    categories: 'Categories',
    subCategories: 'Sub Categories',
    legalAffairs: 'Legal Affairs',
    contactReasons: 'Contact Reasons',
    contactUs: 'Contact Us',
    pushNotifications: 'Push Notifications',
    sendNotification: 'Send Notification',
    sendToAll: 'All users (topic)',
    sendToUser: 'Single user',
    audience: 'Audience',
    notificationTitle: 'Title',
    notificationBody: 'Body',
    noDataFound: 'No data available right now.',
    login: 'Login',
    logout: 'Logout',
    email: 'Email',
    password: 'Password',
    welcomeBack: 'Welcome back',
    signInSubtitle: 'Sign in with your admin credentials.',
    searchUsers: 'Search by name, email, phone',
    refresh: 'Refresh',
    actions: 'Actions',
    details: 'Details',
    edit: 'Edit',
    delete: 'Delete',
    status: 'Status',
    active: 'Active',
    pending: 'Pending',
    disabled: 'Disabled',
    firstName: 'First name',
    lastName: 'Last name',
    phone: 'Phone',
    countryCode: 'Country code',
    save: 'Save',
    cancel: 'Cancel',
    userDetails: 'User details',
    backToUsers: 'Back to users',
    createdAt: 'Created at',
    language: 'Language',
    theme: 'Theme',
  },
  ar: {
       appName: 'لوحة يونيتل',
    dashboard: 'لوحة التحكم',
    users: 'المستخدمون',
    ads: 'الإعلانات',
    adReports: 'بلاغات الإعلانات',
    userVerifications: 'طلبات التوثيق',
    admins: 'المدراء',
    roles: 'الأدوار',
    permissions: 'الصلاحيات',
    profile: 'الملف الشخصي',
    settings: 'الإعدادات',
    countries: 'الدول',
    cities: 'المدن',
    universities: 'الجامعات',
    paymentMethods: 'طرق الدفع',
    languages: 'اللغات',
    categories: 'الأقسام',
    subCategories: 'الأقسام الفرعية',
    legalAffairs: 'الشؤون القانونية',
    contactReasons: 'أسباب التواصل',
    contactUs: 'اتصل بنا',
    pushNotifications: 'الإشعارات',
    sendNotification: 'إرسال إشعار',
    sendToAll: 'جميع المستخدمين (Topic)',
    sendToUser: 'مستخدم واحد',
    audience: 'الفئة المستهدفة',
    notificationTitle: 'العنوان',
    notificationBody: 'النص',
    noDataFound: 'لا توجد بيانات متاحة حاليًا.',
    login: 'تسجيل الدخول',
    logout: 'تسجيل الخروج',
    email: 'البريد الإلكتروني',
    password: 'كلمة المرور',
    welcomeBack: 'أهلا بعودتك',
    signInSubtitle: 'سجل الدخول ببيانات الأدمن.',
    searchUsers: 'ابحث بالاسم أو البريد أو الهاتف',
    refresh: 'تحديث',
    actions: 'الإجراءات',
    details: 'عرض',
    edit: 'تعديل',
    delete: 'حذف',
    status: 'الحالة',
    active: 'نشط',
    pending: 'قيد المراجعة',
    disabled: 'معطل',
    firstName: 'الاسم الأول',
    lastName: 'اسم العائلة',
    phone: 'الهاتف',
    countryCode: 'رمز الدولة',
    save: 'حفظ',
    cancel: 'إلغاء',
    userDetails: 'تفاصيل المستخدم',
    backToUsers: 'العودة للمستخدمين',
    createdAt: 'تاريخ الإنشاء',
    language: 'اللغة',
    theme: 'السمة',
  },
};

type I18nContextType = {
  locale: Locale;
  dir: 'ltr' | 'rtl';
  theme: Theme;
  t: Dictionary;
  setLocale: (locale: Locale) => void;
  setTheme: (theme: Theme) => void;
};

const I18nContext = createContext<I18nContextType | null>(null);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocale] = useState<Locale>(() => {
    const saved = localStorage.getItem('unitill_locale');
    return saved === 'ar' ? 'ar' : 'en';
  });

  const [theme, setTheme] = useState<Theme>(() => {
    const saved = localStorage.getItem('unitill_theme');
    return saved === 'dark' ? 'dark' : 'light';
  });

  useEffect(() => {
    localStorage.setItem('unitill_locale', locale);
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
  }, [locale]);

  useEffect(() => {
    localStorage.setItem('unitill_theme', theme);
    document.documentElement.classList.toggle('dark', theme === 'dark');
  }, [theme]);

  const value = useMemo<I18nContextType>(() => ({
    locale,
    dir: locale === 'ar' ? 'rtl' : 'ltr',
    theme,
    t: dictionaries[locale],
    setLocale,
    setTheme,
  }), [locale, theme]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  const context = useContext(I18nContext);
  if (!context) throw new Error('useI18n must be used inside I18nProvider');
  return context;
}
