<?php
/** @var \MODX\Revolution\modX $modx */

if (!$modx->getOption('maxnotify3.enabled', null, true)) {
    return;
}

$corePath = $modx->getOption(
    'maxnotify3.core_path',
    null,
    $modx->getOption('core_path') . 'components/maxnotify3/'
);

/** @var MaxNotify3 $maxNotify */
$maxNotify = $modx->getService('maxnotify3', 'MaxNotify3', $corePath . 'model/maxnotify3/');
if (!$maxNotify) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MaxNotify3] Could not load the MaxNotify3 service.');
    return;
}

switch ($modx->event->name) {
    case 'msOnCreateOrder':
        if ($modx->getOption('maxnotify3.notify_new_order', null, true) && isset($msOrder)) {
            $maxNotify->notifyOrderCreated($msOrder);
        }
        break;

    case 'msOnChangeOrderStatus':
        if ($modx->getOption('maxnotify3.notify_status_change', null, false) && isset($msOrder, $status)) {
            $maxNotify->notifyOrderStatus($msOrder, (int) $status);
        }
        break;
}
