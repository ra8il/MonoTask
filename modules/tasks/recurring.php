<?php
function getNextDue($type, $time, $day_of_week = null, $day_of_month = null, $custom_days = null) {
    $now  = new DateTime();
    $next = new DateTime();
    list($hour, $minute) = explode(':', $time);
    $next->setTime((int)$hour, (int)$minute, 0);

    switch($type) {
        case 'daily':
            if($next <= $now) $next->modify('+1 day');
            break;
        case 'weekly':
            $days       = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            $target_day = $days[$day_of_week];
            $next->modify("next {$target_day}");
            $next->setTime((int)$hour, (int)$minute, 0);
            if($now->format('w') == $day_of_week && $next > $now) {
                $next->modify('-7 days');
                $next->setTime((int)$hour, (int)$minute, 0);
                if($next <= $now) $next->modify('+7 days');
            }
            break;
        case 'monthly':
            $next->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$day_of_month);
            $next->setTime((int)$hour, (int)$minute, 0);
            if($next <= $now) {
                $next->modify('+1 month');
                $next->setDate((int)$next->format('Y'), (int)$next->format('m'), (int)$day_of_month);
                $next->setTime((int)$hour, (int)$minute, 0);
            }
            break;
        case 'custom':
            $next->modify("+{$custom_days} days");
            $next->setTime((int)$hour, (int)$minute, 0);
            break;
    }

    return $next->format('Y-m-d H:i:s');
}