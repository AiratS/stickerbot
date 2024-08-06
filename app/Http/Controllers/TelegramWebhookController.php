<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ActionRepository;
use App\Repositories\HandlePaymentRepository;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;

class TelegramWebhookController extends Controller
{
    private const CREATE_ORDER_BUTTON = 'Оформить заказ, 300₽';

    private const CALLBACK_QUERY_ENTER_ADDRESS = 'enter_address';
    private const CALLBACK_QUERY_SELECT_PAYMENT_TYPE = 'select_payment_type';

    private const ACTION_START = 'start';
    private const ACTION_SEND_STICKER = 'send_sticker';
    private const ACTION_CREATE_ORDER = 'create_order';
    private const ACTION_STICKER_IS_NOT_FOUND = 'sticker_is_not_found';

    public function __construct(
        private readonly ActionRepository $actionRepository,
        private readonly HandlePaymentRepository $handlePaymentRepository,
    ) {
    }

    public function index(): Response
    {
        $update = Telegram::getWebhookUpdate();
        if (!in_array($update->objectType(), ['message', 'callback_query'])) {
            return new Response();
        }

        Log::debug('ddd');

        if ($this->handlePaymentRepository->hasEnterAddress($update->getChat()->get('id'))) {
            if ('message' !== $update->objectType() || !$update->getMessage()->get('text')) {
                Telegram::sendMessage([
                    'chat_id' => $update->getChat()->get('id'),
                    'text' => 'Упс! Кажется, кажется вы не указали адрес доставки. 🤔'
                ]);

                return new Response();
            }

            return $this->handleEnterAddress($update);
        }


        if ('callback_query' === $update->objectType()) {
            $callbackData = $update->getMessage()->get('reply_markup')->get('inline_keyboard')->get(0)->getRawResponse()[0]['callback_data'];

            switch ($callbackData) {
                case self::CALLBACK_QUERY_ENTER_ADDRESS;
                    return $this->handleEnterAddress($update);
                case self::CALLBACK_QUERY_SELECT_PAYMENT_TYPE;
                    return $this->handleSelectPaymentType($update);
            }

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
            $this->saveAction($update, self::ACTION_STICKER_IS_NOT_FOUND, $text);

            Telegram::sendMessage([
                'chat_id' => $update->getChat()->get('id'),
                'text' => 'Упс! Кажется, мы не можем найти этот стикерпак. 🤔'
            ]);

            return new Response();
        }

        $this->handleSickerProcessing($update);

        return new Response();
    }

    private function handleEnterAddress(Update $update): Response
    {
        $this->saveAction($update, self::CALLBACK_QUERY_ENTER_ADDRESS);

        $replyMarkup = Keyboard::make()
            ->inline()
            ->row([
                Keyboard::inlineButton([
                    'text' => 'Банковская карточка',
                    'callback_data' => self::CALLBACK_QUERY_SELECT_PAYMENT_TYPE,
                ]),
                Keyboard::inlineButton([
                    'text' => 'СБП',
                    'callback_data' => self::CALLBACK_QUERY_SELECT_PAYMENT_TYPE,
                ]),
            ]);

        Telegram::sendMessage([
            'chat_id' => $update->getChat()->get('id'),
            'text' => "Ваш адрес принят в обработку. Выберите способ оплаты",
            'reply_markup' => $replyMarkup,
        ]);

        $this->handlePaymentRepository->delete($update->getChat()->get('id'));

        return new Response();
    }

    private function handleSelectPaymentType(Update $update): Response
    {
        $this->saveAction($update, self::CALLBACK_QUERY_SELECT_PAYMENT_TYPE);

        Telegram::sendMessage([
            'chat_id' => $update->getChat()->get('id'),
            'text' => "Извините, кажется, возникла ошибка.\nПопробуйте зайти позже.😢",
        ]);

        return new Response();
    }

    private function handleCreateOrder(Update $update): Response
    {
        $this->saveAction($update, self::ACTION_CREATE_ORDER);

        Telegram::sendMessage([
            'chat_id' => $update->getChat()->get('id'),
            'text' => "Извините, кажется, возникла ошибка.\nПопробуйте зайти позже.😢"
        ]);

        return new Response();
    }

    private function handleSickerProcessing(Update $update): Response
    {
        $stickerSet = $update->getMessage()->get('text');
        if (!$stickerSet && $update->getMessage()->has('sticker')) {
            $stickerSet = $update->getMessage()->get('sticker')->get('set_name');
        }

        $this->saveAction($update, self::ACTION_SEND_STICKER, $stickerSet);
        $this->handlePaymentRepository->create($update->getChat()->get('id'), self::CALLBACK_QUERY_ENTER_ADDRESS);

        $text = <<<TEXT
🎉 Ваш стикерпак готов к отправке! 🎉

Чтобы завершить заказ, укажите, пожалуйста, адрес доставки. 📦🚀
TEXT;

        Telegram::sendMessage([
            'chat_id' => $update->getChat()->get('id'),
            'text' => $text,
        ]);

        return new Response();
    }

    private function handleStart(Update $update): Response
    {
        $this->saveAction($update, self::ACTION_START);

        $text = <<<TEXT
👋 Привет! Добро пожаловать в СтикерБота! 

Здесь вы можете заказать печать стикеров с доставкой. 

Просто скиньте ссылку:

- на весь стикерпак
- или на отдельный стикер из вашего пака

Мы всё сделаем быстро и качественно! 😊
TEXT;

        Telegram::sendMessage([
            'chat_id' => $update->getChat()->get('id'),
            'text' => $text,
        ]);

        return new Response();
    }

    private function saveAction(Update $update, string $action, ?string $data = null): void
    {
        $chatId = $update->getChat()->get('id');
        $username = $update->getMessage()->get('from')->get('username');

        $this->actionRepository->save(
            $chatId,
            $username,
            $action,
            $data
        );
    }
}
