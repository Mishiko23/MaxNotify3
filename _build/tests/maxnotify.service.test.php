<?php

namespace MODX\Revolution {
    class modX
    {
        public const LOG_LEVEL_ERROR = 1;

        public $logs = [];
        private $options = [];

        public function __construct(array $options = [])
        {
            $this->options = $options;
        }

        public function getOption($key, $options = null, $default = null)
        {
            return $this->options[$key] ?? $default;
        }

        public function log($level, $message)
        {
            $this->logs[] = [$level, $message];
        }
    }
}

namespace {
    require_once dirname(dirname(__DIR__))
        . '/core/components/maxnotify3/model/maxnotify3/maxnotify3.class.php';

    class TestMaxNotify3 extends MaxNotify3
    {
        public $requests = [];

        protected function sendRequest(string $url, string $payload, string $authorization, string $service): bool
        {
            $this->requests[] = compact('url', 'payload', 'authorization', 'service');
            return true;
        }
    }

    function assertTrue($condition, $message): void
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}\n");
            exit(1);
        }
    }

    $modx = new \MODX\Revolution\modX();
    $official = new TestMaxNotify3($modx, [
        'provider' => 'maxbusiness',
        'maxApiUrl' => 'https://platform-api.max.ru/messages',
        'maxToken' => 'official-token',
        'maxRecipientType' => 'chat_id',
        'maxRecipientIds' => '123, 456',
        'maxNotify' => true,
        'maxDisableLinkPreview' => true,
        'format' => 'markdown',
    ]);

    assertTrue($official->send(str_repeat('Я', 4100)), 'MAX Business send should succeed.');
    assertTrue(count($official->requests) === 2, 'Two recipient IDs should produce two requests.');
    assertTrue(
        $official->requests[0]['authorization'] === 'official-token',
        'Official MAX token must not use the Bearer prefix.'
    );
    assertTrue(
        strpos($official->requests[0]['url'], 'chat_id=123') !== false,
        'Official MAX request must contain chat_id.'
    );
    assertTrue(
        strpos($official->requests[1]['url'], 'chat_id=456') !== false,
        'Second official MAX recipient must be used.'
    );

    $officialPayload = json_decode($official->requests[0]['payload'], true);
    assertTrue($officialPayload['format'] === 'markdown', 'Message format must be passed to MAX.');
    assertTrue($officialPayload['notify'] === true, 'Notify flag must be passed to MAX.');
    assertTrue(mb_strlen($officialPayload['text'], 'UTF-8') <= 4000, 'MAX text limit must be enforced.');

    $rumaxbot = new TestMaxNotify3($modx, [
        'provider' => 'rumaxbot',
        'apiUrl' => 'https://rumaxbot.ru/api/v1/messages',
        'apiKey' => 'rumax-key',
        'format' => 'html',
    ]);

    assertTrue($rumaxbot->send('<b>Order</b>'), 'rumaxbot send should succeed.');
    assertTrue(count($rumaxbot->requests) === 1, 'rumaxbot should produce one request.');
    assertTrue(
        $rumaxbot->requests[0]['authorization'] === 'Bearer rumax-key',
        'rumaxbot must keep the Bearer authorization scheme.'
    );

    echo "MaxNotify3 service tests passed.\n";
}
