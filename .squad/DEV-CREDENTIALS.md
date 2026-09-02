# بيانات الدخول — بيئة التطوير المحلية

> ملف تطوير محلي. مايتنشرش ومايتحطش في أي بيئة حقيقية.

## العناوين

| الحاجة | الرابط |
|---|---|
| الواجهة | http://localhost:3000 |
| تسجيل الدخول | http://localhost:3000/sign-in |
| الـ API | http://localhost:8000/api/v1 |
| Postgres | `127.0.0.1:5432` — قاعدة `ragab` / مستخدم `ragab` / باسورد `ragab` |

## الحسابات الموجودة في قاعدة البيانات دلوقتي

استخرجتهم بالاستعلام ده من الكونتينر:

```sql
select u.email, u.name, r.name
from users u
left join model_has_roles mhr on mhr.model_id = u.id
left join roles r on r.id = mhr.role_id;
```

| الإيميل | الاسم | الدور | الباسورد |
|---|---|---|---|
| `admin@ragab.test` | Admin | administrator | `Correct-Horse-9` — موثّق في `README.md` سطر 228 |
| `super@ragab.test` | Super | supervisor | **غير معروف** |
| `agent@ragab.test` | Agent | agent | **غير معروف** |

**ابدأ بـ `admin@ragab.test`** — هو الوحيد اللي الباسورد بتاعه مكتوب في الريبو، وهو كمان الدور الوحيد اللي بيشوف قسم Administration.

## ⚠️ الحسابات دي مش من الـ seeder

دوّرت في الريبو كله: **مفيش seeder بيعمل الحسابات التلاتة دي**.

- `DatabaseSeeder.php` بيعمل حساب واحد بس: `test@example.com` عن طريق `User::factory()`
- `UserFactory.php` سطر 31 بيحط الباسورد `password` لأي حساب بيعمله
- `RolesAndPermissionsSeeder.php` بيعمل الأدوار والصلاحيات، مش مستخدمين

يعني حسابات `@ragab.test` اتعملت **يدوياً** (tinker أو استعلام مباشر). النتيجة المهمة:

> **`php artisan migrate:fresh --seed` هيمسحهم ومش هيرجّعهم.**

لو شغّلت `./scripts/restart.sh` من غير `--no-fresh`، هتلاقي نفسك من غير أي حساب تدخل بيه غير `test@example.com` بباسورد `password`.

## الحسابات بعد `migrate:fresh --seed`

| الإيميل | الباسورد | الدور |
|---|---|---|
| `test@example.com` | `password` | **مفيش دور** — يعني مش هيشوف Administration |

## لو عايز الحسابات التلاتة ترجع بعد كل fresh

محتاج تضيفهم للـ seeder. **ما عملتش ده** — طلبت مني ما أصلحش أي كود. لو حبيت، ده اللي المفروض يتضاف في `DatabaseSeeder::run()`:

```php
foreach ([
    ['Admin', 'admin@ragab.test', 'administrator'],
    ['Super', 'super@ragab.test', 'supervisor'],
    ['Agent', 'agent@ragab.test', 'agent'],
] as [$name, $email, $role]) {
    User::factory()->create(['name' => $name, 'email' => $email])->assignRole($role);
}
```

الباسورد ساعتها هيبقى `password` للتلاتة (من الـ factory).

## ملاحظة عن المتصفح

صفحة `/sign-in` عندك بيملاها Chrome أوتوماتيك بـ `admin@pharmacy.local` وباسورد محفوظ — **دي بيانات مشروع تاني**، مش بتاعة المشروع ده، ومش هتشتغل. امسحها من الحقول قبل ما تكتب البيانات الصح.

أنا **ما كتبتش أي باسورد في أي حقل** ومش هعمل كده — ده خط أحمر عندي حتى على بيئة محلية.
