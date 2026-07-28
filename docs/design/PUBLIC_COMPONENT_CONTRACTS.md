# عقود المكونات العامة لمنصة Yemen Motion

**الحالة:** Approved
**النطاق:** مكونات الواجهات الخارجية العامة والتشغيلية

## Decision

تعتمد المكونات الخارجية العقود التالية بوصفها واجهة سلوكية وبصرية ووصولية
موحدة. تبقى منفصلة شكليًا عن مكونات Admin حتى عند مشاركة منطق أوأدوات
تقنية.

## Rationale

تمنع العقود اختلاف الحالات والتفاعل بين الصفحات، وتجعل RTL والهاتف والوصول
جزءًا من تعريف المكون.

## Scope

تطبق العقود على Public Shell وWorkspace Shell وFocused Shell المحددة في
[`YEMEN_MOTION_PUBLIC_VISUAL_SYSTEM.md`](YEMEN_MOTION_PUBLIC_VISUAL_SYSTEM.md).

## Approved Behavior

### قواعد مشتركة

- تشمل الحالة المطلوبة عند ملاءمتها: `default` و`hover` و`focus-visible`
  و`active` و`disabled` و`loading` و`error`.
- لا تعتمد وظيفة على Hover وحده.
- لا يستخدم `div` بدل `button` أو`a`.
- يبلغ هدف اللمس على الهاتف `44×44px` على الأقل.
- يكون Primary button واحدًا غالبًا داخل مجموعة إجراءات.
- لا يستخدم Danger لون Brand Red.
- تحفظ بيانات المستخدم عند خطأ التحقق أوفشل الشبكة.
- تطبق CSS Logical Properties، ويختبر كل مكون في RTL وLTR.
- يتغير التركيب على الهاتف عند الحاجة؛ لا يصغر Desktop فقط.

### 1. Actions and Inputs

| Component | Purpose | Variants / Sizes | Required states | Accessibility وRTL/Mobile | Usage constraints |
|---|---|---|---|---|---|
| Button | تنفيذ إجراء نصي واضح | primary، secondary، tertiary، danger؛ sm، md، lg | جميع الحالات المشتركة، وerror غير مطلوب عادة | عنصر `button`، اسم واضح، Spinner معلن دون تغيير العرض؛ الأيقونة تتبع inline direction | Primary واحد غالبًا؛ Danger مستقل عن Brand Red |
| IconButton | إجراء مختصر بأيقونة | neutral، primary، danger؛ sm، md، lg | جميع الحالات عدا error غالبًا | `aria-label` إلزامي، Tooltip مساعد، `44×44px` على الهاتف | لا يستخدم لأفعال غامضة أوعدة معانٍ |
| Input | إدخال نص قصير | text، email، password، url؛ md افتراضي | المشترك مع error وread-only عند الحاجة | Label وربط الوصف والخطأ؛ `dir=auto` للمحتوى المختلط؛ `16px` على الهاتف | Placeholder مثال مساعد وليس بديلًا عن Label |
| Textarea | نص متعدد الأسطر | fixed، auto-grow؛ md، lg | المشترك مع error | Label وعداد معلن عند وجود حد؛ يبقى اتجاه واجهة الحقل منطقيًا | لا يستخدم لمعلومة منظمة قابلة للاختيار |
| Select | اختيار واحد من قائمة صغيرة | default، compact؛ md | المشترك مع error | عنصر native عند ملاءمته أوعقد مكافئ كامل؛ اتجاه القائمة حسب اللغة | القوائم الكبيرة تتحول إلى Searchable Combobox |
| Combobox | بحث واختيار من مجموعة كبيرة | single، multi | المشترك مع open، no-results، error | عقد ARIA Combobox، تنقل لوحة المفاتيح، إعلان النتائج؛ Overlay مناسب للهاتف | المهارات تستخدم Combobox + Chips |
| Checkbox | اختيارات مستقلة متعددة | unchecked، checked، indeterminate؛ md | المشترك مع error | Label قابل للنقر وإعلان indeterminate؛ ترتيب منطقي في RTL | لا يستخدم بدل Switch لإجراء فوري |
| Radio | اختيار واحد من مجموعة قصيرة | default، card radio؛ md | المشترك مع error | `fieldset` و`legend` وتنقل الأسهم | لا يستخدم لقائمة طويلة أوبحثية |
| Switch | تبديل إعداد ثنائي فوري | default؛ md، lg | المشترك مع pending وerror | يعلن الاسم والحالة؛ يتبع inline direction دون قلب معنى On/Off | لا يستخدم لتأكيد خطير أوحفظ متعدد الحقول |
| SegmentedControl | تبديل عرض أوقيمة قصيرة متبادلة | 2–4 segments؛ sm، md | المشترك | دلالة radio group أوtabs بحسب الوظيفة؛ يسمح بالتمرير المحكوم على الهاتف | لا يتجاوز أربع قيم ولا يستبدل Tabs المعقدة |
| SearchField | بحث مباشر أوهجين | compact، standard، prominent | المشترك مع searching، results، no-results، error | Label مرئي أومخفي وصوليًا حسب السياق، زر مسح مسمى، Enter مدعوم | debounce بين `300–400ms`؛ يحفظ البحث في URL حيث يلزم |
| FileUpload | اختيار ورفع ملف | single، multiple، dropzone | idle، hover، focus-visible، selected، uploading، success، error، disabled | Input file حقيقي، تعليمات النوع والحجم، تقدم وأخطاء معلنة؛ زر اختيار واضح على الهاتف | Drag-and-drop إضافة وليس المسار الوحيد؛ لا تفقد الاختيار عند خطأ قابل للتصحيح |

### 2. Surfaces and Identity

| Component | Purpose | Variants / Sizes | Required states | Accessibility وRTL/Mobile | Usage constraints |
|---|---|---|---|---|---|
| Card | تجميع محتوى مترابط | standard، dark identity، statistics، action؛ responsive | default، hover/focus عند التفاعل، loading، error | يستخدم عنصرًا دلاليًا مناسبًا؛ الترتيب مقروء في الاتجاهين | Hover للتفاعلي فقط؛ يمنع Card داخل Card بإفراط |
| WorkCard | عرض عمل دون حجب الوسائط | standard، featured، compact؛ ratios حسب الوسيط | default، hover، focus-visible، loading، error، hidden/review عند الصلاحية | عنوان وربط واضحان، بديل للصورة، إجراءات قابلة للوحة المفاتيح | لا Overlay ثقيل يخفي العمل؛ لا تفرض Filters على العمل |
| DesignerCard | نتيجة استكشاف مختصرة للمصمم | standard، compact، featured | default، hover، focus-visible، loading، unavailable | الاسم والمسمى والتخصص مقروءة؛ ترتيب الاتجاه منطقي | ليست ملفًا مصغرًا مزدحمًا؛ لا تعرض بيانات خاصة |
| Badge | حالة قصيرة أوتصنيف | neutral، info، success، warning، danger، brand؛ sm، md | default؛ interactive states فقط إذا كانت قابلة للإجراء | نص أوأيقونة مع النص؛ اللون ليس الناقل الوحيد | لا تستخدم أعداد كبيرة من Badges ولا تستخدم Brand للدلالة على Danger |
| Avatar | تمثيل شخص أوهوية | image، initials، fallback؛ sm–xl | default، loading، error، presence عند الحاجة | alt مناسب أوزخرفي حسب السياق؛ لا ينعكس في RTL | دائري للقوائم، وSquircle للهوية البارزة |

### 3. Navigation and Shell

| Component | Purpose | Variants / Sizes | Required states | Accessibility وRTL/Mobile | Usage constraints |
|---|---|---|---|---|---|
| Tabs | تنقل بين أقسام متقاربة | line، contained؛ responsive | default، hover، focus-visible، active، disabled، loading | ARIA Tabs وتنقل الأسهم؛ تمرير أفقي مضبوط على الهاتف | لا تستخدم لتدفق خطوات أووجهات غير مترابطة |
| Breadcrumb | إظهار المسار الهرمي | standard، compact | default، hover، focus-visible، current | `nav` مع label، و`aria-current`؛ الفواصل زخرفية وتتبع الاتجاه | يختصر الوسط على الهاتف دون إخفاء الصفحة الحالية |
| PublicHeader | تنقل الزائر وهوية العلامة | marketing، standard، compact | default، scrolled، menu-open | `header/nav` وSkip target وتركيز مضبوط؛ قائمة Drawer على الهاتف | لا يحمل أدوات Admin أوكثافة Workspace |
| WorkspaceHeader | سياق وإجراءات مساحة العمل | standard، compact | default، scrolled، menu-open، loading | عنوان صفحة دلالي وإجراءات بلوحة المفاتيح؛ يدعم قائمة الهاتف | لا يكرر كل عناصر Sidebar |
| WorkspaceSidebar | تنقل مساحة المصمم أوالعميل | expanded، collapsed | default، hover، focus-visible، active، disabled | `nav` وتسميات الأيقونات؛ من inline-start، ويتحول Drawer على الهاتف | لا يستخدم في Public Shell ولا يستعير هوية Admin |
| PublicFooter | ختام عام وروابط ثقة | standard، compact | default، link states | `footer` وروابط واضحة؛ أعمدة يعاد ترتيبها منطقيًا | فحمي؛ لا يزدحم بإجراءات تشغيلية |

### 4. Feedback and Overlays

| Component | Purpose | Variants / Sizes | Required states | Accessibility وRTL/Mobile | Usage constraints |
|---|---|---|---|---|---|
| Toast | إشعار عابر غير حرج | info، success، warning، error | enter، visible، pause، dismiss | Live region مناسبة وزر إغلاق؛ موضع منطقي لا يحجب التنقل | ليس بدل خطأ مهم داخل الصفحة؛ حد أقصى 3 متراكمة |
| Banner | رسالة سياقية مستمرة | info، success، warning، danger | default، dismissible، action، loading | عنوان/وصف وإجراء واضح؛ لا يعتمد على اللون | للمعلومات التي يجب أن تبقى مرئية |
| Modal | قرار أوتأكيد قصير | confirmation، decision، compact form | opening، open، loading، error، closing | Dialog مسمى، Focus trap، Escape، استعادة التركيز | لا يستخدم للنماذج الطويلة؛ الطويل على الهاتف Full-screen |
| Drawer | سياق إضافي أوتنقل | navigation، details، form | opening، open، loading، error، closing | Dialog مناسب وقفل تمرير واستعادة تركيز؛ من inline-start للتنقل المعتاد | لا يحل محل صفحة كاملة لمحتوى عميق |
| BottomSheet | إجراءات أوفلاتر قصيرة على الهاتف | actions، filters، selection | opening، open، dragging عند الدعم، loading، error | عنوان ومقبض غير وحيد للإغلاق، Focus مضبوط | للهاتف؛ لا يستخدم لنموذج طويل |
| EmptyState | غياب محتوى قابل للفهم | first-use، no-results، cleared، unavailable | default | عنوان ووصف وإجراء واحد مناسب؛ رسم زخرفي غير مشتت | لا يلوم المستخدم ولا يقدم عدة إجراءات متنافسة |
| Skeleton | حجز بنية المحتوى أثناء التحميل | text، card، list، profile | loading، reduced-motion | مخفي دلاليًا مع حالة تحميل معلنة؛ يحافظ على الاتجاه | يشبه المحتوى النهائي ولا يستخدم بعد خطأ |
| Progress | تقدم معلوم أوغير معلوم | linear، circular، steps | active، paused، success، error | قيمة واسم معلنان؛ لا يعتمد على اللون | لا يعرض نسبة مخترعة |

## Rejected Alternatives

- استخدام Placeholder بدل Label في الحقول المهمة.
- تحويل Select كبير إلى قائمة غير قابلة للبحث.
- استخدام Modal لنموذج طويل.
- وضع Drawer التنقل دائمًا من اليسار بصرف النظر عن اللغة.
- استخدام Toast بدل التحقق Inline أوBanner المهم.
- إخفاء محتوى WorkCard خلف Overlay ثقيل.
- حشو DesignerCard بكل بيانات الملف.
- بناء تفاعل يعتمد على Hover أو`div` قابل للنقر.

## Deferred Decisions

- أسماء Props وEvents وواجهات الاستيراد البرمجية.
- اختيار مكتبة Headless أومكتبة Overlay أثناء التنفيذ.
- تفاصيل Bottom Navigation إن احتاجتها خريطة Workspace النهائية.

## Implementation Notes

هذه الوثيقة عقد سلوكي وليست Schema أوشفرة تنفيذية. ترتبط بيانات المصمم
بـ[`DESIGNER_PROFILES_UX_CONTRACT.md`](DESIGNER_PROFILES_UX_CONTRACT.md)،
وتطبق أصول [`BRAND_ASSETS_REGISTER.md`](BRAND_ASSETS_REGISTER.md)، وتعرض
المعرف العام وفق [`USERNAME_POLICY.md`](USERNAME_POLICY.md).

أي تغيير لاحق في Variant أوحالة أوحد وصول يحتاج قرارًا صريحًا موثقًا.

## وثائق مرتبطة

- [النظام البصري الخارجي](YEMEN_MOTION_PUBLIC_VISUAL_SYSTEM.md)
- [عقد تجربة ملفات المصممين](DESIGNER_PROFILES_UX_CONTRACT.md)
- [سجل أصول العلامة](BRAND_ASSETS_REGISTER.md)
- [سياسة أسماء المستخدمين](USERNAME_POLICY.md)
