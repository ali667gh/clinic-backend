<?php

if (! function_exists('fa_num')) {
    /**
     * Convert English and Arabic numbers to Persian digits, with optional thousands grouping.
     *
     * @param mixed $value
     * @param bool $thousandsSeparator
     * @return string
     */
    function fa_num($value, bool $thousandsSeparator = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($thousandsSeparator && is_numeric($value)) {
            $value = number_format((float) $value);
        }

        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        return str_replace(array_merge($en, $ar), array_merge($fa, $fa), (string) $value);
    }
}
