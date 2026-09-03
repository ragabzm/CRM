# بيانات الدخول — بيئة التطوير المحلية

> ملف تطوير محلي. مايتنشرش ومايتحطش في أي بيئة حقيقية.

## الحسابات

| الإيميل | الدور | الباسورد |
|---|---|---|
| `admin@ragab.test` | administrator | `Correct-Horse-9` |
| `super@ragab.test` | supervisor | `Correct-Horse-9` |
| `agent@ragab.test` | agent | `Correct-Horse-9` |

**اتأكدت من التلاتة بتسجيل دخول فعلي — 2026-09-02.** كلهم رجّعوا 200 وبالدور
الصح.

`admin@ragab.test` هو الوحيد اللي بيشوف قسم Administration.

## العناوين

| الحاجة | الرابط |
|---|---|
| الواجهة | http://localhost:3000 |
| تسجيل الدخول | http://localhost:3000/sign-in |
| الـ API | http://localhost:8000/api/v1 |
| Postgres | `127.0.0.1:5432` — قاعدة `ragab` / مستخدم `ragab` / باسورد `ragab` |

## الحسابات دي بقت في الـ seeder

كانت اتعملت **يدوياً** قبل كده، فـ `migrate:fresh --seed` كان بيمسحهم ومايرجّعهمش.
**وده حصل فعلاً:** الجدول اتلقى فاضي والملف ده لسه بيقول إنهم موجودين.

دلوقتي `DevAccountsSeeder` بيعملهم، و`DatabaseSeeder` بينده عليه. يعني
`migrate:fresh --seed` بيرجّعهم.

لو اتمسحوا لأي سبب:

```bash
docker compose exec backend-web php artisan db:seed --class=DevAccountsSeeder
```

الـ seeder مكتفي بنفسه — بيعمل الأدوار الأول، فالأمر ده شغال لوحده من غير
`--seed` كامل.

### ليه باسورد معروف مكتوب في ملف

`DevAccountsSeeder` **بيرفض يشتغل خارج `local` و `testing`**. مفيش بيئة
الباسورد ده بيفتح فيها حاجة. لو الحارس ده اتشال، الباسورد لازم يتشال معاه.

## لو الدخول رجّع 500 بدل ما يشتغل

`Session store not set on request.` معناها إن الطلب مجاش من نطاق مسجّل في
`SANCTUM_STATEFUL_DOMAINS`. المتصفح على `localhost:3000` مظبوط. لو بتجرّب
بـ `curl` لازم تبعت:

```
-H 'Origin: http://localhost:3000'
```

من غيرها Sanctum مابيشغّلش وضع الجلسة أصلاً.

## ملاحظة عن المتصفح

صفحة `/sign-in` عندك بيملاها Chrome أوتوماتيك بـ `admin@pharmacy.local` وباسورد
محفوظ — **دي بيانات مشروع تاني** ومش هتشتغل. امسحها من الحقول قبل ما تكتب
البيانات الصح.
