<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ActionRepository;
use Symfony\Component\HttpFoundation\Response;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;

class TelegramWebhookController extends Controller
{
    private const CREATE_ORDER_BUTTON = 'Оформить заказ, 300₽';

    private const ACTION_START = 'start';
    private const ACTION_SEND_STICKER = 'send_sticker';
    private const ACTION_CREATE_ORDER = 'create_order';
    private const ACTION_STICKER_IS_NOT_FOUND = 'sticker_is_not_found';

    public function __construct(private readonly ActionRepository $actionRepository)
    {
    }

    public function index(): Response
    {
        $update = Telegram::getWebhookUpdate();
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

        $replyMarkup = Keyboard::make()
            ->setResizeKeyboard(false)
            ->setOneTimeKeyboard(false)
            ->row([
                Keyboard::button(self::CREATE_ORDER_BUTTON),
            ]);

        $text = <<<TEXT
🎉 Ваш стикерпак готов к отправке! 🎉

Чтобы завершить заказ, укажите адрес доставки и выберите способ оплаты. 
Затем нажмите кнопку "Оформить заказ", и ваш стикерпак отправится в путь! 📦🚀
TEXT;

        Telegram::sendMessage([
            'chat_id' =>  $update->getChat()->get('id'),
            'text' => $text,
            'reply_markup' => $replyMarkup
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
