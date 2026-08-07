YM-DESIGNER-ACCOUNT-SETTINGS-001A — إعدادات الحساب العامة (Global Account Settings V1)
الحقل

القيمة
Station ID	YM-DESIGNER-ACCOUNT-SETTINGS-001A
نوع الملف	Spec — Design Contract
الحالة	DESIGN CONTRACT ONLY / NOT IMPLEMENTED
المرحلة التابعة	separate scope — خارج دمج Phase 6 (DP-DEC-012)
تاريخ الاعتماد	2026-08-08 (تاريخ التوثيق الرسمي في المستودع)
القرار المعماري المرجعي	DP-DEC-012 (approved 2026-08-01)
عقد UX المرجعي	docs/design/DESIGNER_PROFILES_UX_CONTRACT.md
سياسة اسم المستخدم المرجعية	docs/design/USERNAME_POLICY.md




    تنبيه: هذا ملف عقد تصميم (Design Contract) فقط. لا يجوز البدء في التنفيذ (Backend / Frontend / Migration) دون فتح محطة تنفيذ رسمية منفصلة. القرارات أدناه كلها APPROVED ولا توجد قرارات UX معلقة.

1. Contract Status
text




DESIGN CONTRACT ONLY
NOT IMPLEMENTED


2. V1 UX

توفر النسخة الأولى تجربة مستخدم مركزية وآمنة عبر صفحة «الإعدادات» لجميع مستخدمي المنصة: مستخدم عادي، مصمم، مدير.

     تنقل جانبي واضح في Desktop.
     تجربة ملائمة في Mobile.
     إعدادات الحساب منفصلة تمامًا عن إعدادات الملف المهني للمصممين.
     منفصلة أيضًا عن الصلاحيات الإدارية للمدراء.
     الهدف هو استقلال وأمن الحساب الشخصي.

3. Scope

النطاق المعتمد في V1:

     واجهة إعدادات حساب موحدة.
     الاسم الشخصي name.
     صورة الحساب Account avatar.
     غلاف الحساب Account cover.
     إدارة username لجميع المستخدمين.
     تغيير البريد الإلكتروني.
     تغيير كلمة المرور.
     الهاتف — اختياري وخاص.
     اللغة.
     المظهر.
     التعطيل الذاتي للحساب.
     إعادة التنشيط.
     طلب الحذف النهائي.
     Grace Period لمدة 30 يومًا.
     Limited Flow لطلب الحذف للحسابات الموقوفة إداريًا.

4. Deferred

خارج V1:

     واجهة كاملة لإدارة الجلسات والأجهزة.
     2FA (المصادقة الثنائية).
     إعدادات الإشعارات التفصيلية.
     Data Export.
     Login بالهاتف.
     Account Recovery بالهاتف.
     SMS Verification.
     إعدادات المراسلات.
     دمج خصائص الملف المهني للمصمم داخل Account Settings.

5. Data Model
جدول users

يحتفظ بهوية الحساب الأساسية والحالات الأمنية:
text




name
username
email
pending_email
password
disabled_at
deactivated_at
deletion_requested_at


جدول account_settings

علاقة One-to-One مع المستخدم، ويعزل التفضيلات والوسائط عن Auth:
text




user_id
avatar_path
cover_path
phone
locale
appearance


جدول username_history

لإدارة الأسماء السابقة وإعادة التوجيه وحمايتها:
text




user_id
username
changed_at
expires_at


6. Account Identity

هناك فصل جذري بين:
text




Account Identity



و:
text




Designer Professional Profile



لذلك:

     لا تدمج حقول DesignerProfile داخل Account Settings.
     لا تدمج وسائط الملف المهني داخلها.
     المصمم يحصل داخل Settings فقط على Navigation links لإدارة ملفه المهني.
     المستخدم العادي لديه Account Identity فقط.

7. Initial Username Claim

إذا لم يكن للمستخدم username مسبقًا:

     تظهر العملية باسم «اختيار اسم مستخدم».
     أول اختيار لا يحتسب ضمن Change Quota.
     Change Quota هي مرتان خلال 90 يومًا.
     تطبق USERNAME_POLICY بالكامل، بما في ذلك:
         الأحرف.
         الأسماء المحجوزة.
         الأسماء المميزة.
         Availability.
         Safe reservation.
         Endpoint rate limiting.

8. Username Change

إذا كان لدى المستخدم username بالفعل:

     تظهر العملية باسم «تغيير اسم مستخدم».
     الحد الأقصى: مرتان خلال 90 يومًا.
     الحجز النهائي يتم داخل Transaction.
     العملية تتطلب current_password.

9. Username Confirmation

القرار المعتمد هو الخيار C:

المستخدم يكتب الـusername الجديد مرة واحدة فقط.

بعدها تظهر Confirmation Modal تحتوي:

     الاسم الحالي.
     الاسم الجديد.
     الرابط العام الجديد للمصمم، لكن فقط إذا كان لديه Designer Profile فعلًا.
     المستخدم العادي لا يرى Public Profile URL حتى لا نوحي بأن لديه ملفًا عامًا.
     تنبيه بأن الحد هو مرتان خلال 90 يومًا.
     تنبيه بأن الاسم السابق سيحمى 180 يومًا.
     الاسم القديم يعاد توجيهه خلال مدة الحماية.
     لا يستطيع شخص آخر أخذه أثناء الحماية.
     المستخدم يدخل current_password لتأكيد العملية.
     لا يطلب منه إعادة كتابة الـusername الجديد.

10. Temporary Redirect

الاسم السابق:

     يحفظ في username_history.
     مدة الحماية: 180 يومًا.
     الرابط القديم يستخدم 301 Permanent Redirect، لأن Alias ينتهي بعد 180 يومًا.

11. Pending Email UX

إذا كان هناك تغيير بريد قيد التحقق، تعرض Settings:



    لديك تغيير بريد إلكتروني قيد التحقق

والخيارات:

    إعادة إرسال رسالة التحقق.
    إلغاء طلب تغيير البريد.
    استبدال البريد المقترح ببريد جديد.

الخيار الثالث:

     يتطلب current_password مرة أخرى.
     يلغي الطلب السابق.
     يلغي Tokens التحقق السابقة.
     يبدأ Pending Email جديدًا.

12. Email Finalization Safety

إذا انتهت صلاحية Verification Link:

     لا يتضرر الحساب.
     البريد الجديد لا يعتمد.
     البريد القديم لا يحذف.
     يمكن إرسال Verification جديد إذا كان Pending state لا يزال صالحًا.

عند Finalization:

     العملية داخل Transaction / Atomic Operation.
     يعاد فحص أن البريد الجديد لا يزال Available.
     الهدف منع Race Conditions.
     إذا أصبح البريد محجوزًا أثناء المعاملة:
         يظهر خطأ واضح.
         يبقى البريد القديم فعالًا.

13. Account Avatar / Cover

كل مستخدم، حتى غير المصمم، لديه:
text




Account Avatar
Account Cover



لكن:

     هما Internal Account Identity Surface.
     يظهران في رأس Settings للمستخدم نفسه.
     لا يصبح المستخدم العادي Public Profile بسببهما.
     لا ينشأ Public User URL.
     منفصلان بالكامل عن:
         Designer Profile avatar.
         Designer Profile cover.
     لا يحدث Auto Sync.
     لا توجد في V1 ميزة «استخدام صورة الحساب كصورة الملف المهني».
     Raw Storage Paths لا تعرض أبدًا.

14. Password

تغيير كلمة المرور يحتاج:
text




current_password
new_password
new_password_confirmation



القرار المعتمد:

لا يتم تسجيل خروج الأجهزة الأخرى تلقائيًا بمجرد تغيير كلمة المرور.

بل يظهر خيار منفصل:



    تسجيل الخروج من جميع الأجهزة الأخرى

وعند اختياره:

     تحذف Sanctum Tokens للأجهزة الأخرى.
     تبقى الجلسة الحالية آمنة.

15. Phone

الهاتف:

     اختياري.
     Private.
     لا يظهر للعامة.
     لا يستخدم في V1 للـLogin.
     لا يستخدم SMS Verification.
     لا يستخدم Recovery.
     نحفظ البنية فقط لتوسعة مستقبلية.
     لا نخترع حالة verified غير موجودة فعليًا.

16. Locale / Appearance
Locale

يقتصر على اللغات المدعومة فعليًا في الواجهة.
Appearance

الخيارات:
text




system
light
dark



وتحفظ التفضيلات في الحساب حتى تنتقل مع المستخدم عبر الأجهزة.
17. Privacy

البريد والهاتف:

     Private افتراضيًا.
     لا Toggles وهمية لا يكون لها أثر حقيقي في Backend.

18. Self-Deactivation

التعطيل الذاتي:
text




deactivated_at



وهو منفصل كليًا عن:
text




disabled_at



الذي يمثل Admin Disable.

التعطيل الذاتي:

     لا يحذف البيانات.
     لا يعدل حالات publication.
     لا يعدل قيم show_publicly الأصلية.
     يعمل كـEffective Visibility Gate.
     يخفي أثناء التعطيل:
         Designer Profile.
         Works.
         Organization.

19. Admin Disable

إذا:
text




disabled_at != null



فهذه الحالة أقوى من Self-Deactivation.

المستخدم:

     لا يدخل التطبيق.
     لا يستطيع فك التعطيل بنفسه.
     لا يستطيع Self Reactivation.
     لا يدخل Settings العادية.
     يسمح له فقط بـLimited Account-State Flow محدد جدًا بعد إثبات كلمة المرور.

20. Account State Precedence

الأولوية المعتمدة:
الأولوية

الحالة

السلوك
1 — الأعلى	Administrative Disable	منع كامل، لا Self Reactivation، Limited deletion flow فقط
2	Deletion Pending / Blocked	لا استخدام عادي، Limited cancel-deletion flow
3	Self-Deactivated	المحتوى مخفي، يحتاج Reactivation
4 — الأقل	Active	استخدام طبيعي


Admin Disable وDeletion Pending لا يمكنهما الإزالة المتبادلة، ولا يمكن لـSelf Reactivation إزالة Admin Disable.
21. Reactivation

إذا كان الحساب Self-deactivated فقط، ثم دخل المستخدم ببيانات صحيحة:

     لا يدخل التطبيق مباشرة.
     تظهر شاشة:



    حسابك معطل مؤقتًا. هل تريد إعادة تنشيطه؟

بعد التأكيد:

     يزال deactivated_at.
     يسجل الحدث.
     يسمح بالدخول.
     تعود حالة رؤية المحتوى السابقة.

22. Deletion

طلب حذف الحساب:

     يتطلب current_password.
     يحدد حالة الحذف.
     يبدأ Grace Period = 30 يومًا.
     يسجل خروج المستخدم من جميع الجلسات.
     تصبح بياناته غير مرئية للعامة.
     لا يحدث Final Deletion فورًا.

23. Admin-disabled Deletion Flow

حتى المستخدم الموقوف إداريًا يمكنه طلب الحذف عبر Limited Account-State Flow.

لكن:

     يحتاج Credentials.
     يحتاج current_password.
     لا يحصل على Full Auth Session.
     لا يدخل التطبيق.
     لا يفك Admin Disable.
     نفس Grace Period = 30 يومًا.

24. Grace Period

مدتها:
text




30 days



إذا حاول المستخدم الدخول ببيانات صحيحة أثناءها، سواء كان:

     Active سابقًا.
     Self-Deactivated.
     Administratively Disabled.

تظهر شاشة:



    حسابك مجدول للحذف في: [التاريخ]

والخيارات:

     إلغاء حذف الحساب واستعادته
     الاستمرار في الحذف (لا تنشأ جلسة، لا يدخل التطبيق).

25. Cancel Deletion State Restoration

عند إلغاء الحذف:
Administratively Disabled

     يلغي طلب الحذف فقط.
     يبقى Admin Disable.
     لا يعاد تنشيط الحساب.

Self-Deactivated قبل الحذف

     يعود إلى Self-Deactivated.
     لا يصبح Active تلقائيًا.

Active قبل الحذف

     يعود Active.

التنفيذ يجب أن يحتفظ بالحالات السابقة أثناء deletion_requested_at بحيث لا يؤدي Cancel Deletion إلى فقدان الحالة الأصلية.
26. Blocked Deletion

بعد 30 يومًا يأتي Final Deletion evaluation.

قد توجد التزامات حقيقية تمنع الحذف، مثل:
text




Legal Hold
Security Hold
Open Dispute



عند وجود Blocker:

     Deletion Request يبقى قائمًا.
     الحالة تصبح:

text




Deletion Pending — Blocked by Active Obligations



     الحساب يبقى غير نشط.
     المحتوى يبقى غير عام.
     الاستخدام يبقى ممنوع.
     لا نخترع Legal schema معقدًا غير موجود.
     Final Deletion ينتظر زوال Blocker.
     بعد زواله تنفذ المعالجة في دورة لاحقة.
     يمكن إرسال Security Email يشرح سبب التعليق.

27. Data Retention

عند Final Deletion الفعلي:

     البيانات الشخصية غير المطلوبة تزال.
     Account media تنظف.
     DesignerProfile / Organization / Works لا تبقى عامة.
     السجلات التشغيلية الضرورية لا تحذف بطريقة تكسر Foreign Keys، مثل:
         financial records.
         orders.
         Audit.
         Security records.

المبدأ:
text




Minimal Retained Record



مع:
text




Anonymization / Identity Detachment



وفق ما يسمح به Source الفعلي.
28. Audit Privacy

ممنوع قطعيًا تخزين القيم الكاملة التالية في Audit metadata:
text




password
current_password
email
pending_email
phone
name
avatar_path
cover_path
tokens
verification tokens



كذلك:

     username history لا يكرر بالكامل داخل Audit.
     username_history هو المصدر المتخصص له.

يسجل Audit بدلًا من ذلك:

     actor/user identity.
     operation type مثل:
         email_change_requested
         password_changed
     Changed Flags.
     Account-state Transition Type.
     بعض display preferences غير الحساسة مثل:
         previous_locale.

29. No-op

للحقول:
text




name
phone
locale
appearance



بعد Normalization:

إذا كانت القيمة الجديدة = الحالية:

     لا DB write.
     لا تحديث غير ضروري لـupdated_at.
     لا Audit mutation event.
     Response success:

json




{
  "changed": false
}


30. Media Lifecycle

دورة حياة Account media:

    Store new.
    Update DB field + Commit.
    عند نجاح DB: حذف الملف القديم بأمان.
    عند فشل DB: حذف الملف الجديد والإبقاء على القديم.
    إذا فشل cleanup سجل الخطأ.

العملية الأصلية تظل Successful.
31. API Boundary

تقسم الـAPI إلى ثلاث فئات:
Authenticated Endpoints

تحتاج:
text




auth:sanctum
Full Auth Session



للإعدادات الطبيعية.
Sensitive Endpoints

تحتاج current_password، مثل:

     username.
     password.
     email.
     deletion.

Limited Pre-auth Account-State Endpoints

للحسابات التي لا يسمح لها Full Auth Session، مثل:

     Deletion Pending.
     Self-Deactivated.
     Administratively Disabled.

وتستخدم فقط لأفعال محددة وآمنة مثل:

     Cancel Deletion.
     Reactivation.
     Admin-disabled deletion request.

ولا يجوز استخدامها كـSecurity Bypass.
32. Frontend IA

شاشة Settings مقسمة إلى:
1. الملف الشخصي / الحساب
text




account avatar
account cover
personal name
username
optional phone


2. الأمان وتسجيل الدخول
text




email change
password change


3. التفضيلات
text




language
appearance


4. حالة الحساب
text




self-deactivation
deletion


للمصمم فقط

تظهر Navigation Links إلى:

     إدارة الملف المهني (Designer Profile).
     إدارة الأعمال.

ولا نكرر خصائص المصمم داخل Account Settings.
33. Migration / Data Safety

مطلوب عند التنفيذ:
إنشاء
text




account_settings
username_history


إضافة إلى users
text




pending_email
deactivated_at
deletion_requested_at



ويجب تجنب كسر designer_profiles أو بيانات المصمم عند تعديل الحساب أو طلب حذفه، إلى أن يصل النظام إلى Final Deletion المنظم.
34. Expected Implementation Scope

مرحلة التنفيذ المستقبلية متوقع أن تشمل:

     Database migrations.
     Models لـAccount Settings.
     Model/Entity لـUsername history.
     Controllers تفصل العمليات الطبيعية عن الأمنية.
     Frontend Settings page.
     Reactivation screens.

وكل ذلك بناءً على هذا العقد حصريًا.
35. Explicit OUT OF SCOPE

يبقى خارج V1 صراحة:

     Device/Session Management UI.
     2FA.
     Notification preferences التفصيلية.
     Data Export implementation.
     Public profile لغير المصممين.
     Phone Auth.
     Phone Verification.
     Phone Recovery.
     Messaging preferences.
     أي Designer professional data.
     أي Designer professional media.
     Publication controls.
     صلاحيات الإدارة العليا.
     Billing.
     Service Requests.

كما أن القرار هو:



    لا يتم فتح Station جديد ولا يعاد تسمية YM-DESIGNER-ACCOUNT-SETTINGS-001A.

36. Pending Decisions

لا توجد قرارات معلقة.

النص النهائي للعقد يقول صراحة إن:



    جميع خيارات UX والمخططات تمت الموافقة عليها وثُبتت كـAPPROVED في مخرجات هذا العقد.

37. Final Recommendation

العقد يعتمد الركائز الأساسية التالية:

     تعميم username كجزء من Account Identity.
     فصل Account Identity عن Designer Professional Identity.
     No-op للعمليات التي لا تغير شيئًا.
     حماية العمليات الحساسة بـcurrent_password.
     Grace Period للحذف دون الإضرار بسلامة البيانات.
     Audit لا يسرب البيانات الحساسة.

وثائق مرتبطة

     قرار DP-DEC-012 — إعلان استقلال المحطة.
     عقد UX للمصممين — الإحالة إلى المحطة.
     سياسة أسماء المستخدمين — المرجع الملزم لقواعد username.

سجل التوثيق
التاريخ

الحدث

الملاحظة
2026-08-01	اعتماد DP-DEC-012 الذي يفصل المحطة كنطاق مستقل.	القرار المعماري المرجعي.
2026-08-08	التوثيق الرسمي للعقد الكامل في المستودع.	تحويل المعرفة المعتمدة إلى Spec file رسمي. الحالة: DESIGN CONTRACT ONLY / NOT IMPLEMENTED.
