<?php

namespace App\Traits;

trait Translatable
{
    public function getAttribute($key)
    {
        if (in_array($key, $this->translatable ?? [])) {
            $locale = app()->getLocale();
            $localizedKey = $key . '_' . $locale;

            if (array_key_exists($localizedKey, $this->attributes)) {
                $value = $this->getAttributeValue($localizedKey);
                if (!empty($value)) {
                    return $value;
                }
            }

            $defaultKey = $key . '_en';
            if (array_key_exists($defaultKey, $this->attributes)) {
                return $this->getAttributeValue($defaultKey);
            }
        }

        return parent::getAttribute($key);
    }
}