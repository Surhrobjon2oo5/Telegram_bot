<?php

// Загружаем токен и конфиг
$token = "7684775316:AAFyH_S8V8Aak9N2o27rKvFjAqLSl-p-NJw";
$apiURL = "https://api.telegram.org/bot$token/";

$config = json_decode(file_get_contents(__DIR__ . "/config.json"), true);

// Функция загрузки состояния
function load_state() {
    $file = __DIR__ . '/state.json';
    if (file_exists($file)) {
        $json = file_get_contents($file);
        return json_decode($json, true) ?: [];
    }
    return [];
}

// Функция сохранения состояния
function save_state($state) {
    $file = __DIR__ . '/state.json';
    file_put_contents($file, json_encode($state));
}

// Функция отправки сообщения
function sendMessage($chat_id, $text, $keyboard = null)
{
    global $apiURL;
    $data = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "HTML"
    ];
    if ($keyboard) {
        $data["reply_markup"] = json_encode([
            "keyboard" => $keyboard,
            "resize_keyboard" => true,
            "one_time_keyboard" => false
        ]);
    } else {
        $data["reply_markup"] = json_encode([
            "remove_keyboard" => true
        ]);
    }

    $url = $apiURL . "sendMessage?" . http_build_query($data);
    file_get_contents($url);
}

// Основные клавиатуры
$main_keyboard = [
    ["💰 Купить UC", "💵 Цены на UC"],
    ["🛒 Как купить UC", "❓ Помощь"],
    ["🎁 Акции", "🌐 Язык"]
];
$back_keyboard = [["🔙 Назад"]];

// Получаем обновление
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update || !isset($update["message"])) {
    exit();
}

$chat_id = $update["message"]["chat"]["id"];
$text = trim($update["message"]["text"] ?? "");

// Загружаем состояние
$state = load_state();
if (!isset($state[$chat_id])) {
    $state[$chat_id] = [
        "step" => "menu",
        "currency" => "somoni",
        "selected_package" => null,
        "pubg_id" => null,
        "language" => "ru"
    ];
}
$user_state = &$state[$chat_id];

// Обработка команды /start: сброс состояния и приветствие
if (mb_strtolower($text) === "/start") {
    $user_state = [
        "step" => "menu",
        "currency" => "somoni",
        "selected_package" => null,
        "pubg_id" => null,
        "language" => "ru"
    ];
    sendMessage($chat_id, "Добро пожаловать в Arzon UC Shop!\nВыберите пункт меню:", $main_keyboard);
    save_state($state);
    exit();
}

switch ($user_state["step"]) {
    case "menu":
        if ($text === "💰 Купить UC") {
            $user_state["step"] = "choose_package";
            $prices = $user_state["currency"] === "somoni" ? $config["prices_somoni"] : $config["prices_rub"];
            $currency_name = $config["currency_names"][$user_state["currency"]];

            $packages = array_keys($prices);
            $keyboard = [];
            $row = [];
            foreach ($packages as $pkg) {
                $row[] = $pkg . " UC";
                if (count($row) === 3) {
                    $keyboard[] = $row;
                    $row = [];
                }
            }
            if (count($row) > 0) $keyboard[] = $row;
            $keyboard[] = ["🔙 Назад"];

            sendMessage($chat_id, "Выберите пакет UC (цена указана в $currency_name):", $keyboard);
        } elseif ($text === "💵 Цены на UC") {
            $user_state["step"] = "choose_currency";
            sendMessage($chat_id, "Выберите валюту:", [
                ["🇷🇺 Рубли", "🇹🇯 Сомони"],
                ["🔙 Назад"]
            ]);
        } elseif ($text === "🛒 Как купить UC") {
            $info = "1️⃣ Нажмите «Купить UC»\n2️⃣ Выберите нужный пакет\n3️⃣ Введите свой PUBG ID\n4️⃣ Оплатите на указанные реквизиты\n5️⃣ Отправьте чек администратору\n6️⃣ Получите UC в течение 10–30 минут";
            sendMessage($chat_id, $info, $main_keyboard);
        } elseif ($text === "❓ Помощь") {
            $help = "📞 Связь с админом:\n\nTelegram: {$config['admin_telegram']}\nInstagram: {$config['admin_instagram']}\nТелефон: {$config['phone']}";
            sendMessage($chat_id, $help, $main_keyboard);
        } elseif ($text === "🎁 Акции") {
            sendMessage($chat_id, "Пока нет активных акций 🎉", $main_keyboard);
        } elseif ($text === "🌐 Язык") {
            sendMessage($chat_id, "Выберите язык (пока доступен только русский):", [
                ["🇷🇺 Русский", "🇬🇧 English"],
                ["🔙 Назад"]
            ]);
        } elseif ($text === "🇷🇺 Русский") {
            $user_state["language"] = "ru";
            sendMessage($chat_id, "Язык установлен на русский.", $main_keyboard);
        } elseif ($text === "🇬🇧 English") {
            $user_state["language"] = "en";
            sendMessage($chat_id, "Language set to English (пока не реализовано).", $main_keyboard);
        } else {
            sendMessage($chat_id, "Пожалуйста, выберите пункт меню:", $main_keyboard);
        }
        break;

    case "choose_currency":
        if ($text === "🇷🇺 Рубли") {
            $user_state["currency"] = "rub";
            $user_state["step"] = "menu";
            sendMessage($chat_id, "Вы выбрали рубли.", $main_keyboard);
        } elseif ($text === "🇹🇯 Сомони") {
            $user_state["currency"] = "somoni";
            $user_state["step"] = "menu";
            sendMessage($chat_id, "Вы выбрали сомони.", $main_keyboard);
        } elseif ($text === "🔙 Назад") {
            $user_state["step"] = "menu";
            sendMessage($chat_id, "Вернулись в меню.", $main_keyboard);
        } else {
            sendMessage($chat_id, "Пожалуйста, выберите валюту:", [
                ["🇷🇺 Рубли", "🇹🇯 Сомони"],
                ["🔙 Назад"]
            ]);
        }
        break;

    case "choose_package":
        $prices = $user_state["currency"] === "somoni" ? $config["prices_somoni"] : $config["prices_rub"];
        $currency_name = $config["currency_names"][$user_state["currency"]];

        if ($text === "🔙 Назад") {
            $user_state["step"] = "menu";
            sendMessage($chat_id, "Отмена покупки. Вернулись в меню.", $main_keyboard);
        } elseif (preg_match('/^(\d+)\s?UC$/i', $text, $matches)) {
            $pkg = $matches[1];
            if (isset($prices[$pkg])) {
                $user_state["selected_package"] = $pkg;
                $user_state["step"] = "enter_pubg_id";
                $price = $prices[$pkg];
                sendMessage($chat_id, "Вы выбрали пакет: $pkg UC\nЦена: $price $currency_name\n\nВведите ваш PUBG ID (пример: 1234567890):", $back_keyboard);
            } else {
                sendMessage($chat_id, "Пакет не найден. Выберите пакет из списка:", null);
            }
        } else {
            sendMessage($chat_id, "Пожалуйста, выберите пакет из списка:", null);
        }
        break;

    case "enter_pubg_id":
        if ($text === "🔙 Назад") {
            $user_state["step"] = "choose_package";
            $prices = $user_state["currency"] === "somoni" ? $config["prices_somoni"] : $config["prices_rub"];

            $packages = array_keys($prices);
            $keyboard = [];
            $row = [];
            foreach ($packages as $pkg) {
                $row[] = $pkg . " UC";
                if (count($row) === 3) {
                    $keyboard[] = $row;
                    $row = [];
                }
            }
            if (count($row) > 0) $keyboard[] = $row;
            $keyboard[] = ["🔙 Назад"];

            sendMessage($chat_id, "Выберите пакет UC:", $keyboard);
        } elseif (preg_match('/^\d{5,}$/', $text)) {
            $user_state["pubg_id"] = $text;
            $user_state["step"] = "show_payment";

            $pkg = $user_state["selected_package"];
            $price = $user_state["currency"] === "somoni" ? $config["prices_somoni"][$pkg] : $config["prices_rub"][$pkg];
            $currency_name = $config["currency_names"][$user_state["currency"]];

            $payment = $config["payment_info"];
            $payment_text = "💳 Реквизиты для оплаты:\n\n";

            foreach ($payment as $method => $info) {
                $payment_text .= "<b>$method</b>:\nНомер: {$info['number']}\nИмя: {$info['name']}\n\n";
            }

            $msg = "Отлично!\n\nВаш PUBG ID: <b>{$user_state['pubg_id']}</b>\nПакет: <b>$pkg UC</b>\nЦена к оплате: <b>$price $currency_name</b>\n\n$payment_text❗ После оплаты отправьте чек администратору.";

            sendMessage($chat_id, $msg, [["🔙 Назад"]]);
        } else {
            sendMessage($chat_id, "Неверный формат ID. Введите только цифры (пример: 1234567890):", [["🔙 Назад"]]);
        }
        break;

    case "show_payment":
        if ($text === "🔙 Назад") {
            $user_state["step"] = "enter_pubg_id";
            sendMessage($chat_id, "Введите ваш PUBG ID (пример: 1234567890):", $back_keyboard);
        } else {
            sendMessage($chat_id, "Пожалуйста, нажмите кнопку «🔙 Назад» для возврата.", [["🔙 Назад"]]);
        }
        break;

    default:
        $user_state["step"] = "menu";
        sendMessage($chat_id, "Добро пожаловать! Выберите пункт меню:", $main_keyboard);
        break;
}

// Сохраняем состояние
save_state($state);
?>
