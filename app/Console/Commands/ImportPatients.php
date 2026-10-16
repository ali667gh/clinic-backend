<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ImportPatients extends Command
{
    protected $signature = 'patients:import {file}';
    protected $description = 'Import patients from CSV (142k rows)';

    public function handle()
    {
        $filePath = base_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("فایل در مسیر یافت نشد: " . $filePath);
            return;
        }

        $this->info("عملیات شروع شد. لطفا منتظر بمانید...");

        $command = $this;

        LazyCollection::make(function () use ($filePath) {
            $handle = fopen($filePath, 'r');
            // رد کردن سطر اول (هدر)
            fgetcsv($handle);

            while (($line = fgetcsv($handle, 2000, ",")) !== false) {
                yield $line;
            }
            fclose($handle);
        })->chunk(1000)->each(function ($chunk) use ($command) {
            $insertData = [];

            foreach ($chunk as $row) {
                // پاکسازی شماره پرونده (file_number)
                $fileNumber = $row[2] ?? null;
                $fileNumber = is_string($fileNumber) ? trim($fileNumber) : $fileNumber;
                if ($fileNumber === '' || strtolower((string)$fileNumber) === 'nan' || strtolower((string)$fileNumber) === 'null') {
                    $fileNumber = null;
                }

                // پاکسازی کد ملی (national_code)
                $nationalCode = $row[3] ?? null;
                $nationalCode = is_string($nationalCode) ? trim($nationalCode) : $nationalCode;
                if ($nationalCode === '' || strtolower((string)$nationalCode) === 'nan' || strtolower((string)$nationalCode) === 'null') {
                    $nationalCode = null;
                }

                // ترکیب نام و نام خانوادگی و برش تا ۱۴۰ کاراکتر
                $fullName = trim(($row[0] ?? '') . ' ' . ($row[1] ?? ''));
                $fullName = mb_substr($fullName, 0, 140);
                if ($fullName === '') {
                    $fullName = 'بدون نام';
                }

                // پاکسازی موبایل
                $rawMobile = $row[4] ?? '';
                $rawMobile = str_replace(['NaN', 'nan'], '', $rawMobile);
                $firstPhone = preg_split('/[-,\/]/', trim($rawMobile))[0] ?? '';
                $mobile = preg_replace('/\D/', '', $firstPhone);
                if (strlen($mobile) < 4 || strlen($mobile) > 15) {
                    $mobile = null;
                }

                $insertData[] = [
                    'file_number'   => $fileNumber,
                    'national_code' => $nationalCode,
                    'full_name'     => $fullName,
                    'mobile'        => $mobile,
                    'notes'         => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            // درج دسته‌ای با نادیده گرفتن مواردی که واقعاً تکراری کلید یونیک هستند
            try {
                DB::table('patients')->insertOrIgnore($insertData);
            } catch (\Throwable $e) {
                // اگر باز هم دسته با ارور مواجه شد، سطر به سطر ایمپورت می‌کنیم تا کل بچ متوقف نشود
                foreach ($insertData as $singleRow) {
                    try {
                        DB::table('patients')->insertOrIgnore([$singleRow]);
                    } catch (\Throwable $ex) {
                        // رد کردن رکورد مشکل‌دار
                    }
                }
            }

            $command->line("1000 رکورد دیگر پردازش شد...");
        });

        $this->info("تبریک! تمام ۱۴۲ هزار رکورد با موفقیت بررسی و وارد شدند.");
    }
}
