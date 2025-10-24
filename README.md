# نمونه کد لاراول برای API سپیدار (Sepidar Laravel Example)

این ریپازیتوری یک «پکیج» قابل نصب با Composer **نیست**.

این مجموعه، شامل کلاس‌های سرویس (Service Classes) آماده برای استفاده در پروژه‌های **لاراول** است تا به سرعت بتوانید به API سپیدار متصل شوید. این سرویس‌ها وظیفه ثبت دستگاه، احراز هویت، مدیریت توکن، و رمزنگاری‌های مورد نیاز برای ارسال درخواست‌ها را به صورت خودکار انجام می‌دهDEN.

---

## لینک‌های مهم

* **مستندات API (Swagger):** [**pourjanali.github.io/sepidar-api-docs**](https://pourjanali.github.io/sepidar-api-docs/)
* **SDK اصلی PHP (خالص):** [**sepidar-php-sdk**](https://github.com/pourjanali/sepidar-php-sdk)
* **SDK پایتون:** [**sepidar-python-sdk**](https://github.com/pourjanali/sepidar-python-sdk)

---

## پیش‌نیازها

این سرویس‌ها مستقیماً از کامپوننت‌های لاراول (مانند `Http`, `Cache`, `Storage`, `Log`) و چند پکیج خارجی استفاده می‌کنند.

مطمئن شوید که پکیج‌های زیر در پروژه لاراولی شما نصب شده باشند:

```bash
composer require phpseclib/phpseclib ramsey/uuid
````

## نحوه راه‌اندازی (کپی-پیست)

**۱. کپی کردن فایل‌ها**

فایل‌های موجود در پوشه `Example/Services` این ریپازیتوری را در پروژه لاراولی خود کپی کنید. بهترین مسیر پیشنهادی:

```
App/
  Services/
    Sepidar/
      SepidarBaseService.php
      SepidarAuthService.php
```

(اگر پوشه `App/Services` یا `App/Services/Sepidar` وجود ندارد، آن‌ها را بسازید)

**۲. بررسی Namespace**

مطمئن شوید که `namespace` بالای هر دو فایل `App\Services\Sepidar` باشد (که به صورت پیش‌فرض در فایل‌ها تنظیم شده است).

**۳. تنظیم متغیرهای محیطی (.env)**

متغیرهای زیر را به فایل `.env` پروژه لاراولی خود اضافه کنید و مقادیر آن را با اطلاعات دریافتی از سپیدار پر کنید:

```dotenv
SEPIDAR_API_URL=[https://api.sepidarsystem.com/api/v1](https://api.sepidarsystem.com/api/v1)
SEPIDAR_SERIAL=12345678
SEPIDAR_GEN_VER=110
SEPIDAR_USERNAME=your-username
SEPIDAR_PASSWORD=your-password
```

**۴. ساخت پوشه Storage**

سرویس سپیدار برای ذخیره کلید عمومی و فایل‌های ثبت‌نام به پوشه `storage/app/sepidar` نیاز دارد. این پوشه را بسازید و از قابل نوشتن (writable) بودن آن اطمینان حاصل کنید:

```bash
mkdir -p storage/app/sepidar
```

**۵. (اختیاری) حذف سرویس تلگرام**

در فایل `SepidarBaseService.php`، در متد `checkStatus`، یک خط کامنت شده برای ارسال پیام تلگرام وجود دارد (`//TelegramService::sendMessage(...)`). اگر از چنین سرویسی استفاده نمی‌کنید، می‌توانید آن خط و `use App\Services\TelegramService;` را از بالای فایل حذف کنید.

## نحوه استفاده

حالا می‌توانید `SepidarAuthService` را در هر کجای پروژه (مانند کنترلرها) تزریق (Inject) و استفاده کنید.

```php
use App\Services\Sepidar\SepidarAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;

class MySepidarController extends Controller
{
    protected $sepidar;

    // سرویس به صورت خودکار توسط لاراول تزریق می‌شود
    public function __construct(SepidarAuthService $sepidar)
    {
        $this->sepidar = $sepidar;
    }

    /**
     * مرحله ۱: ثبت دستگاه (فقط یک بار اجرا شود)
     * این متد دستگاه شما را ثبت کرده و فایل‌های مورد نیاز برای رمزنگاری را می‌سازد
     */
    public function registerDevice()
    {
        $result = $this->sepidar->register(); //
        
        if ($result['success']) {
            // پس از ثبت، کلید عمومی را استخراج کنید
            $keyResult = $this->sepidar->extractPublicKey(); //
            return $keyResult;
        }

        return $result;
    }

    /**
     * مرحله ۲: لاگین و دریافت توکن
     * توکن به صورت خودکار در کش لاراول ذخیره می‌شود
     */
    public function login()
    {
        $result = $this->sepidar->login(); //
        return $result;
    }

    /**
     * مرحله ۳: ارسال درخواست به API
     * این متد هدرهای لازم (شامل توکن) را آماده می‌کند
     */
    public function getInvoices()
    {
        try {
            // این متد به صورت خودکار چک می‌کند، اگر توکن منقضی شده باشد، لاگین می‌کند
            $headers = $this->sepidar->getAuthenticatedHeaders(); //
            $baseUrl = config('sepidar.base_url', env('SEPIDAR_API_URL')); // خواندن از .env

            // ارسال درخواست به هر End-Point دلخواه
            $response = Http::withHeaders($headers)
                ->post($baseUrl . '/Invoice/GetInvoices', [
                    'some' => 'data'
                ]);

            if (!$response->successful()) {
                return $this->sepidar->sepidarError($response); //
            }

            return $response->json();

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * (کمکی) بررسی وضعیت API
     */
    public function checkApiStatus()
    {
        return $this->sepidar->checkStatus(); //
    }
}
```

## لایسنس

این پروژه تحت لایسنس MIT منتشر شده است. برای جزئیات بیشتر [فایل LICENSE](https://github.com/pourjanali/sepidar-laravel/blob/main/LICENSE) را مطالعه کنید.
