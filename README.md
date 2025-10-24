# 🛠️ نمونه کد لاراول برای API سپیدار (Sepidar Laravel Example)

> ⚠️ این ریپازیتوری یک **پکیج Composer** نیست و صرفاً شامل کلاس‌های سرویس آماده برای استفاده در پروژه‌های لاراول است.

این مجموعه به شما کمک می‌کند تا به سرعت به **API سپیدار** متصل شوید. سرویس‌ها شامل امکانات زیر هستند:

- ✅ ثبت دستگاه (Device Registration)  
- ✅ احراز هویت و مدیریت توکن  
- ✅ رمزنگاری‌های لازم برای ارسال درخواست‌ها  

---

## 🔗 لینک‌های مهم

- **مستندات API (Swagger):** [pourjanali.github.io/sepidar-api-docs](https://pourjanali.github.io/sepidar-api-docs)  
- **SDK اصلی PHP:** [sepidar-php-sdk](https://github.com/pourjanali/sepidar-php-sdk)  
- **SDK پایتون:** [sepidar-python-sdk](https://github.com/pourjanali/sepidar-python-sdk)  

---

## ⚙️ پیش‌نیازها

این سرویس‌ها از کامپوننت‌های لاراول مانند `Http`, `Cache`, `Storage`, `Log` و چند پکیج خارجی استفاده می‌کنند.  
پکیج‌های زیر باید نصب باشند:

```bash
composer require phpseclib/phpseclib ramsey/uuid
````

---

## 🚀 نحوه راه‌اندازی (کپی-پیست)

### ۱️⃣ کپی کردن فایل‌ها

فایل‌های داخل پوشه `Example/Services` را به پروژه لاراولی خود منتقل کنید:

```
App/
  Services/
    Sepidar/
      SepidarBaseService.php
      SepidarAuthService.php
```

> اگر پوشه‌ها وجود ندارند، آن‌ها را بسازید.

### ۲️⃣ بررسی Namespace

اطمینان حاصل کنید که بالای هر فایل نوشته شده باشد:

```php
namespace App\Services\Sepidar;
```

### ۳️⃣ تنظیم متغیرهای محیطی (.env)

مقادیر زیر را در `.env` اضافه کنید و با اطلاعات سپیدار پر کنید:

```dotenv
SEPIDAR_API_URL=https://api.sepidarsystem.com/api/v1
SEPIDAR_SERIAL=12345678
SEPIDAR_GEN_VER=110
SEPIDAR_USERNAME=your-username
SEPIDAR_PASSWORD=your-password
```

### ۴️⃣ ساخت پوشه Storage

برای ذخیره کلید عمومی و فایل‌های ثبت‌نام:

```bash
mkdir -p storage/app/sepidar
```

اطمینان حاصل کنید که پوشه قابل نوشتن است.

### ۵️⃣ (اختیاری) حذف سرویس تلگرام

در `SepidarBaseService.php`، متد `checkStatus` شامل خطی برای ارسال پیام تلگرام است:

```php
// TelegramService::sendMessage(...)
```

اگر استفاده نمی‌کنید، این خط و `use App\Services\TelegramService;` را حذف کنید.

---

## 📝 نحوه استفاده

`SepidarAuthService` را می‌توان در کنترلرها تزریق و استفاده کرد:

```php
use App\Services\Sepidar\SepidarAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;

class MySepidarController extends Controller
{
    protected $sepidar;

    public function __construct(SepidarAuthService $sepidar)
    {
        $this->sepidar = $sepidar;
    }

    /** 
     * ۱️⃣ مرحله ۱: ثبت دستگاه (یک بار اجرا شود)
     */
    public function registerDevice()
    {
        $result = $this->sepidar->register();

        if ($result['success']) {
            return $this->sepidar->extractPublicKey();
        }

        return $result;
    }

    /** 
     * ۲️⃣ مرحله ۲: لاگین و دریافت توکن
     */
    public function login()
    {
        return $this->sepidar->login();
    }

    /** 
     * ۳️⃣ مرحله ۳: ارسال درخواست به API
     */
    public function getInvoices()
    {
        try {
            $headers = $this->sepidar->getAuthenticatedHeaders();
            $baseUrl = config('sepidar.base_url', env('SEPIDAR_API_URL'));

            $response = Http::withHeaders($headers)
                ->post($baseUrl . '/Invoice/GetInvoices', [
                    'some' => 'data'
                ]);

            if (!$response->successful()) {
                return $this->sepidar->sepidarError($response);
            }

            return $response->json();

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /** 
     * 🔍 بررسی وضعیت API (کمکی)
     */
    public function checkApiStatus()
    {
        return $this->sepidar->checkStatus();
    }
}
```

---

## 📄 لایسنس

این پروژه تحت **MIT License** منتشر شده است. جزئیات در [فایل LICENSE](https://github.com/pourjanali/sepidar-laravel/blob/main/LICENSE) موجود است.
