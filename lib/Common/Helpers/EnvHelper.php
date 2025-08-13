<?php

namespace LibreBooking\Common\Helpers {

function env(string $key, $default = null)
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }

    $value = getenv($key);

    return $value === false ? $default : $value;
}
}

namespace {
    if (!function_exists('env')) {
        function env(string $key, $default = null) {
            return \LibreBooking\Common\Helpers\env($key, $default);
        }
    }
}
