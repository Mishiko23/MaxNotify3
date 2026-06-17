<?php

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

class MaxNotify3
{
    public const VERSION = '1.0.0';

    /** @var modX */
    public $modx;

    /** @var array */
    protected $config = [];

    public function __construct(modX &$modx, array $config = [])
    {
        $this->modx =& $modx;

        $corePath = $this->modx->getOption(
            'maxnotify3.core_path',
            $config,
            $this->modx->getOption('core_path') . 'components/maxnotify3/'
        );

        $this->config = array_merge([
            'corePath' => $corePath,
            'provider' => strtolower((string) $this->modx->getOption(
                'maxnotify3.provider',
                null,
                'rumaxbot'
            )),
            'apiUrl' => $this->modx->getOption(
                'maxnotify3.api_url',
                null,
                'https://rumaxbot.ru/api/v1/messages'
            ),
            'apiKey' => trim((string) $this->modx->getOption('maxnotify3.api_key', null, '')),
            'maxApiUrl' => $this->modx->getOption(
                'maxnotify3.max_api_url',
                null,
                'https://platform-api.max.ru/messages'
            ),
            'maxToken' => trim((string) $this->modx->getOption('maxnotify3.max_token', null, '')),
            'maxRecipientType' => strtolower((string) $this->modx->getOption(
                'maxnotify3.max_recipient_type',
                null,
                'chat_id'
            )),
            'maxRecipientIds' => trim((string) $this->modx->getOption(
                'maxnotify3.max_recipient_ids',
                null,
                ''
            )),
            'maxNotify' => (bool) $this->modx->getOption('maxnotify3.max_notify', null, true),
            'maxDisableLinkPreview' => (bool) $this->modx->getOption(
                'maxnotify3.max_disable_link_preview',
                null,
                true
            ),
            'format' => strtolower((string) $this->modx->getOption('maxnotify3.format', null, 'markdown')),
            'timeout' => max(1, (int) $this->modx->getOption('maxnotify3.timeout', null, 10)),
        ], $config);
    }

    public function notifyOrderCreated($order): bool
    {
        return $this->sendOrderMessage($order, 'maxNotify3OrderCreated');
    }

    public function notifyOrderStatus($order, $statusId): bool
    {
        if (!$this->isStatusAllowed($statusId)) {
            return true;
        }

        return $this->sendOrderMessage($order, 'maxNotify3OrderStatus', $statusId);
    }

    protected function sendOrderMessage($order, string $chunkName, ?int $statusId = null): bool
    {
        if (!$order || !is_object($order) || !method_exists($order, 'get')) {
            $this->log(modX::LOG_LEVEL_ERROR, 'Order object was not provided.');
            return false;
        }

        if ($this->config['format'] === 'html') {
            $chunkName .= 'Html';
        }

        $placeholders = $this->getOrderPlaceholders($order, $statusId);
        $message = trim((string) $this->modx->getChunk($chunkName, $placeholders));

        if ($message === '') {
            $this->log(modX::LOG_LEVEL_ERROR, 'Message chunk is empty or missing: ' . $chunkName);
            return false;
        }

        return $this->send($message);
    }

    protected function getOrderPlaceholders($order, ?int $statusId = null): array
    {
        $address = method_exists($order, 'getOne') ? $order->getOne('Address') : null;
        $addressFields = [
            'first_name', 'last_name', 'phone', 'email', 'country', 'index', 'region', 'city',
            'metro', 'street', 'building', 'entrance', 'floor', 'room', 'comment', 'text_address',
        ];

        $values = [
            'id' => $order->get('id'),
            'uuid' => $order->get('uuid'),
            'num' => $order->get('num'),
            'cost' => $this->formatNumber($order->get('cost')),
            'cart_cost' => $this->formatNumber($order->get('cart_cost')),
            'delivery_cost' => $this->formatNumber($order->get('delivery_cost')),
            'weight' => $this->formatNumber($order->get('weight')),
            'createdon' => $order->get('createdon'),
            'user_id' => $order->get('user_id'),
            'customer_id' => $order->get('customer_id'),
            'delivery_id' => $this->getOrderField($order, 'delivery_id', 'delivery'),
            'payment_id' => $this->getOrderField($order, 'payment_id', 'payment'),
            'status_id' => $statusId ?? (int) $this->getOrderField($order, 'status_id', 'status'),
            'order_comment' => $order->get('order_comment'),
            'manager_url' => $this->getManagerUrl($order->get('id')),
        ];

        foreach ($addressFields as $field) {
            $values[$field] = $address ? $address->get($field) : '';
        }

        $values['receiver'] = trim($values['first_name'] . ' ' . $values['last_name']);
        $values['status_name'] = $this->getStatusName((int) $values['status_id']);
        $values['delivery_name'] = $this->getRelatedName(msDelivery::class, (int) $values['delivery_id']);
        $values['payment_name'] = $this->getRelatedName(msPayment::class, (int) $values['payment_id']);
        $values['address'] = $this->getAddressText($values);

        foreach ($values as $key => $value) {
            $values[$key] = $key === 'manager_url'
                ? $this->escapeUrl($value)
                : $this->escape($value);
        }

        $values['products'] = $this->getProductsText($order);

        return $values;
    }

    protected function getOrderField($order, string $newField, string $legacyField)
    {
        $value = $order->get($newField);
        return $value !== null && $value !== '' ? $value : $order->get($legacyField);
    }

    protected function getProductsText($order): string
    {
        if (!method_exists($order, 'getMany')) {
            return '';
        }

        $lines = [];
        foreach ((array) $order->getMany('Products') as $product) {
            $name = trim((string) $product->get('name'));
            if ($name === '') {
                $name = '#' . $product->get('product_id');
            }

            $line = $this->escape($name)
                . ' x ' . $this->escape($this->formatNumber($product->get('count')))
                . ' = ' . $this->escape($this->formatNumber($product->get('cost')));

            $lines[] = $this->config['format'] === 'html'
                ? '<li>' . $line . '</li>'
                : '- ' . $line;
        }

        if ($this->config['format'] === 'html') {
            return $lines ? '<ul>' . implode('', $lines) . '</ul>' : '';
        }

        return implode("\n", $lines);
    }

    protected function getManagerUrl($orderId): string
    {
        $managerUrl = (string) $this->modx->getOption('manager_url');
        if (!preg_match('#^https?://#i', $managerUrl)) {
            $managerUrl = rtrim((string) $this->modx->getOption('site_url'), '/')
                . '/' . ltrim($managerUrl, '/');
        }

        return rtrim($managerUrl, '/') . '/?a=mgr/orders&namespace=minishop3&order=' . (int) $orderId;
    }

    protected function getRelatedName(string $class, int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        $object = $this->modx->getObject($class, $id);
        return $object ? (string) $object->get('name') : '';
    }

    protected function getAddressText(array $values): string
    {
        if (!empty($values['text_address'])) {
            return (string) $values['text_address'];
        }

        $parts = [];
        foreach (['index', 'region', 'city', 'street', 'building'] as $field) {
            if (!empty($values[$field])) {
                $parts[] = (string) $values[$field];
            }
        }

        foreach ([
            'entrance' => 'подъезд',
            'floor' => 'этаж',
            'room' => 'кв./офис',
        ] as $field => $label) {
            if (!empty($values[$field])) {
                $parts[] = $label . ' ' . $values[$field];
            }
        }

        return implode(', ', $parts);
    }

    protected function getStatusName(int $statusId): string
    {
        if ($statusId <= 0) {
            return '';
        }

        $status = $this->modx->getObject(msOrderStatus::class, $statusId);
        return $status ? (string) $status->get('name') : (string) $statusId;
    }

    protected function isStatusAllowed($statusId): bool
    {
        $configured = trim((string) $this->modx->getOption('maxnotify3.statuses', null, ''));
        if ($configured === '') {
            return true;
        }

        $statuses = array_filter(array_map('trim', explode(',', $configured)), 'strlen');
        $statuses = array_map('intval', $statuses);

        return in_array((int) $statusId, $statuses, true);
    }

    public function send($message): bool
    {
        $format = in_array($this->config['format'], ['markdown', 'html'], true)
            ? $this->config['format']
            : 'markdown';

        if (in_array($this->config['provider'], ['maxbusiness', 'max', 'official'], true)) {
            return $this->sendMaxBusiness((string) $message, $format);
        }

        return $this->sendRumaxbot((string) $message, $format);
    }

    protected function sendRumaxbot(string $message, string $format): bool
    {
        if ($this->config['apiKey'] === '') {
            $this->log(modX::LOG_LEVEL_ERROR, 'System setting maxnotify3.api_key is empty.');
            return false;
        }

        $payload = $this->encodePayload([
            'text' => $message,
            'format' => $format,
        ]);

        if ($payload === false) {
            return false;
        }

        return $this->sendRequest(
            $this->config['apiUrl'],
            $payload,
            'Bearer ' . $this->config['apiKey'],
            'rumaxbot.ru'
        );
    }

    protected function sendMaxBusiness(string $message, string $format): bool
    {
        if ($this->config['maxToken'] === '') {
            $this->log(modX::LOG_LEVEL_ERROR, 'System setting maxnotify3.max_token is empty.');
            return false;
        }

        $recipientType = in_array($this->config['maxRecipientType'], ['chat_id', 'user_id'], true)
            ? $this->config['maxRecipientType']
            : 'chat_id';
        $recipientIds = preg_split('/[\s,;]+/', $this->config['maxRecipientIds'], -1, PREG_SPLIT_NO_EMPTY);
        $recipientIds = array_values(array_filter($recipientIds, static function ($id) {
            return preg_match('/^-?\d+$/', $id);
        }));

        if (!$recipientIds) {
            $this->log(
                modX::LOG_LEVEL_ERROR,
                'System setting maxnotify3.max_recipient_ids does not contain a valid recipient ID.'
            );
            return false;
        }

        $payload = $this->encodePayload([
            'text' => $this->limitText($message, 4000),
            'format' => $format,
            'notify' => $this->config['maxNotify'],
        ]);

        if ($payload === false) {
            return false;
        }

        $success = true;
        foreach ($recipientIds as $recipientId) {
            $query = [
                $recipientType => $recipientId,
                'disable_link_preview' => $this->config['maxDisableLinkPreview'] ? 'true' : 'false',
            ];
            $separator = strpos($this->config['maxApiUrl'], '?') === false ? '?' : '&';
            $url = $this->config['maxApiUrl'] . $separator . http_build_query($query);

            if (!$this->sendRequest($url, $payload, $this->config['maxToken'], 'MAX Business')) {
                $success = false;
            }
        }

        return $success;
    }

    protected function encodePayload(array $data)
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            $this->log(modX::LOG_LEVEL_ERROR, 'Could not encode the API request as JSON.');
            return false;
        }

        return $payload;
    }

    protected function sendRequest(string $url, string $payload, string $authorization, string $service): bool
    {
        if (function_exists('curl_init')) {
            return $this->sendWithCurl($url, $payload, $authorization, $service);
        }

        return $this->sendWithStreams($url, $payload, $authorization, $service);
    }

    protected function sendWithCurl(string $url, string $payload, string $authorization, string $service): bool
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->config['timeout'],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authorization,
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: MaxNotify3/' . self::VERSION,
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($response === false) {
            $this->log(modX::LOG_LEVEL_ERROR, $service . ' transport error: ' . $error);
            return false;
        }

        return $this->validateResponse($status, $response, $service);
    }

    protected function sendWithStreams(string $url, string $payload, string $authorization, string $service): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $this->config['timeout'],
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Authorization: ' . $authorization,
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: MaxNotify3/' . self::VERSION,
                ]),
                'content' => $payload,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $headers = isset($http_response_header) ? $http_response_header : [];
        $status = $this->getStatusFromHeaders($headers);

        if ($response === false && $status === 0) {
            $this->log(
                modX::LOG_LEVEL_ERROR,
                $service . ' transport error. Enable cURL or allow_url_fopen and verify outbound HTTPS access.'
            );
            return false;
        }

        return $this->validateResponse($status, (string) $response, $service);
    }

    protected function getStatusFromHeaders(array $headers): int
    {
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }

    protected function validateResponse(int $status, string $response, string $service): bool
    {
        if ($status >= 200 && $status < 300) {
            return true;
        }

        $body = trim(strip_tags($response));
        if (strlen($body) > 500) {
            $body = substr($body, 0, 500) . '...';
        }

        $this->log(
            modX::LOG_LEVEL_ERROR,
            $service . ' returned HTTP ' . $status . ($body !== '' ? ': ' . $body : '')
        );

        return false;
    }

    protected function limitText(string $text, int $limit): string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length <= $limit) {
            return $text;
        }

        $suffix = "\n...";
        $sliceLength = $limit - strlen($suffix);
        $text = function_exists('mb_substr')
            ? mb_substr($text, 0, $sliceLength, 'UTF-8')
            : substr($text, 0, $sliceLength);

        return rtrim($text) . $suffix;
    }

    protected function escape($value): string
    {
        $value = (string) $value;

        if ($this->config['format'] === 'html') {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        return preg_replace('/([\\\\`*_{}\[\]()#+.!>|~-])/', '\\\\$1', $value);
    }

    protected function escapeUrl($value): string
    {
        $value = (string) $value;
        return $this->config['format'] === 'html'
            ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            : $value;
    }

    protected function formatNumber($value): string
    {
        $number = (float) $value;
        return number_format($number, $number == floor($number) ? 0 : 2, '.', ' ');
    }

    protected function log(int $level, string $message): void
    {
        $this->modx->log($level, '[MaxNotify3] ' . $message);
    }
}
