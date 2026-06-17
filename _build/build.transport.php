<?php

use MODX\Revolution\modCategory;
use MODX\Revolution\modChunk;
use MODX\Revolution\modNamespace;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use xPDO\Transport\xPDOTransport;

require_once __DIR__ . '/build.config.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

class MaxNotify3BuildModX extends modX
{
    /** @var bool */
    public $suppressEvents = true;

    public function invokeEvent($eventName, array $params = [])
    {
        if ($this->suppressEvents) {
            return false;
        }

        return parent::invokeEvent($eventName, $params);
    }
}

class MaxNotify3PackageBuilder extends modPackageBuilder
{
    public function __construct(modX &$modx)
    {
        $this->modx =& $modx;
        $this->directory = MODX_CORE_PATH . 'packages/';
        $this->autoselects = [];
    }
}

$modx = new MaxNotify3BuildModX();
$modx->initialize('mgr');
$modx->suppressEvents = false;
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$builder = new MaxNotify3PackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);

/** @var modNamespace $namespace */
$namespace = $modx->newObject(modNamespace::class);
$namespace->fromArray([
    'name' => PKG_NAME_LOWER,
    'path' => '{core_path}components/' . PKG_NAME_LOWER . '/',
], '', true, true);
$builder->registerNamespace($namespace, false, true);

/** @var modCategory $category */
$category = $modx->newObject(modCategory::class);
$category->set('category', PKG_NAME);

$chunks = [
    'maxNotify3OrderCreated' => 'maxnotify3.order_created.chunk.tpl',
    'maxNotify3OrderCreatedHtml' => 'maxnotify3.order_created_html.chunk.tpl',
    'maxNotify3OrderStatus' => 'maxnotify3.order_status.chunk.tpl',
    'maxNotify3OrderStatusHtml' => 'maxnotify3.order_status_html.chunk.tpl',
];

$chunkObjects = [];
foreach ($chunks as $name => $file) {
    /** @var modChunk $chunk */
    $chunk = $modx->newObject(modChunk::class);
    $chunk->fromArray([
        'name' => $name,
        'description' => 'Шаблон уведомления MaxNotify 3 для MAX',
        'snippet' => file_get_contents(PKG_CORE . 'elements/chunks/' . $file),
    ], '', true, true);
    $chunkObjects[] = $chunk;
}
$category->addMany($chunkObjects);

$pluginCode = file_get_contents(PKG_CORE . 'elements/plugins/maxnotify3.plugin.php');
$pluginCode = preg_replace('/^<\?php\s*/', '', $pluginCode);

/** @var modPlugin $plugin */
$plugin = $modx->newObject(modPlugin::class);
$plugin->fromArray([
    'name' => PKG_NAME,
    'description' => 'Уведомления о заказах miniShop3 через MAX Business API или rumaxbot.ru',
    'plugincode' => $pluginCode,
], '', true, true);

$pluginEvents = [];
foreach (['msOnCreateOrder', 'msOnChangeOrderStatus'] as $eventName) {
    /** @var modPluginEvent $event */
    $event = $modx->newObject(modPluginEvent::class);
    $event->fromArray([
        'event' => $eventName,
        'priority' => 0,
        'propertyset' => 0,
    ], '', true, true);
    $pluginEvents[] = $event;
}
$plugin->addMany($pluginEvents);
$category->addMany([$plugin]);

$vehicle = $builder->createVehicle($category, [
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
        'Chunks' => [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => false,
        ],
        'Plugins' => [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                ],
            ],
        ],
    ],
]);

$vehicle->resolve('file', [
    'source' => PKG_CORE,
    'target' => "return MODX_CORE_PATH . 'components/';",
]);
$builder->putVehicle($vehicle);

$settings = [
    'enabled' => ['value' => true, 'xtype' => 'combo-boolean', 'area' => 'maxnotify3_main'],
    'provider' => ['value' => 'rumaxbot', 'xtype' => 'textfield', 'area' => 'maxnotify3_main'],
    'api_url' => ['value' => 'https://rumaxbot.ru/api/v1/messages', 'xtype' => 'textfield', 'area' => 'maxnotify3_api'],
    'api_key' => ['value' => '', 'xtype' => 'textfield', 'area' => 'maxnotify3_api'],
    'max_api_url' => ['value' => 'https://platform-api.max.ru/messages', 'xtype' => 'textfield', 'area' => 'maxnotify3_max_business'],
    'max_token' => ['value' => '', 'xtype' => 'textfield', 'area' => 'maxnotify3_max_business'],
    'max_recipient_type' => ['value' => 'chat_id', 'xtype' => 'textfield', 'area' => 'maxnotify3_max_business'],
    'max_recipient_ids' => ['value' => '', 'xtype' => 'textfield', 'area' => 'maxnotify3_max_business'],
    'max_notify' => ['value' => true, 'xtype' => 'combo-boolean', 'area' => 'maxnotify3_max_business'],
    'max_disable_link_preview' => ['value' => true, 'xtype' => 'combo-boolean', 'area' => 'maxnotify3_max_business'],
    'format' => ['value' => 'markdown', 'xtype' => 'textfield', 'area' => 'maxnotify3_api'],
    'timeout' => ['value' => 10, 'xtype' => 'numberfield', 'area' => 'maxnotify3_api'],
    'notify_new_order' => ['value' => true, 'xtype' => 'combo-boolean', 'area' => 'maxnotify3_events'],
    'notify_status_change' => ['value' => false, 'xtype' => 'combo-boolean', 'area' => 'maxnotify3_events'],
    'statuses' => ['value' => '', 'xtype' => 'textfield', 'area' => 'maxnotify3_events'],
];

foreach ($settings as $key => $data) {
    /** @var modSystemSetting $setting */
    $setting = $modx->newObject(modSystemSetting::class);
    $setting->fromArray([
        'key' => PKG_NAME_LOWER . '.' . $key,
        'value' => $data['value'],
        'xtype' => $data['xtype'],
        'namespace' => PKG_NAME_LOWER,
        'area' => $data['area'],
    ], '', true, true);

    $settingVehicle = $builder->createVehicle($setting, [
        xPDOTransport::UNIQUE_KEY => 'key',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => false,
    ]);
    $builder->putVehicle($settingVehicle);
}

$builder->setPackageAttributes([
    'name' => PKG_NAME,
    'author' => 'Mishiko23',
    'email' => 'bigo2008@gmail.com',
    'description' => 'Уведомления о заказах miniShop3 в MAX через официальный MAX Business API или сервис rumaxbot.ru.',
    'license' => file_get_contents(PKG_ROOT . 'LICENSE'),
    'readme' => file_get_contents(PKG_ROOT . 'README.md'),
    'changelog' => file_get_contents(PKG_ROOT . 'CHANGELOG.md'),
]);

$builder->pack();
