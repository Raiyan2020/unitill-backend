import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

type Locale = 'en' | 'ar';
type Theme = 'light' | 'dark';

export type Dictionary = {
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
  listingFee: string;
  listingFeeStandard: string;
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
  account: string;
  security: string;
  uploadNewPhoto: string;
  resetPhoto: string;
  loading: string;
  changePassword: string;
  profileUpdated: string;
  photoUpdated: string;
  photoRemoved: string;
  updateFailed: string;
  superAdmin: string;
  adminRole: string;
  connected: string;
  thanLastWeek: string;
  adsRevenueLastTenDays: string;
  dailyAdsAndRevenue: string;
  adsLabel: string;
  revenueLabel: string;
  manageUsersSubtitle: string;
  userNotFound: string;
  previous: string;
  next: string;
  userUpdatedSuccessfully: string;
  userCreatedSuccessfully: string;
  userDeletedSuccessfully: string;
  createUser: string;
  confirmPassword: string;
  confirmDeletion: string;
  deleteUserConfirmation: string;
  back: string;
  yes: string;
  no: string;
  userDeviceSessions: string;
  userFavoriteAds: string;
  adDetails: string;
  adNotFound: string;
  subtitle: string;
  countryAndCity: string;
  category: string;
  description: string;
  specifications: string;
  gallery: string;
  noSpecifications: string;
  draft: string;
  published: string;
  rejected: string;
  sold: string;

  // Added August 2026: strings that were hardcoded in the page components.
  add: string;
  admin: string;
  permission: string;
  role: string;
  subCategory: string;
  university: string;
  legalAffair: string;
  contactReason: string;
  paymentMethod: string;
  coupon: string;
  total: string;
  reviewed: string;
  dismissed: string;
  approved: string;
  usedUp: string;
  default: string;
  deletedSuccessfully: string;
  savedSuccessfully: string;
  statusUpdatedSuccessfully: string;
  adDeleted: string;
  adOwner: string;
  reporterExplanation: string;
  reportedAd: string;
  relatedAd: string;
  openAd: string;
  noMessagesInConversation: string;
  welcomeToDashboard: string;
  rememberMe: string;
  forgotPassword: string;
  profit: string;
  order: string;
  topic: string;
  estimatedAudience: string;
  firebase: string;
  userId: string;
  linkOptional: string;
  history: string;
  titleAndBodyRequired: string;
  userIdRequiredForSingle: string;
  newCoupon: string;
  maxDiscountOptional: string;
  minSpendOptional: string;
  totalUsesHint: string;
  startsAtOptional: string;
  expiresAtOptional: string;
  redemptions: string;
  couponNotUsedYet: string;
  managePlatformSettings: string;
  loadingSettings: string;
  noChangesToSave: string;
  settingsUpdated: string;
  noDomainsAdded: string;
  stateHint: string;
  domainHint: string;
  universityNameRequired: string;
  atLeastOneTranslation: string;
  passwordRequired: string;
  passwordConfirmationMismatch: string;
  permissionNameRequired: string;
  userVerificationDetails: string;
  allRequests: string;
  latestVerificationRequest: string;
  noVerificationRequest: string;
  allUserVerificationRequests: string;
  operationsCity: string;
  preferredContact: string;
  offersSummary: string;
  viewVerificationDetails: string;
  deviceSessions: string;
  favoriteAds: string;
  userAds: string;
  dashboards: string;
  editCoupon: string;
  couponOncePerUserNote: string;
  deleteConfirmation: string;
  deleteUniversityConfirmation: string;
  actionFailed: string;
  newPasswordOptional: string;
  deleteAdConfirmation: string;
  verificationRequest: string;
  business: string;
  service_provider: string;
  organization_type: string;
  website: string;
  updatedSuccessfully: string;
  createdSuccessfully: string;
  passwordTooShort: string;
  passwordConfirmationRequired: string;
  roleRequired: string;
  deleteAdminConfirmation: string;
  view: string;
  create: string;
  update: string;
  page: string;
  rolePermissions: string;
  noPermissionsAssigned: string;
  roleNameRequired: string;
  rolePermissionsRequired: string;
  deleteRoleConfirmation: string;
  permissionsSelected: string;
  countryCodeRequired: string;
  countryCodeLength: string;
  nameRequiredAnyLanguage: string;
  countryRequired: string;
  cityCodeLength: string;
  nameInLanguage: string;
  currentImage: string;
  noImage: string;
  changeImage: string;
  languageCodeRequired: string;
  languageCodeLength: string;
  titleRequired: string;
  isActiveQuestion: string;
  loginFailed: string;
  nameArRequired: string;
  nameEnRequired: string;
  slugRequired: string;
  imageRequired: string;
  viewImage: string;
  allUsers: string;
  singleUser: string;
  selectUser: string;
  deletePaymentMethodConfirmation: string;
  sending: string;
  notificationSent: string;
  firebaseNotConfigured: string;
  searchUser: string;
  noUsersFound: string;
  nameRequired: string;
  emailRequired: string;
  emailInvalid: string;
  paymentUnsettledShort: string;
  menu: string;
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
    sendToAll: 'All users',
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
    listingFee: 'Listing fee (£)',
    listingFeeStandard: 'Standard price',
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
    account: 'Account',
    security: 'Security',
    uploadNewPhoto: 'Upload new photo',
    resetPhoto: 'Reset',
    loading: 'Loading...',
    changePassword: 'Change password',
    profileUpdated: 'Profile updated successfully.',
    photoUpdated: 'Profile photo updated successfully.',
    photoRemoved: 'Profile photo removed.',
    updateFailed: 'Unable to save changes.',
    superAdmin: 'Super administrator',
    adminRole: 'Administrator',
    connected: 'Connected',
    thanLastWeek: 'than last week',
    adsRevenueLastTenDays: 'Ads & Revenue (Last 10 Days)',
    dailyAdsAndRevenue: 'Daily ads count (bars) and revenue (line)',
    adsLabel: 'Ads',
    revenueLabel: 'Revenue',
    manageUsersSubtitle: 'Manage users and basic profile data',
    userNotFound: 'User not found.',
    previous: 'Prev',
    next: 'Next',
    userUpdatedSuccessfully: 'User updated successfully.',
    userCreatedSuccessfully: 'User created successfully.',
    userDeletedSuccessfully: 'User deleted successfully.',
    createUser: 'Add user',
    confirmPassword: 'Confirm password',
    confirmDeletion: 'Confirm deletion',
    deleteUserConfirmation: 'Are you sure you want to delete {name}? This action cannot be undone.',
    back: 'Back',
    yes: 'Yes',
    no: 'No',
    userDeviceSessions: 'User device sessions',
    userFavoriteAds: 'User favorite ads',
    adDetails: 'Ad details',
    adNotFound: 'Ad not found.',
    subtitle: 'Subtitle',
    countryAndCity: 'Country / City',
    category: 'Category',
    description: 'Description',
    specifications: 'Specifications',
    gallery: 'Gallery',
    noSpecifications: 'No specifications submitted for this ad.',
    draft: 'Draft',
    published: 'Published',
    rejected: 'Rejected',
    sold: 'Sold',
    add: "Add",
    admin: "Admin",
    permission: "Permission",
    role: "Role",
    subCategory: "Sub Category",
    university: "University",
    legalAffair: "Legal Affair",
    contactReason: "Contact Reason",
    paymentMethod: "Payment Method",
    coupon: "Coupon",
    total: "Total",
    reviewed: "Reviewed",
    dismissed: "Dismissed",
    approved: "Approved",
    usedUp: "Used up",
    default: "Default",
    deletedSuccessfully: "Deleted successfully.",
    savedSuccessfully: "Saved successfully.",
    statusUpdatedSuccessfully: "Status updated successfully.",
    adDeleted: "Ad deleted",
    adOwner: "Ad owner",
    reporterExplanation: "Reporter's explanation",
    reportedAd: "Reported ad",
    relatedAd: "Related ad",
    openAd: "Open ad",
    noMessagesInConversation: "No messages in this conversation.",
    welcomeToDashboard: "Welcome to Dashboard!",
    rememberMe: "Remember Me",
    forgotPassword: "Forgot Password?",
    profit: "Profit",
    order: "Order",
    topic: "Topic",
    estimatedAudience: "Estimated audience",
    firebase: "Firebase",
    userId: "User ID",
    linkOptional: "Link (optional)",
    history: "History",
    titleAndBodyRequired: "Title and body are required.",
    userIdRequiredForSingle: "User ID is required for single-user notifications.",
    newCoupon: "New coupon",
    maxDiscountOptional: "Max discount £ (optional)",
    minSpendOptional: "Min spend £ (optional)",
    totalUsesHint: "Total uses (blank = unlimited)",
    startsAtOptional: "Starts at (optional)",
    expiresAtOptional: "Expires at (optional)",
    redemptions: "Redemptions",
    couponNotUsedYet: "This coupon has not been used yet.",
    managePlatformSettings: "Manage platform settings and contact info.",
    loadingSettings: "Loading settings...",
    noChangesToSave: "No changes to save.",
    settingsUpdated: "Settings updated successfully.",
    noDomainsAdded: "No domains added yet.",
    stateHint: "State (e.g. CA)",
    domainHint: "e.g. harvard.edu",
    universityNameRequired: "University name is required.",
    atLeastOneTranslation: "At least one translation is required.",
    passwordRequired: "Password is required.",
    passwordConfirmationMismatch: "Password confirmation does not match.",
    permissionNameRequired: "Permission name is required.",
    userVerificationDetails: "User Verification Details",
    allRequests: "All Requests",
    latestVerificationRequest: "Latest Verification Request",
    noVerificationRequest: "No verification request found for this user.",
    allUserVerificationRequests: "All User Verification Requests",
    operationsCity: "Operations City",
    preferredContact: "Preferred Contact",
    offersSummary: "Offers Summary",
    viewVerificationDetails: "View verification details",
    deviceSessions: "Device sessions",
    favoriteAds: "Favorite ads",
    userAds: "User Ads",
    dashboards: "Dashboards",
    editCoupon: "Edit coupon",
    couponOncePerUserNote: "Every coupon can be used only once per user — that limit is enforced automatically.",
    deleteConfirmation: "This item will be permanently deleted. Continue?",
    deleteUniversityConfirmation: "This university and its domains will be permanently deleted. Continue?",
    actionFailed: "The action could not be completed.",
    newPasswordOptional: "New password (optional)",
    deleteAdConfirmation: "This ad will be permanently deleted. Continue?",
    verificationRequest: "Verification Request",
    business: "Business",
    service_provider: "Service provider",
    organization_type: "Organization",
    website: "Website",
    updatedSuccessfully: "Updated successfully.",
    createdSuccessfully: "Created successfully.",
    passwordTooShort: "Password must be at least {min} characters.",
    passwordConfirmationRequired: "Please confirm the password.",
    roleRequired: "Please select a role for this admin.",
    deleteAdminConfirmation: "This admin will be permanently deleted. Continue?",
    view: "View",
    create: "Create",
    update: "Update",
    page: "Page",
    rolePermissions: "Role permissions",
    noPermissionsAssigned: "No permissions assigned.",
    roleNameRequired: "Please enter the role name.",
    rolePermissionsRequired: "Please select at least one permission.",
    deleteRoleConfirmation: "This role will be permanently deleted. Continue?",
    permissionsSelected: "selected",
    countryCodeRequired: "Country code is required.",
    countryCodeLength: "Country code must be exactly 2 letters.",
    nameRequiredAnyLanguage: "Enter the name in at least one language.",
    countryRequired: "Please select a country.",
    cityCodeLength: "City code must not exceed 50 characters.",
    nameInLanguage: "Name",
    currentImage: "Current image",
    noImage: "No image",
    changeImage: "Change image",
    languageCodeRequired: "Language code is required.",
    languageCodeLength: "Language code must not exceed {max} characters.",
    titleRequired: "Title is required.",
    isActiveQuestion: "Activation status",
    loginFailed: "Login failed",
    nameArRequired: "The Arabic name is required.",
    nameEnRequired: "The English name is required.",
    slugRequired: "The slug is required.",
    imageRequired: "An image is required.",
    viewImage: "View image",
    allUsers: "All users",
    singleUser: "A single user",
    selectUser: "Select a user",
    deletePaymentMethodConfirmation: "This payment method will be permanently deleted. Continue?",
    sending: "Sending...",
    notificationSent: "Notification sent.",
    firebaseNotConfigured: "Push notifications are disabled: Firebase is not configured on the server.",
    searchUser: "Search by name or email",
    noUsersFound: "No users found",
    nameRequired: "The name is required.",
    emailRequired: "The email address is required.",
    emailInvalid: "Enter a valid email address, for example name@example.com",
    paymentUnsettledShort: "fee unpaid",
    menu: "Menu",
  },
  ar: {
    appName: 'لوحة يونيتل',
    dashboard: 'لوحة التحكم',
    users: 'المستخدمين',
    ads: 'الإعلانات',
    adReports: 'بلاغات الإعلانات',
    chatReports: 'بلاغات المحادثات',
    coupons: 'الكوبونات',
    userVerifications: 'طلبات التوثيق',
    admins: 'المديرين',
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
    sendToAll: 'جميع المستخدمين',
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
    listingFee: 'سعر الإعلان (£)',
    listingFeeStandard: 'السعر القياسي',
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
    account: 'الحساب',
    security: 'الأمان',
    uploadNewPhoto: 'رفع صورة جديدة',
    resetPhoto: 'إزالة الصورة',
    loading: 'جارٍ التحميل...',
    changePassword: 'تغيير كلمة المرور',
    profileUpdated: 'تم تحديث الملف الشخصي بنجاح.',
    photoUpdated: 'تم تحديث صورة الملف الشخصي بنجاح.',
    photoRemoved: 'تمت إزالة صورة الملف الشخصي.',
    updateFailed: 'تعذر حفظ التغييرات.',
    superAdmin: 'مدير النظام',
    adminRole: 'مشرف',
    connected: 'متصل',
    thanLastWeek: 'مقارنة بالأسبوع الماضي',
    adsRevenueLastTenDays: 'الإعلانات والأرباح خلال آخر 10 أيام',
    dailyAdsAndRevenue: 'عدد الإعلانات اليومي (أعمدة) والأرباح (خط)',
    adsLabel: 'الإعلانات',
    revenueLabel: 'الأرباح',
    manageUsersSubtitle: 'إدارة المستخدمين وبيانات الملف الشخصي الأساسية',
    userNotFound: 'لم يتم العثور على المستخدم.',
    previous: 'السابق',
    next: 'التالي',
    userUpdatedSuccessfully: 'تم تعديل المستخدم بنجاح.',
    userCreatedSuccessfully: 'تمت إضافة المستخدم بنجاح.',
    userDeletedSuccessfully: 'تم حذف المستخدم بنجاح.',
    createUser: 'إضافة مستخدم',
    confirmPassword: 'تأكيد كلمة المرور',
    confirmDeletion: 'تأكيد الحذف',
    deleteUserConfirmation: 'هل أنت متأكد من حذف {name}؟ لا يمكن التراجع عن هذا الإجراء.',
    back: 'رجوع',
    yes: 'نعم',
    no: 'لا',
    userDeviceSessions: 'جلسات أجهزة المستخدم',
    userFavoriteAds: 'الإعلانات المفضلة للمستخدم',
    adDetails: 'تفاصيل الإعلان',
    adNotFound: 'لم يتم العثور على الإعلان.',
    subtitle: 'العنوان الفرعي',
    countryAndCity: 'الدولة / المدينة',
    category: 'القسم',
    description: 'الوصف',
    specifications: 'المواصفات',
    gallery: 'معرض الصور',
    noSpecifications: 'لم تتم إضافة مواصفات لهذا الإعلان.',
    draft: 'مسودة',
    published: 'منشور',
    rejected: 'مرفوض',
    sold: 'مباع',
    add: "إضافة",
    admin: "مدير",
    permission: "صلاحية",
    role: "دور",
    subCategory: "قسم فرعي",
    university: "جامعة",
    legalAffair: "شأن قانوني",
    contactReason: "سبب تواصل",
    paymentMethod: "طريقة دفع",
    coupon: "كوبون",
    total: "الإجمالي",
    reviewed: "تمت المراجعة",
    dismissed: "مرفوض",
    approved: "مقبول",
    usedUp: "مستنفد",
    default: "افتراضي",
    deletedSuccessfully: "تم الحذف بنجاح.",
    savedSuccessfully: "تم الحفظ بنجاح.",
    statusUpdatedSuccessfully: "تم تحديث الحالة بنجاح.",
    adDeleted: "تم حذف الإعلان",
    adOwner: "صاحب الإعلان",
    reporterExplanation: "شرح المُبلِّغ",
    reportedAd: "الإعلان المُبلَّغ عنه",
    relatedAd: "الإعلان المرتبط",
    openAd: "فتح الإعلان",
    noMessagesInConversation: "لا توجد رسائل في هذه المحادثة.",
    welcomeToDashboard: "مرحباً بك في لوحة التحكم!",
    rememberMe: "تذكرني",
    forgotPassword: "نسيت كلمة المرور؟",
    profit: "الأرباح",
    order: "الطلبات",
    topic: "الموضوع",
    estimatedAudience: "الجمهور المتوقع",
    firebase: "Firebase",
    userId: "معرّف المستخدم",
    linkOptional: "الرابط (اختياري)",
    history: "السجل",
    titleAndBodyRequired: "العنوان والنص مطلوبان.",
    userIdRequiredForSingle: "معرّف المستخدم مطلوب لإشعار مستخدم واحد.",
    newCoupon: "كوبون جديد",
    maxDiscountOptional: "أقصى خصم £ (اختياري)",
    minSpendOptional: "أقل قيمة شراء £ (اختياري)",
    totalUsesHint: "إجمالي الاستخدامات (فارغ = غير محدود)",
    startsAtOptional: "يبدأ في (اختياري)",
    expiresAtOptional: "ينتهي في (اختياري)",
    redemptions: "مرات الاستخدام",
    couponNotUsedYet: "لم يُستخدم هذا الكوبون بعد.",
    managePlatformSettings: "إدارة إعدادات المنصة وبيانات التواصل.",
    loadingSettings: "جارٍ تحميل الإعدادات...",
    noChangesToSave: "لا توجد تغييرات للحفظ.",
    settingsUpdated: "تم تحديث الإعدادات بنجاح.",
    noDomainsAdded: "لم تتم إضافة نطاقات بعد.",
    stateHint: "الولاية (مثال: CA)",
    domainHint: "مثال: harvard.edu",
    universityNameRequired: "اسم الجامعة مطلوب.",
    atLeastOneTranslation: "مطلوب ترجمة واحدة على الأقل.",
    passwordRequired: "كلمة المرور مطلوبة.",
    passwordConfirmationMismatch: "تأكيد كلمة المرور غير مطابق.",
    permissionNameRequired: "اسم الصلاحية مطلوب.",
    userVerificationDetails: "تفاصيل طلب التوثيق",
    allRequests: "كل الطلبات",
    latestVerificationRequest: "أحدث طلب توثيق",
    noVerificationRequest: "لا يوجد طلب توثيق لهذا المستخدم.",
    allUserVerificationRequests: "كل طلبات التوثيق",
    operationsCity: "مدينة العمل",
    preferredContact: "وسيلة التواصل المفضلة",
    offersSummary: "ملخص العروض",
    viewVerificationDetails: "عرض تفاصيل التوثيق",
    deviceSessions: "جلسات الأجهزة",
    favoriteAds: "الإعلانات المفضلة",
    userAds: "إعلانات المستخدم",
    dashboards: "لوحات التحكم",
    editCoupon: "تعديل الكوبون",
    couponOncePerUserNote: "يمكن استخدام كل كوبون مرة واحدة فقط لكل مستخدم — يُطبَّق هذا الحد تلقائياً.",
    deleteConfirmation: "سيتم حذف هذا العنصر نهائياً. هل تريد المتابعة؟",
    deleteUniversityConfirmation: "سيتم حذف هذه الجامعة ونطاقاتها نهائياً. هل تريد المتابعة؟",
    actionFailed: "تعذّر إتمام العملية.",
    newPasswordOptional: "كلمة مرور جديدة (اختياري)",
    deleteAdConfirmation: "سيتم حذف هذا الإعلان نهائياً. هل تريد المتابعة؟",
    verificationRequest: "طلب توثيق",
    business: "شركة",
    service_provider: "مقدّم خدمة",
    organization_type: "مؤسسة",
    website: "موقع إلكتروني",
    updatedSuccessfully: "تم التحديث بنجاح.",
    createdSuccessfully: "تم الإنشاء بنجاح.",
    passwordTooShort: "يجب ألا تقل كلمة المرور عن {min} أحرف.",
    passwordConfirmationRequired: "يرجى تأكيد كلمة المرور.",
    roleRequired: "يرجى اختيار دور لهذا المشرف.",
    deleteAdminConfirmation: "سيتم حذف هذا المشرف نهائياً. هل تريد المتابعة؟",
    view: "عرض",
    create: "إنشاء",
    update: "تعديل",
    page: "الصفحة",
    rolePermissions: "صلاحيات الدور",
    noPermissionsAssigned: "لا توجد صلاحيات مسندة.",
    roleNameRequired: "يرجى إدخال اسم الدور.",
    rolePermissionsRequired: "يرجى اختيار صلاحية واحدة على الأقل.",
    deleteRoleConfirmation: "سيتم حذف هذا الدور نهائياً. هل تريد المتابعة؟",
    permissionsSelected: "محددة",
    countryCodeRequired: "رمز الدولة مطلوب.",
    countryCodeLength: "يجب أن يتكون رمز الدولة من حرفين بالضبط.",
    nameRequiredAnyLanguage: "أدخل الاسم بلغة واحدة على الأقل.",
    countryRequired: "يرجى اختيار الدولة.",
    cityCodeLength: "يجب ألا يتجاوز رمز المدينة 50 حرفاً.",
    nameInLanguage: "الاسم",
    currentImage: "الصورة الحالية",
    noImage: "لا توجد صورة",
    changeImage: "تغيير الصورة",
    languageCodeRequired: "رمز اللغة مطلوب.",
    languageCodeLength: "يجب ألا يتجاوز رمز اللغة {max} أحرف.",
    titleRequired: "العنوان مطلوب.",
    isActiveQuestion: "حالة التفعيل",
    loginFailed: "فشل تسجيل الدخول",
    nameArRequired: "الاسم بالعربية مطلوب.",
    nameEnRequired: "الاسم بالإنجليزية مطلوب.",
    slugRequired: "المُعرّف (slug) مطلوب.",
    imageRequired: "الصورة مطلوبة.",
    viewImage: "عرض الصورة",
    allUsers: "جميع المستخدمين",
    singleUser: "مستخدم واحد",
    selectUser: "اختر المستخدم",
    deletePaymentMethodConfirmation: "سيتم حذف طريقة الدفع هذه نهائياً. هل تريد المتابعة؟",
    sending: "جارٍ الإرسال...",
    notificationSent: "تم إرسال الإشعار.",
    firebaseNotConfigured: "الإشعارات معطّلة: لم يتم إعداد Firebase على الخادم.",
    searchUser: "ابحث بالاسم أو البريد الإلكتروني",
    noUsersFound: "لا يوجد مستخدمون",
    nameRequired: "الاسم مطلوب.",
    emailRequired: "البريد الإلكتروني مطلوب.",
    emailInvalid: "أدخل بريداً إلكترونياً صحيحاً، مثال: name@example.com",
    paymentUnsettledShort: "الرسوم غير مدفوعة",
    menu: "القائمة",
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
