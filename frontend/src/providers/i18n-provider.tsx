import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

type Locale = 'en' | 'ar';
type Theme = 'light' | 'dark';

type Dictionary = {
  appName: string;
  dashboard: string;
  users: string;
  ads: string;
  adReports: string;
  chatReports: string;
  coupons: string;
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

  // Table headers and form field labels shared across the admin modules.
  id: string;
  name: string;
  nameEn: string;
  nameAr: string;
  title: string;
  type: string;
  code: string;
  slug: string;
  sort: string;
  state: string;
  section: string;
  isDefault: string;
  native: string;
  direction: string;
  image: string;
  date: string;
  city: string;
  cityId: string;
  country: string;
  domains: string;
  emailDomains: string;
  identifier: string;
  deviceName: string;
  lastSeen: string;
  message: string;
  recipients: string;
  reason: string;
  reporter: string;
  reportedUser: string;
  requestNumber: string;
  sellerType: string;
  owner: string;
  price: string;
  paid: string;
  discount: string;
  minSpend: string;
  expires: string;
  used: string;
  user: string;
  ad: string;
  closeSidebar: string;
  togglePassword: string;

  // Filter controls: dropdown options and search placeholders.
  search: string;
  searchShortcut: string;
  searchCode: string;
  inactive: string;
  expired: string;
  allStatuses: string;
  allReasons: string;
  allTypes: string;
  selectRole: string;
  selectCountry: string;
  percentage: string;
  fixedAmount: string;
  nativeName: string;
  permissionName: string;
  enterEmailOrUsername: string;
  organization: string;
  phoneNumber: string;
  address: string;
  zipCode: string;
  countryCodeHint: string;
  reportedConversation: string;
  currentPassword: string;
  newPassword: string;
  confirmNewPassword: string;
  messageBody: string;
  roleName: string;
};

const dictionaries: Record<Locale, Dictionary> = {
  en: {
    appName: 'Unitill Admin',
    dashboard: 'Dashboard',
    users: 'Users',
    ads: 'Ads',
    adReports: 'Ad Reports',
    chatReports: 'Chat Reports',
    coupons: 'Coupons',
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

    id: 'ID',
    name: 'Name',
    nameEn: 'Name EN',
    nameAr: 'Name AR',
    title: 'Title',
    type: 'Type',
    code: 'Code',
    slug: 'Slug',
    sort: 'Sort',
    state: 'State',
    section: 'Section',
    isDefault: 'Default',
    native: 'Native',
    direction: 'Dir',
    image: 'Image',
    date: 'Date',
    city: 'City',
    cityId: 'City ID',
    country: 'Country',
    domains: 'Domains',
    emailDomains: 'Email domains / subdomains',
    identifier: 'Identifier',
    deviceName: 'Device Name',
    lastSeen: 'Last Seen',
    message: 'Message',
    recipients: 'Recipients',
    reason: 'Reason',
    reporter: 'Reporter',
    reportedUser: 'Reported user',
    requestNumber: 'Request #',
    sellerType: 'Seller Type',
    owner: 'Owner',
    price: 'Price',
    paid: 'Paid',
    discount: 'Discount',
    minSpend: 'Min spend',
    expires: 'Expires',
    used: 'Used',
    user: 'User',
    ad: 'Ad',
    closeSidebar: 'Close sidebar',
    togglePassword: 'Toggle password visibility',

    search: 'Search',
    searchShortcut: 'Search (CTRL + K)',
    searchCode: 'Search code',
    inactive: 'Inactive',
    expired: 'Expired',
    allStatuses: 'All statuses',
    allReasons: 'All reasons',
    allTypes: 'All types',
    selectRole: 'Select role',
    selectCountry: 'Select country',
    percentage: 'Percentage (%)',
    fixedAmount: 'Fixed amount (£)',
    nativeName: 'Native name',
    permissionName: 'Permission name',
    enterEmailOrUsername: 'Enter your email or username',
    organization: 'Organization',
    phoneNumber: 'Phone Number',
    address: 'Address',
    zipCode: 'Zip Code',
    countryCodeHint: 'Country Code (2)',
    reportedConversation: 'Reported conversation',
    currentPassword: 'Current password',
    newPassword: 'New password',
    confirmNewPassword: 'Confirm new password',
    messageBody: 'Message body',
    roleName: 'Role name',
  },
  ar: {
       appName: 'لوحة يونيتل',
    dashboard: 'لوحة التحكم',
    users: 'المستخدمون',
    ads: 'الإعلانات',
    adReports: 'بلاغات الإعلانات',
    chatReports: 'بلاغات المحادثات',
    coupons: 'الكوبونات',
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

    id: 'المعرّف',
    name: 'الاسم',
    nameEn: 'الاسم بالإنجليزية',
    nameAr: 'الاسم بالعربية',
    title: 'العنوان',
    type: 'النوع',
    code: 'الرمز',
    slug: 'المعرّف النصي',
    sort: 'الترتيب',
    state: 'الحالة',
    section: 'القسم',
    isDefault: 'افتراضي',
    native: 'الاسم الأصلي',
    direction: 'الاتجاه',
    image: 'الصورة',
    date: 'التاريخ',
    city: 'المدينة',
    cityId: 'معرّف المدينة',
    country: 'الدولة',
    domains: 'النطاقات',
    emailDomains: 'نطاقات البريد والنطاقات الفرعية',
    identifier: 'المعرّف',
    deviceName: 'اسم الجهاز',
    lastSeen: 'آخر ظهور',
    message: 'الرسالة',
    recipients: 'المستلمون',
    reason: 'السبب',
    reporter: 'المُبلِّغ',
    reportedUser: 'المستخدم المُبلَّغ عنه',
    requestNumber: 'رقم الطلب',
    sellerType: 'نوع البائع',
    owner: 'المالك',
    price: 'السعر',
    paid: 'مدفوع',
    discount: 'الخصم',
    minSpend: 'الحد الأدنى للإنفاق',
    expires: 'ينتهي في',
    used: 'مرات الاستخدام',
    user: 'المستخدم',
    ad: 'الإعلان',
    closeSidebar: 'إغلاق القائمة',
    togglePassword: 'إظهار أو إخفاء كلمة المرور',

    search: 'بحث',
    searchShortcut: 'بحث (CTRL + K)',
    searchCode: 'ابحث برمز الكوبون',
    inactive: 'غير مفعّل',
    expired: 'منتهي',
    allStatuses: 'كل الحالات',
    allReasons: 'كل الأسباب',
    allTypes: 'كل الأنواع',
    selectRole: 'اختر الدور',
    selectCountry: 'اختر الدولة',
    percentage: 'نسبة مئوية (%)',
    fixedAmount: 'مبلغ ثابت (£)',
    nativeName: 'الاسم بلغته الأصلية',
    permissionName: 'اسم الصلاحية',
    enterEmailOrUsername: 'أدخل بريدك الإلكتروني أو اسم المستخدم',
    organization: 'الجهة',
    phoneNumber: 'رقم الهاتف',
    address: 'العنوان',
    zipCode: 'الرمز البريدي',
    countryCodeHint: 'رمز الدولة (حرفان)',
    reportedConversation: 'المحادثة المُبلَّغ عنها',
    currentPassword: 'كلمة المرور الحالية',
    newPassword: 'كلمة المرور الجديدة',
    confirmNewPassword: 'تأكيد كلمة المرور الجديدة',
    messageBody: 'نص الرسالة',
    roleName: 'اسم الدور',
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
