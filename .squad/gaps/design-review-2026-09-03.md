# مراجعة تصميم — 2026-09-03

الملف ده **مش عن وظايف ناقصة** — ده عن الشكل. المشاكل الوظيفية في
`.squad/gaps/ui-issues-2026-09-03.md`، وكتير منها كان تحت الإصلاح وأنا بكتب.

كل ملاحظة هنا مقيسة على **الموك-أب المعتمد** الموجود في الريبو، مش على ذوق.

---

## حالة التنفيذ — 2026-09-04

الملف اتنفّذ بالكامل بالترتيب المقترح في القسم ١١.

| # | البند | الحالة |
|---|---|---|
| 1 | أسماء دلالية للعائلات الأربعة (٢) | ✅ ٤٠ توكن بقى ليها أسماء في `:root` و `@theme inline` |
| 2 | شارة الحالة + مقياس الأولوية + شريط SLA (٣) | ✅ `StatusBadge` · `UrgencyMeter` · `Meter` جوّه `SlaIndicator` |
| 3 | صف سطرين + وقت نسبي (٣) | ✅ الموضوع فوق، وتحته `TKT-… · القناة`. عمود المرجع اتشطب |
| 4 | هياكل التحميل (٨) | ✅ `RowSkeleton` + بلاطات بتستنى بدل ما تدّعي |
| 5 | شريط أفعال رأس التذكرة (٥-أ) | ✅ `TicketHeaderActions` — حل/إعادة فتح كفعل أساسي |
| 6 | توحيد شريط الفلاتر (٧) | ✅ ٦ قوايم بتسميات + شرايح للمفعّل |
| 7 | الحافة القابلة للتحرير (٥-ب) | ✅ أربع فرقات + `EditableEdge` صلبة/متقطّعة |
| 8 | تجميع طابور الرئيسية (٤) | ✅ ٤ مجموعات حسب سبب الانتباه + سطر الترتيب + سطر السياق |
| 9 | عدّادات قايمة الإدارة (٦) | ✅ عدّاد جنب كل قسم + `AdminContextLine` في الرأس |
| 10 | pager + الملاحظات الصغيرة (٩) | ✅ |

### تعديل واحد على اللي الملف طلبه — والسبب

الملف اقترح الأسماء الدلالية تبقى `--color-sla-on-track-fg` وخلافه، **وقال
في نفس الوقت متشيلش `status|sla|priority|channel` من `PRIMITIVE_SCALES`**.
الاتنين مايتحققوش مع بعض: `check-tokens.mjs` بيمنع **الاسم** نفسه
(`bg-status-open-bg`)، مش الطبقة اللي معرّفاه — فاسم دلالي بنفس نص الاسم
البدائي كان هيتمنع بالظبط زيه.

اتحلّت بالحفاظ على **نية** التعليمة: الحظر ما اتلمسش، والأسماء الدلالية اتسمّت
باللي بتعنيه — بنفس منطق `success` (بدائي) اللي بيتوصل بـ `state-success`:

| العائلة البدائية | الاسم الدلالي | مثال |
|---|---|---|
| `status-*` | `ticket-*` | `bg-ticket-open-bg` |
| `sla-*` | `clock-*` | `text-clock-breached` · `bg-clock-track` |
| `priority-*` | `urgency-*` | `text-urgency-urgent` |
| `channel-*` | `source-*` | `text-source-email` |

السبب مكتوب كامل في `tokens.css` جنب البلوك نفسه.

### حاجة زيادة اتصلحت

**العدّاد كان بيطبع دقايق خام.** تذكرة متأخرة أسبوعين كانت بتقول
«متأخرة ٢٠٬٨٨٠ دقيقة» — رقم محدش بيحوّله وهو بيمسح طابور، على الشارة اللي
موجودة عشان تتمسح. بقى `43d 4h` (يوم عمل ٨ ساعات، مش ٢٤، لأن المحرّك بيعدّ
بوقت الشغل). والموك-أب بيقول `+18h`.

### الجملة الغلط في الخطة (القسم ٠)

`12-story-503.md:356` كان مكتوب فيه إن الموك-أب «not present in this repo».
اتصحّحت، ومكتوب جنبها إن جملة التركيب على المسار هي اللي ضاعت في نفس إعادة
الكتابة — واللي خلّت شاشة «تذكرة جديدة» تتبني وماتتركّبش.

### اللي لسه ماتفحصش بالعين

موبايل ٣٩٠px وتابلت ٧٦٨px، وقواعد R-01→R-08 في `screen-responsive.html`.
وموك-أبّات `screen-customer-profile.html` و`screen-portal.html`
و`screen-reports.html` ما اتقارنوش بند ببند — بس البنود اللي الملف ده رصدها
منهم كلها اتقفلت.

---

## 0 — مرجع التصميم، وإزاي تفتحه

تمن ملفات HTML مستقلة (مفيش شبكة، مفيش جافاسكربت، تفتح أوفلاين):

| الملف | بيحكم شاشة إيه |
|---|---|
| `.squad/stories/inti/507/attachments/direction-e2-list.html` | قائمة التذاكر + قرار الخطوط |
| `.squad/stories/inti/507/attachments/screen-home.html` | الرئيسية — طابور الانتباه |
| `.squad/stories/inti/506/attachments/direction-e2-workspace.html` | مساحة التذكرة + الرَيل |
| `.squad/stories/inti/500/attachments/screen-customer-profile.html` | ملف العميل |
| `.squad/stories/inti/498/attachments/screen-admin.html` | كونسول الإدارة |
| `.squad/stories/inti/512/attachments/screen-portal.html` | بوابة العملاء |
| `.squad/stories/inti/495/attachments/screen-responsive.html` | التجاوب — تلات نطاقات |
| `.squad/stories/inti/493/attachments/screen-reports.html` | التوكنز والمكوّنات |

`file://` مقفول في المتصفح المؤتمت. قدّمهم على HTTP:

```
cd .squad/stories/inti && python3 -m http.server 8899 --bind 127.0.0.1
# http://127.0.0.1:8899/507/attachments/direction-e2-list.html
```

**ملاحظة للي جاي بعدي:** `12-story-503.md:356` مكتوب فيه إن
`direction-e2-workspace.html` **"not present in this repo"** فاتبنت شاشة
"تذكرة جديدة" بالتخمين من وصف الستوري. الملف **موجود** —
`.squad/stories/inti/506/attachments/`. الجملة دي غلط ولازم تتشال من الخطة.

---

## 1 — النتيجة في سطر

المنتج بيشتغل ومنظّم ونضيف، لكنه **مرسوم بلون واحد**. نظام التصميم فيه أربع
عائلات ألوان كاملة للحالة والـ SLA والأولوية والقناة — **ولا واحدة فيهم
مستخدمة في أي مكوّن**، وده مش إهمال: **الطريقة الوحيدة لاستخدامها ممنوعة
بفحص CI**. التفصيل في القسم اللي بعده، وهو أهم بند في الملف.

---

## 2 — 🔴 طبقة الألوان الدلالية كلها ميتة، والسبب معماري

### الحقيقة المقاسة

```
$ grep -rl "status-open|sla-breached|priority-urgent|channel-email" components/ app/ lib/
(صفر نتائج)
```

أربع عائلات في `frontend/tokens/tokens.css` بصفر استخدام:

| العائلة | السطور | الأسماء |
|---|---|---|
| حالة التذكرة | 51-68 | `--color-status-{new,open,pending,resolved,closed,cancelled}-{fg,bg,bd}` |
| ساعة الـ SLA | 71-89 | `--color-sla-{on-track,at-risk,breached,met,paused,na}-{fg,bg,bd}` + `--color-sla-track` |
| الأولوية | 92-97 | `--color-priority-{low,normal,high,urgent}` + `-urgent-bg` + `--color-priority-bar-empty` |
| القناة | 100-104 | `--color-channel-{email,whatsapp,sms,livechat,webform}` |

### ليه ميتة — ودي مش غلطة مطوّر

`tokens.css` بيفرض تراتب من طبقتين، والقاعدة مكتوبة في رأس الملف:

> `@theme` **primitives**. Components must NEVER reference them (no `bg-n-800`).
> `:root` **semantics** — the only names a component may use.

والحظر ده **متنفَّذ فعلاً**، مش عرف. `frontend/scripts/check-tokens.mjs:27`:

```js
/** Primitive palette scales. Declared in tokens.css for Tailwind's benefit only. */
const PRIMITIVE_SCALES = "n|status|sla|priority|channel|cat|ord|chart|ai";
```

يعني `className="bg-status-open-bg"` = **فشل في CI**، وبقاعدة ESLint
`design-system/semantic-tokens-only` كمان.

وبصّ بقى على طبقة الأسماء المسموح بيها (`@theme inline`, السطر 372 لآخر
الملف). اللي جوّاها بالظبط:

**Surfaces · Borders · Foreground · Accent · State (success/warning/danger/info) · Elevation.**

**مفيش ولا اسم واحد لـ status ولا sla ولا priority ولا channel.**

فالنتيجة: العائلات الأربعة **مستحيل الوصول ليها من أي مكوّن، بالتصميم**.
الـ ٤٠ توكن دول موجودين في الملف ومحدش يقدر يلمسهم.

### الدليل على إن ده اللي حصل فعلاً

`components/domain/SlaIndicator/SlaIndicator.tsx:66-70`:

```tsx
on_track: "text-fg-muted",
at_risk:  "font-semibold text-state-warning",
breached: "font-semibold text-state-danger",
met:      "text-state-success",
paused:   "italic text-fg-muted",
```

المكوّن مدّ إيده على ألوان الـ **feedback العامة**، لأنها الوحيدة المتاحة له.
وعائلة `--color-sla-*` — المكتوب فوقيها في `tokens.css:70`
*"The traffic light. Reserved for SLA."* — محجوزة لحاجة عمرها ما جت.

ولاحظ: `text-only`. مفيش خلفية، مفيش برواز، ومفيش **شريط**، رغم إن
`--color-sla-track` موجود عشان الشريط ده بالظبط.

### المطلوب

**أ) وسّع طبقة الأسماء الدلالية.** ضيف في `@theme inline` أسماء لكل عائلة،
بنفس نمط `--color-state-*` الموجود. مثال للـ SLA:

```css
--color-sla-on-track-fg: var(--sla-on-track-fg);
--color-sla-on-track-bg: var(--sla-on-track-bg);
--color-sla-on-track-border: var(--sla-on-track-bd);
/* … at-risk / breached / met / paused / na، ونفس الحكاية لـ status و priority */
--color-sla-track: var(--sla-track);
```

ولازم كمان تتعرّف القيم دي في بلوك `:root` زي باقي الدلاليات، عشان
`@theme inline` تلاقي حاجة تشاور عليها والـ theming يفضل شغّال وقت التشغيل.

**ب) سيب `check-tokens.mjs` زي ما هو.** الحظر صح — اللي ناقص هو الأسماء
المسموح بيها، مش إن الحظر يترفع. **متشيلش `status|sla|priority|channel` من
`PRIMITIVE_SCALES`.**

**ج) بعدين بس** ابني الشارات والشرايط في القسم ٣.

**علامة الإنجاز:** `pnpm lint` و`node scripts/check-tokens.mjs` خضرا،
والـ grep اللي فوق بيرجّع نتايج من `components/`.

> نفس الحكاية بالظبط في `cat`, `ord`, `chart` (`tokens.css:126-140`) — تلات
> عائلات ألوان تانية محظورة ومن غير أسماء دلالية. مش مستعجلة دلوقتي (مفيش
> رسوم بيانية في المنتج)، بس هي نفس العيب.

---

## 3 — صف التذكرة: الموك-أب بيبني تراتب جوّه الصف، والشغّال بيفرده على أعمدة

قارن بعينك: `direction-e2-list.html` جنب `http://localhost:3000/tickets`.

### الفرق، بند بند

| | الموك-أب | الشغّال دلوقتي |
|---|---|---|
| الموضوع | **سطرين**: الموضوع غامق فوق، وتحته `اسم العميل · القناة · TKT-000398` بخط أصغر باهت | سطر واحد. والمرجع عمود منفصل. |
| الحالة | **شارة ملوّنة بنقطة** — خضرا Open، كهرماني Pending | نص عادي أسود |
| الأولوية | **مقياس أعمدة + كلمة**، أحمر لـ Urgent | نص عادي أسود |
| المسؤول | **شارة أفاتار بالأحرف الأولى** (`ON` Omar Nasser)، و Unassigned باهت بأفاتار `+` | نص عادي |
| SLA | **وقت + شريط تقدّم ملوّن** تحته (`+18h Breached` بشريط أحمر) | نص صغير باهت من غير شريط |
| آخر تحديث | **نسبي** (`12m ago`) + شارة `2 new` للنشاط غير المقروء | تاريخ مطلق `Sep 3, 2026, 7:23 PM` |
| القناة | أيقونة + اسم في سطر الموضوع | **مش معروضة خالص** |

### القاعدة اللي الموك-أب بيصرّح بيها

> **One badge per row.** · `Row height 44px` · `Western digits 0-9, tabular`

يعني: شارة واحدة ملوّنة بس في الصف تاخد الانتباه. الباقي تدرّجات رمادي.
دلوقتي **مفيش ولا شارة**، فكل قيمة في الصف ليها نفس الوزن البصري، والعين
مالقيتش حاجة تمسك فيها.

### المطلوب

1. `StatusBadge` — شارة بخلفية وبرواز ونقطة، من عائلة `status`.
2. `PriorityMeter` — تلات أعمدة صغيرة + الكلمة، من عائلة `priority` +
   `--color-priority-bar-empty`.
3. `AvatarChip` — دايرة بالأحرف الأولى، وحالة "غير مسند" لها شكلها.
4. وسّع `SlaIndicator` بشريط تقدّم على `--color-sla-track`.
5. اطوِ المرجع والعميل والقناة في **سطر تاني** جوه عمود الموضوع، واشطب عمود
   المرجع المنفصل.
6. `Updated` تبقى نسبية (`Intl.RelativeTimeFormat` من طبقة `useFormat`
   الموجودة) والتاريخ المطلق يبقى في `title`.

**الملفات:** `components/domain/TicketList/TicketListTable.tsx`،
`components/domain/SlaIndicator/SlaIndicator.tsx`، ومكوّنات جديدة تحت
`components/domain/`.

---

## 4 — الرئيسية: المطلوب طابور مجمَّع، والموجود نسخة تانية من القائمة

`screen-home.html` بيفتتح باقتباس من صاحب المنتج:

> "I do not want a dashboard full of useless charts. I want it practical,
> showing what needs the user's attention now."

وبيحقّقه بـ **تجميع الطابور حسب السبب**: عناوين مجموعات زي
**"⬦ Breached — act now"** وتحتها `resolution target passed`.

اللي شغّال: بلاطات العدّ (شغّالة صح دلوقتي، الأرقام بتوصل ✓) وتحتها
**نفس جدول `/tickets` بالحرف** — نفس الأعمدة، نفس الترتيب، من غير أي تجميع.
يعني الرئيسية بقت قائمة تانية مفلترة، مش شاشة ليها شخصية.

### فروق تانية

- **البلاطة مقلوبة.** الموك-أب: الرقم كبير الأول، وتحته الوصف بنقطة ملوّنة.
  الشغّال: الوصف صغير فوق والرقم تحته. الرقم هو الرسالة — يبقى هو الأول.
- **البلاطة شكلها مش قابلة للضغط.** هي `<a>` فعلاً، بس مفيش سهم ولا أي إشارة.
  الموك-أب حاطط `→` وحالة hover واضحة وحلقة تركيز.
- **مفيش سطر السياق** تحت "Home". الموك-أب:
  `Tuesday 25 August · 10:42 · Deliveries, Riyadh`.
- **مفيش جملة الترتيب.** الموك-أب بيكتب جنب التبويب
  `Ordered by SLA urgency, then priority, then age`. الطابور دلوقتي مرتّب
  بمنطق مالوش أي أثر على الشاشة.
- `"Your queue"` نص غامق سايب، مش عنوان قسم.

### المطلوب

جمّع الطابور بعناوين مجموعات حسب سبب الانتباه (متجاوز / معرّض للتجاوز /
منتظر ردّك)، اقلب البلاطة، حط سهم وحالة hover، وارجّع سطر السياق وجملة
الترتيب.

**الملفات:** `components/screens/home/AgentHomeScreen.tsx`،
`components/screens/home/CountsStrip.tsx`.

> **متعملش:** تبويبات "Tasks & reminders" و"Mentions" و"Set availability"
> اللي في الموك-أب — دول برّه نطاق المنتج.

---

## 5 — مساحة التذكرة: الرَيل هو توقيع التصميم، والموجود قايمة حقول

عنوان الموك-أب حرفياً: *"the Property / Context Rail as the signature"*.

### أ) مفيش شريط أفعال في الرأس

الموك-أب: `↩ Reply · 🔒 Note · ⏰ Snooze · ↑ Escalate · ⋯ More · ✓ Resolve`
(الأخير زرار أساسي على اليمين).

الشغّال: **صفر أزرار في الرأس**. تقفل تذكرة عن طريق قايمة منسدلة اسمها
"Status" في الرَيل. أهم فعل في المنتج مدفون في `<select>`.

### ب) الرَيل قايمة تسميات، مش أربع فرقات

الموك-أب بيقسّم السبع خصائص لأربع فرقات حسب **نوع الحاجة**، وكل واحدة ليها
*"editable edge"* — خط ٢px على الحافة الداخلية، منوّر بلون الـ accent لما
القيمة قابلة للتغيير، ومتقطّع وباهت لما تكون مقفولة. الخط ماشي على طول الرَيل
وبيقف عند أول حقيقة مش قابلة للتغيير.

الشغّال: تسمية فوق `<select>` صغير، مكررة ست مرات. مفيش فرقات ولا حافة.

**الحافة دي هي أوضح فكرة في نظام التصميم كله** — بتقول للموظف "دي تقدر
تغيّرها ودي لأ" من غير كلمة واحدة. ومش موجودة في أي شاشة، لا هنا ولا في
كونسول الإدارة (اللي `screen-admin.html` بيقول عليها بالنص
*"Administration is where the editable edge earns its keep"*).

### ج) لوحة الـ SLA حقايق ناقصة

| الموك-أب | الشغّال |
|---|---|
| عنوان `Live state` + تلميح `changes without you` | `Service level` |
| `⚠ 1h 12m left` بخط كبير | `Met` / `Paused` نص صغير |
| `At risk · 82% elapsed · due 25 Aug, 10:14` | ولا حاجة |
| `First response met, 00:41 of 01:00 · Standard — Deliveries` | ولا حاجة |
| شريط تقدّم | ولا حاجة |

النسبة المئوية والموعد المستحق واسم السياسة — تلاتتهم بيوصلوا من الـ API
(ستوري 510، AC6) وبيتترموا.

### د) "Assigned to" المفروض كارت مش سطر

الموك-أب: أفاتار + اسم + `Agent · Deliveries · Riyadh` + اختصار
`⌥A to take it`. الشغّال: قايمة منسدلة صغيرة.

### هـ) الرسايل من غير أفاتار

الموك-أب: أفاتار بالأحرف الأولى + اسم + قناة (`Email`, `Email reply`) + وقت
+ حالة تسليم `✓ Delivered`. الشغّال عنده الاسم والقناة والوقت وحالة التسليم ✓،
وناقصه الأفاتار.

### و) المرجع

الموك-أب: **شريحة** `TKT-000412` قبل العنوان. الشغّال: نص رمادي صغير جنبه.

**الملفات:** `components/domain/TicketPropertyRail/TicketPropertyRail.tsx`،
`components/screens/tickets/TicketDetailScreen.tsx`،
`components/domain/TicketConversation/ConversationPanel.tsx`،
`components/domain/SlaIndicator/SlaIndicator.tsx`.

> **متعملش:** لوحة `AI summary` اللي فوق المحادثة في الموك-أب. ستوري 506
> شالتها عن قصد وبند الإنجاز بيقول `grep for AiSuggestionPanel returns nothing`.

---

## 6 — كونسول الإدارة: الفهرس بيقول أرقام، والقايمة بتقول أسامي

`screen-admin.html` بيرسم `/admin` كـ **شبكة كروت**: أيقونة + عنوان + جملة
شرح + **سطر إحصاء حيّ**:

- Organisation — `4 departments · 3 branches`
- Ticketing — `18 categories · 4 priorities · 6 statuses`
- Service levels — `5 SLA policies · 7 escalation rules`

والرأس فيه `Administration` + `AZM Squad · 38 users · 3 branches` + زرار
`Open the audit log`.

الشغّال: `/admin` بيعمل redirect لـ `/admin/organisation`، والتنقّل قايمة نصية
على الجنب. المنطق مكتوب في الكود (*"a landing page listing the six sections
would duplicate the index that is already on screen"*) وهو **منطق سليم** —
بس بكده **الأرقام ضاعت**. الأدمن بيدخل الأقسام واحد واحد عشان يعرف فيها إيه.

### المطلوب

مش لازم ترجّع صفحة الفهرس. **حطّ الإحصاء في القايمة الجانبية** — عدّاد صغير
باهت جنب اسم كل قسم، زي ما القايمة الجانبية في الموك-أب عاملة بالظبط
(`My tickets 14`، `At risk 3`). كده الأدمن يشوف الشكل من غير صفحة زيادة.

وضيف سطر السياق في الرأس (`عدد المستخدمين · عدد الأقسام`).

**الملفات:** `components/screens/admin/SectionIndex.tsx`،
`app/(app)/admin/layout.tsx`.

---

## 7 — شريط الفلاتر بقى مزدحم بعد الإضافة

الفلاتر الستة اتضافوا (كويس — كانوا اتنين). بس الشريط دلوقتي:

```
[Any][Open][Pending][Resolved][Closed]  [Any][Low][Normal][High][Urgent]
   Category ▾   Department ▾   [Any][At risk][Breached]   Assignee ▾
```

- مجموعات الشرايح **من غير تسمية** والقوايم المنسدلة **ليها تسمية**. نفس
  الشريط بقاعدتين.
- كلمة **"Any" ظاهرة تلات مرات**، وكلها معناها حاجة مختلفة.
- المجموعات بتلف على سطرين من غير أي فاصل بصري، فمش واضح فين تخلص مجموعة
  وتبدأ اللي بعدها.
- الموك-أب بيحل ده بآلية **واحدة**: كله قوايم منسدلة بتسمية، والمختار بيتحوّل
  لـ **شريحة قابلة للشيل** (`SLA  At risk, Breached  ×`) قبلها. وبيصرّح بالقاعدة:
  **"One filter mechanism, not two"** — وكانت دي بالذات مشكلة النسخة اللي
  اتشالت.

### المطلوب

وحّد الآلية. إمّا الكل شرايح بتسميات ظاهرة، أو الكل قوايم + شرايح للمفعّل.
الموك-أب بيرشّح التاني.

**الملف:** `components/screens/tickets/TicketListScreen.tsx`.

---

## 8 — حالات التحميل والفراغ: الشاشة بتجاوب قبل ما تعرف

اتشاف مرتين في الجولة دي (`/` و`/tickets`) على أول رسمة:

- بلاطات العدّ الخمسة كلها `—` و **"Not tracked yet"**
- الجدول بيرسم حالته الفاضية: **"No tickets match these filters"**
- وجهة **Administration** والأفاتار مش موجودين

وبعد ما البيانات توصل بثانية، كله بيتغيّر. يعني الشاشة **بتجاوب على سؤال
لسه ما اتسألش**: "مفيش تذاكر مطابقة" ادعاء عن نتيجة طلب لسه شغّال، و"Not
tracked yet" ادعاء عن ميزة شغّالة فعلاً.

`components/screens/tickets/TicketListScreen.tsx` بقى بيعرض
`Loading tickets…` (أحسن من قبل كده، كان بيعرض كلمة "Tickets")، بس النص ده
**جنب** الجدول اللي بيقول مفيش نتايج، مش بدله.

### المطلوب

القاعدة: **حالة الفراغ متترسمش أبداً وقت أول تحميل.** ابنِ هيكل عظمي
(skeleton) بنفس أبعاد الصف النهائي — لا الجدول ينط، ولا الشاشة تدّعي حاجة.
ونفس الكلام على بلاطات العدّ: `—` تتقال بس لما العدّ يوصل فعلاً وهو `null`.

**الملفات:** `TicketListScreen.tsx`، `CountsStrip.tsx`،
`components/domain/DataTable/DataTable.tsx`.

> اختفاء "Administration" و الأفاتار على أول رسمة **مقصود ومكتوب سببه** في
> `Sidebar.tsx:45-50`. سيبه.

---

## 9 — ملاحظات أصغر

- **زرار "New ticket" ثانوي.** الموك-أب: `+ New ticket` مملوء بلون الـ accent
  وهو الفعل الأساسي في الشاشة. الشغّال: زرار بحدود بدون لون. حطّ معاه أيقونة `+`.
- **مفيش pager.** الموك-أب فيه
  `Showing 1–7 of 7 · filtered from 128 · 50 per page · Previous · Next`.
  القائمة الشغّالة مالهاش تذييل — الموظف مش عارف بيبص على كام من كام.
  و`TicketListParams` فيه `page` أصلاً.
- **جرس الإشعارات شبه غير مرئي** — رمادي فاتح على أبيض في الشريط العلوي.
  اتأكد من التباين مقابل `--fg-muted`.
- **مصفوفة أهداف الـ SLA** فيها تكرار تلاتي لكل خلية (عنوان العمود، ثم نفس
  الكلمة كتسمية، ثم جملة شرح) و**٨ أزرار حفظ**. اقرا `SettingRow` والمصفوفة
  مع بعض — دي أكتر لوحة مزدحمة في المنتج.
- **فورم دخول البوابة ~٧٥٠px وفورم الموظفين ~٣٨٠px.** نفس النوع من الشاشة،
  عرضين مختلفين. وحّدهم.

---

## 10 — ⚠️ متلمسش الحاجات دي

اتشالت من الموك-أب **عن قصد** ومكتوب في بنود الإنجاز. لو رجّعتها هتكون
بتخالف الخطة مش بتنفّذها:

- عمود التحديد الجماعي وشريط الأفعال الجماعية
- تبويبات الـ saved views
- مؤشّر الاتصال
- لوحة `AI summary` وأي معالجة AI
- وجهات القايمة الجانبية: Chat · Knowledge · Reports
- أقسام الإدارة: Knowledge settings · AI · Portal & branding · Integrations
- الفروع (branches) — مفيش جدول `branches` ومفيش عمود `branch_id`، وده
  متأكَّد منه بتست معماري في ستوري 497

**وكمان متشيلش** `status|sla|priority|channel` من `PRIMITIVE_SCALES` في
`scripts/check-tokens.mjs`. الحل إضافة أسماء دلالية، مش رفع الحظر.

---

## 11 — الترتيب المقترح

| # | الشغلانة | ليه هنا |
|---|---|---|
| 1 | أسماء دلالية للعائلات الأربعة (قسم 2) | كل حاجة بصرية تانية متوقّفة عليها |
| 2 | شارة الحالة + مقياس الأولوية + شريط SLA (قسم 3) | أعلى مردود لأقل شغل |
| 3 | صف سطرين + وقت نسبي (قسم 3) | بيدّي الصف تراتب |
| 4 | هياكل التحميل (قسم 8) | بيوقّف الشاشة عن الكدب |
| 5 | شريط أفعال رأس التذكرة (قسم 5-أ) | أهم فعل مدفون في `<select>` |
| 6 | توحيد شريط الفلاتر (قسم 7) | ازدحام جديد، يتظبط قبل ما ينسى |
| 7 | الحافة القابلة للتحرير (قسم 5-ب) | توقيع النظام، وأكبر شغل |
| 8 | تجميع طابور الرئيسية (قسم 4) | بيدّي الرئيسية شخصية |
| 9 | عدّادات قايمة الإدارة (قسم 6) | صغيرة ومفيدة |
| 10 | pager + الملاحظات الصغيرة (قسم 9) | تنضيف |

---

## 12 — اللي ماتفحصش

الجولة اتعملت بحساب `admin@ragab.test` على `localhost:3000`، إنجليزي وعربي،
على شاشة عريضة.

**ماتفحصش:** موبايل ٣٩٠px وتابلت ٧٦٨px بالعين (`resize_window` ماأثّرش على
منفذ العرض) — و`screen-responsive.html` بيحدد تلات نطاقات وقواعد R-01 لـ R-08
لازم حد يتأكد منها. كمان ماتفحصش: أي حاجة ورا تسجيل دخول البوابة،
`/customers/{id}`، `/admin/ticketing`، `/admin/platform`، `/admin/audit-log`،
وموك-أب `screen-customer-profile.html` و`screen-portal.html`
و`screen-reports.html` اتفتحوا بس ما اتقارنوش بند ببند.

**وملاحظة عن التوقيت:** كان فيه شغل إصلاح شغّال على الريبو أثناء المراجعة
(فلاتر جديدة، عدّادات الـ SLA، `OrganisationSection`، شاشة التذكرة الجديدة،
وتست معماري جديد `every-screen-is-reachable.test.ts`). ملاحظات الشكل هنا
اتاخدت من ملفات **ما اتلمستش** في الشغل ده — `tokens.css`، `SlaIndicator`،
`TicketPropertyRail`، `TicketDetailScreen`، `DataTable`، `Sidebar`، `TopBar` —
فهي لسه صحيحة. لو حاجة اتغيرت بعد كده، الموك-أب هو الحكم.
