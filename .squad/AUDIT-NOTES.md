# ملاحظات التدقيق — 2026-09-02

تدقيق الـ Done Criteria بتاعة الـ stories الخلصانة ضد الكود الحقيقي، مش ضد أي تقرير سابق.
كل بند اتعلّم `[x]` هنا اتأكد منه بقراءة كود فعلية أو باختبار اتشغّل.

---

## 1. الحالة الحالية للمربعات

| الستوري | العنوان | مُعلَّم | ناقص | الحالة |
|---|---|---|---|---|
| 492 | هيكل المشروع + API contract | 13 | 0 | ✅ مدقَّقة |
| 493 | Design tokens + مكتبة الكومبوننتس | 16 | 0 | ✅ مدقَّقة |
| 494 | الشل ثنائي اللغة RTL | 13 | 0 | ✅ مدقَّقة |
| 495 | Responsive + a11y | 7 | **3** | ⚠️ ناقصة تحقُّق يدوي |
| 496 | مصادقة الموظفين + البروفايل | 11 | 0 | ✅ مدقَّقة + **إصلاح** |
| 497 | المستخدمين/الأدوار/الأقسام | 0 | 12 | ⏳ **لسه ما اتدققتش** |
| 498 | سجل الإعدادات + كونسول الإدارة | 0 | 10 | ⏳ **لسه ما اتدققتش** |
| 499 | سجل التدقيق | 0 | 13 | ⏳ **لسه ما اتدققتش** |
| 500 | سجل العميل + كشف التكرار | 0 | 11 | ⏳ **لسه ما اتدققتش** |
| 501 | الملاحظات والمرفقات | 14 | 0 | ✅ مدقَّقة |

**مهم:** 497→500 شغلهم **موجود في الكود** (جداول، controllers، صفحات) — بس **ما اتدققّش بند بند**.
مربعاتهم فاضية، يعني الـ Stop hook هيوقفك لو فتحت أي واحدة فيهم.

الـ stories من 502 لـ 513 لسه ما اتنفّذتش أصلاً (ما عدا 503، اقرا القسم 2).

---

## 2. ⚠️ فيه جلسة تانية شغالة على نفس الريبو

اتأكدت من ده من `.claude/hooks/.state/track-story.log`:

```
05:05:07  session=f7c19b61…  →  10-story-501.md   ← الجلسة دي
05:08:10  session=cde12c1b…  →  12-story-503.md   ← جلسة تانية
```

الجلسة التانية بتنفّذ **ستوري 503 (مسار كتابة التذاكر)** وكتبت في الريبو أثناء التدقيق:

- `backend/app/Modules/Tickets/` قفز من **10 ملفات لـ 43**
- 3 migrations جديدة: `create_tickets_table`، `create_ticket_events_table`، `create_ticket_reference_sequence`
- عدّلت ملفات **مشتركة**: `backend/routes/api.php`، `frontend/lib/api/schema.ts`، `backend/openapi.yaml`
- عدد اختبارات الباك قفز من **458 لـ 509** بين تشغيلتين

### الخطر

`routes/api.php` و `openapi.yaml` و `schema.ts` ملفات **مشتركة بين كل الـ stories**.
جلستين بيكتبوا فيهم في نفس الوقت = حد هيدوس على التاني من غير ما ياخد باله.

### التوصية

شغّل **جلسة واحدة بس** على الريبو ده في أي وقت. لو محتاج توازي، استخدم
git worktrees عشان كل جلسة تشتغل على نسخة منفصلة.

### الخبر الحلو

نظام التتبّع اللي اتركّب شغّال صح عبر الجلستين — كل جلسة ليها ملف حالة منفصل
(`current_story-<session-id>.json`)، فالـ Stop hook بتاع كل واحدة بيراقب الستوري
بتاعتها هي بس ومش بيتلخبط.

---

## 3. 🔧 إصلاح حقيقي — rollback الـ migrations (ستوري 496، البند 10)

### الأعراض

```
2026_09_01_000400_add_department_and_active_to_users_table .. FAIL

SQLSTATE[HY000]: General error: 1 error in index users_is_active_index
after drop column: no such column: "is_active"
SQL: alter table "users" drop column "is_active"
```

### السبب

`down()` كان بيمسح العمود `is_active` من غير ما يمسح الـ index بتاعه اللي
`up()` عمله بـ `->index()`.

**ليه كان مخفي:** Postgres بيمسح الـ index التابع للعمود تلقائياً، فالمشكلة
ما بتظهرش على قاعدة التطوير. SQLite — اللي الـ **test suite** شغالة عليه —
بيعيد بناء الجدول وبعدين بيلاقي index بيشاور على عمود اتمسح، فيفشل.

### الإصلاح

الملف: `backend/app/Modules/Security/Database/Migrations/2026_09_01_000400_add_department_and_active_to_users_table.php`

اتضاف `$table->dropIndex('users_is_active_index');` في `Schema::table` منفصل
**قبل** حذف العمود. صحيح على المحركين.

### التحقق

```
migrate            → OK (كل الـ 15 migration)
migrate:rollback   → نزل للآخر لحد 0001_01_01_000000_create_users_table
php artisan test   → 509 passed / 1922 assertions
```

اتعمل على قاعدة sqlite مؤقتة في الـ scratchpad — **ما اتلمستش** أي داتا تطوير.

---

## 4. ⚠️ ستوري 495 — 3 بنود لسه ناقصة

دي **مش قابلة للإقفال من التيرمنال**. محتاجة شخص معاه موبايل وكيبورد،
حسب خطوة `Manual — real devices` (خطوة 7 في قسم Verification Steps بتاع الخطة نفسها).

### البنود التلاتة

**البند 4 — سطر 257 في `.squad/plans/inti/04-story-495.md`**
> Every function on every rendered page is completable at 390 px in a mobile
> browser and by keyboard alone with a visible focus indicator sourced from `--focus-ring`.

**البند 5 — سطر 258**
> `FileInput` primitive exposes `accept` and `capture` and has been verified
> opening the camera and photo library on a real iOS Safari and a real Android
> Chrome device.

**البند 8 — سطر 261**
> No action anywhere is reachable only by hover (verified by axe rule
> `interactive-supports-focus` and by manual keyboard traversal on the sample
> page at 1440 px).

### اللي **متحقَّق منه** برمجياً بالفعل

| الحاجة | الدليل |
|---|---|
| `accept` و `capture` مكشوفين | `frontend/components/ui/file-input.tsx:20,25` — و متمرّرين للـ `<input>` في السطور 80-81 |
| توكن حلقة التركيز موجود | `frontend/tokens/tokens.css:276-278` — `--focus-ring-color`, `--focus-ring-width`, `--focus-ring-offset` |
| axe بيشتغل على كل صفحة | `pnpm test:a11y` في `.github/workflows/frontend.yml:51` |
| مجموعة القواعد كاملة | `frontend/__tests__/a11y/axe.ts` — WCAG 2.1 A/AA، مفيش قاعدة مقفولة غير `region` وللكومبوننتس بس. `interactive-supports-focus` جوّه المجموعة دي |
| اختبارات كيبورد | `DataTable.keyboard.test.tsx`، `DataTable.focus.test.tsx` |
| الاتجاهين | كل ملفات a11y بتشتغل `ltr` و `rtl` |

### اللي **ناقص** — الجولة اليدوية

اللي محتاج يتعمل بالظبط:

1. شغّل الفرونت (`pnpm dev`) وافتحه من **iOS Safari حقيقي** (نسخة رئيسية حالية)
2. اعمل نفس الحاجة على **Android Chrome حقيقي** (نسخة رئيسية حالية)
3. على كل جهاز، وفي اللغتين **en** و **ar**:
   - (أ) اتأكد إن كل إجراء في `frontend/app/page.tsx` ينفع يتعمل
   - (ب) اتأكد إن حقل الملف بيفتح **الكاميرا** و**مكتبة الصور**
   - (ج) اتأكد إن حلقات التركيز باينة لما تضغط Tab
4. على ديسكتوب عرض **1440px**: تنقّل بالكيبورد بس في الصفحة، واتأكد إن
   مفيش إجراء بيظهر بالـ hover بس

بعد ما تخلص، علّم التلات مربعات في `.squad/plans/inti/04-story-495.md`.

التفاصيل متسجّلة كمان في **`.squad/gaps/04-495.md`**.

---

## 5. الاختبارات اللي اتشغّلت (كلها خُضر)

| الأمر | النتيجة |
|---|---|
| `php artisan test` | **509 passed** / 1922 assertions |
| `php artisan openapi:check` | `openapi.yaml is up to date` |
| `deptrac analyse` (layers) | 0 violations / 544 allowed |
| `deptrac analyse` (tiers) | 0 violations / 544 allowed |
| `php scripts/check-no-cross-import.php` | clean |
| `npx vitest run` | **579 passed** / 49 files |
| `npx eslint` | exit 0 — 0 errors، تحذير واحد |
| `node scripts/check-tokens.mjs` | clean |
| `npx tsc --noEmit` | exit 0 |
| `node scripts/check-next-version.mjs` | `next 16.3.3 satisfies >= 16.3.3` |
| `npx next build` | نجح |
| `migrate` + `migrate:rollback` | نجحوا **بعد** الإصلاح في القسم 3 |

> **ملاحظة:** الـ 579 اختبار فرونت و الـ 509 باك اتشغّلوا في لحظات مختلفة أثناء
> شغل الجلسة التانية. لو الأرقام اختلفت عندك، ده متوقَّع.

التحذير الوحيد في lint:
`__tests__/components/screens/customers/CustomerProfileScreen.test.tsx:180:5`
— `@typescript-eslint/no-unused-expressions`. تحذير مش خطأ، فالـ lint بيعدّي.

---

## 6. الخطوات الجاية

### أ. لو هتكمّل تدقيق 497→500

**46 بند** لسه محتاجين تحقق. الطريقة اللي اتبعتها:

1. اقرا قسم `## Done Criteria` بتاع الستوري
2. اتأكد من كل بند ضد الكود الحقيقي — ملفات، routes، migrations، اختبارات
3. اللي ناقص → نفّذه
4. اللي موجود ومتحقَّق منه → علّمه `[x]`
5. أي حاجة مكسورة **خارج** نطاق الخطة → `.squad/gaps/<NN>-<id>.md`

في أي جلسة: `/audit-story 497` (الأمر بيقبل مسار كامل أو اسم مجرَّد أو رقم).

### ب. لو هتبدأ تنفيذ stories جديدة

الترتيب حسب `00-overview.md`: **502** (خط زمن التفاعلات) هي التالية،
لكن **503 تحت التنفيذ في جلسة تانية دلوقتي**. اتأكد الأول.

### ج. قرار معلّق — `backend/CLAUDE.md`

ظهر ملف `backend/CLAUDE.md` جديد بيطلب تثبيت `laravel/boost` كـ dev dependency:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

**ما اتنفّذش** — ده تغيير في `composer.json` و `composer.lock` خارج نطاق التدقيق.
قرارك.

---

## 7. نظام التتبّع — حالته

اتركّب وشغّال. التفاصيل:

| المكوّن | المكان | الحالة |
|---|---|---|
| تسجيل الـ hooks | `.claude/settings.json` | ✅ جديد |
| تتبّع الستوري الجارية | `.claude/hooks/track_current_story.sh` (UserPromptSubmit) | ✅ بيلقط المسار الكامل، الاسم المجرَّد، ورقم الستوري بالعربي والإنجليزي |
| منع الإنهاء المبكر | `.claude/hooks/verify_story_completion.sh` (Stop) | ✅ اتصلح فيه خطأ صياغة كان بيمنعه من الاشتغال أصلاً |
| أمر التدقيق | `.claude/commands/audit-story.md` | ✅ اتنقل من `hooks/` وشيلت منه مسار `foundation` المتحفور |
| حالة الجلسات | `.squad/state/` | ✅ ملف لكل جلسة، في `.gitignore` |
| سجل الثغرات | `.squad/gaps/` | ✅ فيه `04-495.md` |

**حدود الـ Stop hook:** بيقرا المربعات بس — **مش** بيتحقق من الكود.
دوره إنه يمنع الخروج من ستوري وبنودها لسه فاضية، مش إنه يحكم على جودة الشغل.
التحقق الحقيقي مسؤولية اللي بينفّذ.
