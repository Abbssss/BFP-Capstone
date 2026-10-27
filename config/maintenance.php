<?php

return [
    'priority_warning_days' => (int) env('MAINTENANCE_PRIORITY_WARNING_DAYS', 30),
    'priority_critical_overdue_days' => (int) env('MAINTENANCE_PRIORITY_CRITICAL_OVERDUE_DAYS', 30),
];
