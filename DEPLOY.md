# نشر ذبائح بلادي على السيرفر

يُرفع الخادم ولوحة الإدارة (مجلد `backend`) على الدومين. تطبيق الجوال يُبنى وحده ويتصل بـ `https://<الدومين>/api/v1`.

## قبل البدء
- **نسخة احتياطية من النظام الحالي**: ملفات الموقع + تصدير قاعدة البيانات (phpMyAdmin > Export أو `mysqldump`). احتفظ بها خارج السيرفر.
- المتطلبات: PHP 8.3+ مع الإضافات `mbstring, intl, pdo_mysql, bcmath, gd, zip, fileinfo`، وComposer، وMySQL 8 أو MariaDB 10.6+، وSSL على الدومين.

## الإعداد لأول مرة
```bash
git clone https://github.com/molhamhosini/zabayeh-bladi.git
cd zabayeh-bladi/backend
cp .env.example .env
```
عدّل `.env`:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<الدومين>
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
ADMIN_EMAIL=...          # بريد المدير
ADMIN_PASSWORD=...       # 12 حرفاً على الأقل
```
ثم:
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force     # الإمارات والمناطق والأقسام وخيارات التجهيز وحساب المدير
bash deploy.sh
```
- اجعل جذر الدومين (Document Root) يشير إلى `zabayeh-bladi/backend/public`.
- صلاحيات الكتابة: `backend/storage` و`backend/bootstrap/cache`.
- مهمة مجدولة (Cron) كل دقيقة: `* * * * * cd /path/zabayeh-bladi/backend && php artisan schedule:run >> /dev/null 2>&1`

### على cPanel
- ارفع المستودع خارج `public_html` (مثلاً `~/zabayeh-bladi`) عبر **Git Version Control** أو **Terminal**.
- من **Domains** غيّر Document Root للدومين إلى `zabayeh-bladi/backend/public`.
- من **MultiPHP Manager** اختر PHP 8.3، ومن **MySQL Databases** أنشئ القاعدة والمستخدم.

## المفاتيح (تُكتب في `.env` على السيرفر فقط، لا تُرسل في المحادثة ولا تُرفع إلى GitHub)
- Stripe: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` — Webhook: `https://<الدومين>/api/v1/webhooks/stripe`
- تابي: `TABBY_PUBLIC_KEY`, `TABBY_SECRET_KEY`, `TABBY_MERCHANT_CODE` — Webhook: `https://<الدومين>/api/v1/webhooks/tabby`
- تمارا: `TAMARA_API_TOKEN`, `TAMARA_NOTIFICATION_TOKEN` — Webhook: `https://<الدومين>/api/v1/webhooks/tamara`
- MSG91: من لوحة الإدارة > الإعدادات > رسائل SMS.

## التحديثات بعد ذلك
- يدوياً على السيرفر: `git pull && bash backend/deploy.sh`
- أو تلقائياً من GitHub: أضف في المستودع **Settings > Secrets and variables > Actions** القيم
  `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_PORT`, `DEPLOY_PATH`
  ثم من تبويب **Actions** شغّل **Deploy backend**. يشغّل الاختبارات أولاً ثم ينشر.
