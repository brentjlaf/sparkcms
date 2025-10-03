<?php
// File: CMS/modules/events/helpers.php
// This file now acts as a thin wrapper around the EventsService for legacy includes.

require_once __DIR__ . '/EventsService.php';

if (!function_exists('events_service')) {
    function events_service(): EventsService
    {
        static $service = null;
        if ($service === null) {
            $service = EventsService::createDefault();
        }
        return $service;
    }
}
