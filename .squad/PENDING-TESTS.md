> **الحالة: اتنفّذوا كلهم.** ٥٩ اختبار جديد اتكتبوا واتشغّلوا وكلهم خُضر،
> ومربعات ستوري 504 اتعلّمت. الملف متسيب كسجل للي اتغطى.

# اختبارات مؤجَّلة — تُكتب دفعة واحدة

الكود بيتنفّذ الأول، والاختبارات بتتكتب كلها مرة واحدة في الآخر.
كل بند هنا فيه: الملف، الحالة المطلوبة، وإيه اللي بيثبته.

> **قاعدة:** أي اختبار **موجود** بيقع بسبب تغيير في السلوك بيتعدّل في نفس اللحظة
> عشان السويت تفضل خضرا — مش بيتأجّل. المؤجَّل هو الاختبارات **الجديدة** بس.

---

## ستوري 504 — دورة حياة التذكرة

### `Feature/Tickets/TicketLifecycleTest.php` (تعديل الموجود + إضافات)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 1 | كل حافة مسموحة: `open⇄pending`، `open→resolved`، `pending→resolved`، `resolved→closed`، `closed→open` داخل النافذة، `resolved→open` | جدول الانتقالات هو المصدر الوحيد |
| 2 | كل حافة ممنوعة تترفض بـ `409 ticket.transition_forbidden` **بنداء API مباشر** | السيرفر بيفرض القاعدة مهما عمل الكلاينت |
| 3 | تذكرة بتتولد `open` دايمًا؛ محاولة إنشاء بحالة تانية تترفض | AC: "born Open" |
| 4 | مفيش `new` ولا `cancelled` في الإينَم ولا في openapi | نص الستوري |

### `Feature/Tickets/TicketAssignmentTest.php` (جديد)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 5 | agent يسند تذكرة **غير مسندة** لنفسه → ينفع | القاعدة الأساسية |
| 6 | agent يحاول ياخد تذكرة **مسندة لزميل** → `403 ticket.reassign_forbidden` | الفرق بين assign و reassign_any |
| 7 | supervisor يعيد الإسناد لأي حد → ينفع | `ticket.reassign_any` |
| 8 | agent يعمل unassign لتذكرة زميله → نفس الرفض | إلغاء الإسناد تعديل زيه زي أي تعديل |
| 9 | إسناد لمستخدم **معطَّل** → `422 ticket.assignee_invalid` | معطَّل يعني مش هيشتغل عليها |
| 10 | إسناد لمستخدم من **قسم تاني** → `422 ticket.assignee_invalid` | |

### `Feature/Tickets/TicketDepartmentTest.php` (جديد)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 11 | نقل القسم بينجح وبيتسجّل في التاريخ | |
| 12 | قسم مش موجود → `422 ticket.department_invalid` | |
| 13 | لو النقل خلّى المُسنَد إليه **بره القسم** → `assignee_id` بيتفضّى **بقيد تاريخ منفصل** | قرار الخطة: امسح وسجّل، مش ارفض |

### `Feature/Tickets/TicketResolveReopenTest.php` (جديد)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 14 | resolve بيحط `resolved_at` | |
| 15 | reopen جوّه النافذة بيفضّي `resolved_at` و `closed_at` | |
| 16 | reopen **على حافة النافذة بالظبط** ينفع (`<=`) | الحدود، مش التقريب |
| 17 | reopen بعد النافذة → `409 ticket.reopen_window_expired` وفيه `new_request_hint` | الرفض بيقول تعمل إيه بدالها |

### `Feature/Tickets/TicketAutoCloseSweepTest.php` (جديد)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 18 | محلولة من 73 ساعة، مفيش نشاط عميل → **بتتقفل** | |
| 19 | محلولة من 71 ساعة → **بتتساب** | الحدّ بيتحسب صح |
| 20 | محلولة من 100 ساعة بس فيه رد عميل من ساعة → **بتتساب** | نشاط العميل بيمنع الإغلاق |
| 21 | مقفولة أصلًا → بتتساب | |
| 22 | تشغيل الأمر **مرتين** بيدّي نفس النتيجة | idempotent |
| 23 | قيد التاريخ actor = `system` / `auto_close` وشكله زي أي إجراء بشري | AC صريح |
| 24 | تذكرة اتفتحت تاني بين الـ SELECT والتنفيذ → بتتسجّل وبيكمّل، مش بيقع | سباق الـ sweep |
| 25 | `--dry-run` ما بيغيّرش حاجة | |

### `Feature/Tickets/TicketStaleVersionTest.php` (جديد)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 26 | `PATCH /status` بنسخة قديمة → `409 ticket.stale_version` | |
| 27 | الـ sweep **معفي** من فحص النسخة (استدعاء مباشر بنسخة قديمة كـ system) | نص الستوري |

### `Feature/Tickets/CustomerReplyReopensTest.php` (جديد)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 28 | حدث رد العميل على تذكرة `resolved` → بترجع `open` | |
| 29 | `last_customer_activity_at` بيتحدّث **قبل** الانتقال | عشان الـ sweep ما يسبقهاش |
| 30 | رد عميل على تذكرة `closed` → **مفيش** فتح تلقائي | خارج نطاق الستوري صراحةً |

### `Architecture/CapabilitiesInSyncTest.php` (تحديث)

| # | الحالة | اللي بيثبته |
|---|---|---|
| 31 | الصلاحيات الستة الجديدة موجودة ومزروعة للأدوار الصح | |

### فرونت

| # | الملف | اللي بيثبته |
|---|---|---|
| 32 | `__tests__/lib/api/ticketErrors.test.ts` | الخمس أكواد بتطلع `TicketRefusedError` من مكان واحد (`request.ts`)، وكل واحد بمفتاح الرسالة الصح |
| 33 | نفس الملف | `tickets.reopen_window_expired` بيجيب `newRequestHint` و `reopenWindowDays` متحللين |
| 34 | نفس الملف | كود مش في الجدول → `ApiError` عادي، مش `TicketRefusedError` |
| 35 | `messages-parity.test.ts` | بيعدّي لوحده بعد إضافة المفاتيح (مفيش كود جديد) |

---

## اللي اتعمل بالفعل (كود من غير اختبارات جديدة)

| الحاجة | المكان |
|---|---|
| أعمدة `resolved_at` / `closed_at` / `last_customer_activity_at` + فهرس الـ sweep | `2026_09_06_000100_add_lifecycle_columns_to_tickets.php` |
| جدول الانتقالات، مصدر واحد | `Domain/Enum/TicketTransitions.php` |
| الحالات بقت أربعة (`reopened` اتشالت) | `Domain/Enum/TicketStatus.php` |
| نافذة إعادة الفتح + ختم الأوقات | `Domain/Lifecycle/TicketLifecycle.php` |
| قاعدة الإسناد (agent مقابل reassign_any) | `Domain/Commands/AssignTicket.php` |
| نقل القسم + تفضية المُسنَد إليه المعزول | `Domain/Commands/ChangeDepartment.php` |
| الكنس التلقائي عبر نفس أمر `ChangeStatus` | `Console/Commands/TicketsAutoCloseCommand.php` |
| جدولة كل ١٥ دقيقة + `withoutOverlapping` + `onOneServer` | `routes/console.php` |
| حدث رد العميل + المستمع | `Domain/Events/CustomerReplyPosted.php`، `Listeners/ReopenOnCustomerReply.php` |
| ٦ صلاحيات جديدة + الزرع للأدوار | `Capabilities.php`، `RolesAndPermissionsSeeder.php` |
| الإعدادين (٧٢ ساعة / ١٤ يوم) | `TicketsServiceProvider::registerSettings()` |
| `PATCH /tickets/{id}/department` | `TicketsController::changeDepartment()` |
| الأخطاء الخمسة كأنواع في مكان واحد | `frontend/lib/api/errors.ts` |

### باچين اتلقطوا من التشغيل الحي (اتصلحوا، ومحتاجين اختبار في الدفعة الأخيرة)

| الباج | الأثر لو ما اتصلحش | اختبار مطلوب |
|---|---|---|
| `VersionGuard` كان بيرفض الـ sweep لأنه بيبعت `version = null` | الإغلاق التلقائي **ما كانش هيشتغل خالص** — كل تذكرة بتترفض بـ stale_version | #27 (إعفاء الـ system) |
| أعمدة دورة الحياة مكانتش متحوّلة لتواريخ في `Ticket` | `closed_at->addDays()` على نص = خطأ قاتل وقت أي إعادة فتح | #16 (حافة النافذة) |

> الاتنين اتلقطوا لأني شغّلت الـ sweep والإعادة فتح **على قاعدة حقيقية**، مش من الاختبارات.
> ده بالظبط سبب إن الاختبارات المؤجَّلة دي لازم تتكتب قبل ما الستوري تتقفل.

### ملاحظة عن البيئة

الاستاك كله شغّال في **Docker** من image متبني (`ragab-crm-backend-web-1` على المنفذ 8000)،
والسورس **مش** متعمله bind mount. يعني أي اختبار عبر `curl` على `localhost:8000`
بيضرب كود قديم. التحقق الحقيقي بيتعمل بـ `php artisan` محليًا أو بالـ test suite.
لو عايز تجرب من المتصفح لازم تعمل rebuild للـ image الأول.

### اختبارات موجودة اتعدّلت (مش مؤجَّلة — السويت لازم تفضل خضرا)

- `TicketLifecycleTest` — جدول الانتقالات الجديد، و`closed` بقت قابلة للفتح، و`reopened` اتشالت
- `SettingsRegistryTest` — قايمة المفاتيح المثبَّتة زادت اتنين
- `EventAppendedInSameTransactionTest` — قايمة الأوامر المثبَّتة زادت `ChangeDepartment`
- `AuthorizationRefusalTest` — رفض إعادة الإسناد اتنقل من الراوت للأمر نفسه، فالاختبار بقى بيستخدم تذكرة حقيقية
