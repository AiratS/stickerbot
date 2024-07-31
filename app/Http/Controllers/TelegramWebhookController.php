<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;

class TelegramWebhookController extends Controller
{
    private const CREATE_ORDER_BUTTON = 'Оформить заказ, 300₽';

    public function index(): Response
    {
        $update = Telegram::getWebhookUpdate();

        Log::debug('m', ['u' => $update]);

        if ('message' !== $update->objectType()) {
            return new Response();
        }

        $text = $update->getMessage()->get('text');
        switch ($text) {
            case '/start':
                return $this->handleStart($update);
            case self::CREATE_ORDER_BUTTON:
                return $this->handleCreateOrder($update);
        }

        if (!$update->getMessage()->has('sticker') && !filter_var($text, FILTER_VALIDATE_URL)) {
            Telegram::sendMessage([
                'chat_id' => $update->getChat()->get('id'),
                'text' => 'Стикерпак не найден'
            ]);

            return new Response();
        }

        $this->handleSickerProcessing($update);

        return new Response();
    }

    private function handleCreateOrder(Update $update): Response
    {
        Telegram::sendMessage([
            'chat_id' => $update->getChat()->get('id'),
            'text' => 'Произошла ошибка, попробуйте позже'
        ]);

        return new Response();
    }

    private function handleSickerProcessing(Update $update): Response
    {
        $replyMarkup = Keyboard::make()
            ->setResizeKeyboard(false)
            ->setOneTimeKeyboard(false)
            ->row([
                Keyboard::button(self::CREATE_ORDER_BUTTON),
            ]);

        Telegram::sendMessage([
            'chat_id' =>  $update->getChat()->get('id'),
            'text' => 'Ваш Стикерпак обработан, чтобы указать адрес доставки и оплатить, нажмите кнопку "Оформить заказ"',
            'reply_markup' => $replyMarkup
        ]);

        return new Response();
    }

    private function handleStart(Update $update): Response
    {
        Telegram::sendMessage([
            'chat_id' => $update->getChat()->get('id'),
            'text' => 'Добро пожаловать в СтикерБота, тут вы можете заказать распечатку ваших стикеров с доставкой. Просто скинут ссылку на стикерпака, или один стикер из вашего стикерпака'
        ]);

        return new Response();
    }
}
