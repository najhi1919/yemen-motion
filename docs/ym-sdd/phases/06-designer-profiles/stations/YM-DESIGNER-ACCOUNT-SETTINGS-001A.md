YM-DESIGNER-ACCOUNT-SETTINGS-001A — إعدادات الحساب العامة
الحقل	القيمة
Station ID	YM-DESIGNER-ACCOUNT-SETTINGS-001A
الحالة	design-contract-only / not-implemented
المرحلة التابعة	separate scope — خارج دمج Phase 6 (DP-DEC-012)
نوع الملف	Station (يشير إلى Spec)
تاريخ الفتح	— (لم تُفتح للتنفيذ بعد)
تاريخ الإغلاق	—
Current step	design contract documented — awaiting future implementation station
الهدف

توفير صفحة إعدادات حساب موحدة وآمنة لكل مستخدمي المنصة (مستخدم عادي، مصمم، مدير)، مع فصل تام بين Account Identity وDesigner Professional Profile، وحماية العمليات الحساسة بـcurrent_password، ومنح مهلة 30 يومًا قبل الحذف النهائي.
In Scope (وفق العقد)

    صفحة إعدادات موحدة لكل المستخدمين.
    name + username + email + password + phone (اختياري وخاص).
    Account avatar + Account cover.
    اللغة + المظهر (system / light / dark).
    التعطيل الذاتي + إعادة التنشيط + طلب الحذف النهائي.
    Grace Period مدتها 30 يومًا.
    Limited Flow لطلب الحذف للحسابات الموقوفة إداريًا.
    فصل تام عن Designer Profile وعن صلاحيات Admin.

Out of Scope (مؤجل عمدًا)

    واجهة إدارة الجلسات والأجهزة.
    2FA (المصادقة الثنائية).
    إعدادات الإشعارات التفصيلية.
    Data Export.
    Login / Recovery / Verification بالهاتف وSMS.
    إعدادات المراسلات.
    دمج خصائص الملف المهني للمصمم داخل Account Settings.
    Publication controls.
    صلاحيات الإدارة العليا.
    Billing.
    Service Requests.

القرارات المعتمدة

    DP-DEC-012 (approved 2026-08-01): إعدادات الحساب محطة مستقلة ولا تدمج داخل نشر الملف، فصلًا لأمن الحساب عن lifecycle العرض العام.
    DESIGNER_PROFILES_UX_CONTRACT.md: إحالة صريحة إلى المحطة كمحطة مؤجلة منفصلة.

العقد التفصيلي

العقد الكامل (37 قسمًا) موثق في:

docs/ym-sdd/specs/YM-DESIGNER-ACCOUNT-SETTINGS-001A.spec.md

ويشمل: V1 UX / Scope / Data Model / Account Identity / Username Claim/Change/Confirmation/Redirect / Pending Email UX / Email Finalization Safety / Avatar / Cover / Password / Phone / Locale / Appearance / Privacy / Self-Deactivation / Admin Disable / Account State Precedence / Reactivation / Deletion / Admin-disabled Deletion Flow / Grace Period / Cancel Deletion State Restoration / Blocked Deletion / Data Retention / Audit Privacy / No-op / Media Lifecycle / API Boundary / Frontend IA / Migration / Expected Implementation Scope / Explicit OUT OF SCOPE / Final Recommendation.
الحالة

     العقد معتمد بالكامل (APPROVED).
     جميع قرارات UX موثّقة (لا قرارات معلقة).
     لم يبدأ التنفيذ (Backend / Frontend / Migration).
     لا يجوز فتح المحطة للتنفيذ دون قرار صريح منفصل.

الحدود

     لا يجوز البدء في التنفيذ دون فتح محطة تنفيذ رسمية منفصلة.
     لا يجوز تغيير Station ID YM-DESIGNER-ACCOUNT-SETTINGS-001A أو إعادة تسميته.
     لا يجوز دمج أي جزء من هذه المحطة داخل محطة أخرى.
     لا يجوز توسيع Scope ليشمل البنود المؤجلة (Out of Scope) دون قرار صريح.

المحطة التالية

غير معيّنة. هذه المحطة تبقى design-contract-only / not-implemented حتى قرار صريح بفتحها للتنفيذ في المستقبل.
سجل زمني Append-only
التاريخ

الحدث

الدليل

الحالة
2026-08-01	اعتماد DP-DEC-012 الذي يفصل المحطة كنطاق مستقل.	DECISIONS.md	separate scope
2026-08-01	إحالة DESIGNER_PROFILES_UX_CONTRACT.md إلى المحطة كمحطة مؤجلة.	docs/design/DESIGNER_PROFILES_UX_CONTRACT.md	deferred
2026-08-08	التوثيق الرسمي للعقد الكامل (37 قسمًا) في Spec file منفصل.	docs/ym-sdd/specs/YM-DESIGNER-ACCOUNT-SETTINGS-001A.spec.md	design-contract-only / not-implemented
