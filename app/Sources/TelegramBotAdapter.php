<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Posts from Telegram channels whose owners added the ministry's bot. The bot cannot read other channels.
 * Telegram's terms restrict AI processing of its content, so these sources default to ai_enabled = false.
 */
class TelegramBotAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not set');
        }

        $response = $this->http()->get("https://api.telegram.org/bot{$token}/getUpdates", [
            'offset' => $source->state['offset'] ?? 0,
            'timeout' => 0,
            'allowed_updates' => json_encode(['channel_post']),
        ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new RuntimeException("Telegram returned HTTP {$response->status()}");
        }

        $offset = $source->state['offset'] ?? 0;

        foreach ($response->json('result', []) as $update) {
            $offset = max($offset, $update['update_id'] + 1);
            $post = $update['channel_post'] ?? null;
            $text = trim((string) ($post['text'] ?? $post['caption'] ?? ''));

            if ($post === null || $text === '') {
                continue;
            }

            $chat = $post['chat'];
            $url = isset($chat['username'])
                ? "https://t.me/{$chat['username']}/{$post['message_id']}"
                : 'https://t.me/c/'.preg_replace('/^-100/', '', (string) $chat['id'])."/{$post['message_id']}";

            yield new ItemData(
                url: $url,
                title: mb_substr($text, 0, 200),
                kind: 'social',
                excerpt: mb_substr($text, 0, 2000),
                publisher: 'Telegram: '.($chat['title'] ?? $chat['username'] ?? $chat['id']),
                publishedAt: Carbon::createFromTimestamp($post['date']),
            );
        }

        $source->state = array_merge($source->state ?? [], ['offset' => $offset]);
    }
}
