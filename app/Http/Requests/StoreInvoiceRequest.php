<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Service;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => [
                'required',
                'integer',
                'exists:appointments,id',
                'unique:invoices,appointment_id', // هر نوبت فقط یک فاکتور
            ],
            'amount' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'lte:amount'],
            'status' => ['nullable', Rule::in(['unpaid', 'paid', 'cancelled'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'pos', 'online', 'card_to_card'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['required_with:items', 'integer', 'exists:services,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $this->input('items', []);

            if (empty($items)) {
                return;
            }

            $serviceIds = collect($items)
                ->pluck('service_id')
                ->filter()
                ->unique()
                ->values();

            $services = Service::query()
                ->whereIn('id', $serviceIds)
                ->get(['id', 'is_visit']);

            $visitCount = $services->where('is_visit', true)->count();

            if ($visitCount > 1) {
                $validator->errors()->add(
                    'items',
                    'امکان انتخاب بیش از یک خدمت ویزیت برای هر فاکتور وجود ندارد.',
                );
            }

            if ($visitCount === 0) {
                $validator->errors()->add(
                    'items',
                    'انتخاب حداقل یک خدمت ویزیت پایه الزامی است.',
                );
            }
        });
    }
}
