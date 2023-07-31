<?php
define('DATABASE_FILE', '.tinyib.db');

$allowed_get = array(
    'chat' => '1'
);

function logout(){
    session_unset();
    session_destroy();
    header('Location: /');
    exit;
}

function login(){
    $content = '';
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        // проверяем логин и пароль
        $db = new SQLite3(DATABASE_FILE);
        $stmt = $db->prepare('SELECT password, role FROM accounts WHERE username=:username');
        $stmt->bindValue(':username', $_POST['username'], SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && password_verify($_POST['password'], $row['password']) && $row['role'] == 99) {
            // логин и пароль верны, сохраняем информацию о пользователе в сессию
            $_SESSION['username'] = $_POST['username'];
            $_SESSION['role'] = $row['role'];
            header('Location: /');
            exit;
        } else {
            // логин или пароль неверны
            $content = 'Неверный логин или пароль!';
        }
    }
    $content .= <<<EOF
    <div class="loginform">
    <div class="head">I Was Almost A Claire Sandwich.</div>
        <form method="post">
            <input type="text" name="username" placeholder="username">
            <input type="password" name="password" placeholder="password">
            <input type="submit" value="Войти">
            <a class="abutton" href="/?reg">Регистрация</a>
        </form>
    </div>
EOF;
    return $content;
}

function registration(){
    $content = '';
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        // проверяем, что все поля заполнены
        if (empty($_POST['username']) || empty($_POST['password'])) {
            $content = '<div class="notify">Заполните все поля!</div>';
        } else {
            // проверяем, что такого пользователя еще нет
            $db = new SQLite3(DATABASE_FILE);
            $stmt = $db->prepare('SELECT COUNT(*) FROM accounts WHERE username=:username');
            $stmt->bindValue(':username', $_POST['username'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $count = $result->fetchArray(SQLITE3_NUM)[0];
            if ($count > 0) {
                $content = '<div class="notify">Пользователь с таким именем уже существует! <a href="/" class="abutton">Вернуться</a></div>';
            } else {
                // добавляем нового пользователя в базу данных
                $stmt = $db->prepare('INSERT INTO accounts (username, password, role, lastactive) VALUES (:username, :password, 99, :lastactive)');
                $stmt->bindValue(':username', $_POST['username'], SQLITE3_TEXT);
                $stmt->bindValue(':password', password_hash($_POST['password'], PASSWORD_DEFAULT), SQLITE3_TEXT);
                $stmt->bindValue(':lastactive', time(), SQLITE3_INTEGER);
                $stmt->execute();
                $content = '<div class="notify">Вы успешно зарегистрировались! Теперь вы можете <a href="?login" class="abutton">войти</a>.</div>';
            }
        }
    } else {
        $content .= <<<EOF
    <div class="loginform">
    <div class="head">Регистрация нового пользователя:</div>
        <form method="post">
            <input type="text" placeholder="username" name="username">
            <input type="password" placeholder="password" name="password">
            <input type="submit" value="Зарегистрироваться">
            <a class="abutton" href="/?login">Назад</a>
        </form>
    </div>
EOF;
    }
    return $content;
}

function home(){
        $content = ''; // Добавленная строка инициализации переменной $content
    global $allowed_get;
    foreach ($allowed_get as $key => $value) {
    $url = '?' . 'board=' . $key;
    $content .= '[ <a href="' . $url . '">' . $key . '</a> ]';
    }


    // пользователь авторизован
    $content .= <<<EOF
        <span class="adminbar">Добро пожаловать, {$_SESSION['username']}! <a href="?logout">Выйти</a></span>
    EOF;

    // Проверяем, была ли отправлена форма
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
        // Генерируем трипкод из имени пользователя
        $tripcode = substr(crypt($_SESSION['username'], 'tr'), -10);
        
        // Форматируем дату и время в нужном формате
        $date = date('y/m/d(D)H:i:s', time());
        
        // Создаем строку для столбца nameblock
        $nameblock = '<span class="postername">' . $_SESSION['username'] . '</span><span class="postertrip">' . $tripcode . '</span> ' . $date;
        
        // Добавляем сообщение в базу данных
        $db = new SQLite3(DATABASE_FILE);
        $stmt = $db->prepare('INSERT INTO b_posts (name, file_hex, password, file, file_original, file_size_formatted, email, thumb, subject, message, parent, timestamp, bumped, ip, tripcode, nameblock) VALUES (:name, "", "", "", "", "", "", "", "", :message, :parent, :timestamp, :timestamp, :ip, :tripcode, :nameblock)');
        $stmt->bindValue(':name', $_SESSION['username'], SQLITE3_TEXT);
        $message = htmlspecialchars($_POST['message'], ENT_QUOTES);
        $stmt->bindValue(':message', $message, SQLITE3_TEXT);
        $stmt->bindValue(':parent', $allowed_get[$_GET['board']], SQLITE3_INTEGER);
        $stmt->bindValue(':timestamp', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
        $stmt->bindValue(':tripcode', $tripcode, SQLITE3_TEXT);
        $stmt->bindValue(':nameblock', $nameblock, SQLITE3_TEXT);
        $stmt->execute();
        $db->close();

        // Перенаправляем пользователя на другую страницу
        header('Location: ?board=' . $_GET['board']);
        exit();
    }

    // Установка соединения с базой данных
    $db = new SQLite3(DATABASE_FILE);
    $stmt = $db->prepare("SELECT id, name, tripcode, message FROM (SELECT id, name, tripcode, message FROM b_posts ORDER BY id DESC LIMIT 30) AS last_posts ORDER BY id ASC;");
    $stmt->bindValue(':parent', $allowed_get[$_GET['board']], SQLITE3_INTEGER);
    $results = $stmt->execute();
    $content .= '<div class="feed">'; // Открываем обертку для всех строк

    while ($row = $results->fetchArray()) {
        $message = htmlspecialchars($row['message'], ENT_QUOTES);
        $content .= '<div id="'  . $row['id'] . '" class="post">#'  . $row['id'] . ' ' . $row['name'] . ' <span class="name">' . $row['tripcode'] . '</span> <div class="p">' . $row['message'] . '</div></div>';
    }

    $content .= '</div>'; // Закрываем обертку для всех строк
    $db->close();

    // Форма для добавления сообщений
    $content .= <<<EOF
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
        <div class="addpost">
            <form id="send" method="post">
                <input style="width: 80%;" type="text" name="message" autofocus><input style="float:right;width:15%;" type="submit" value="Отправить">
            </form>
        </div>
    EOF;

    return $content;
}

function getNewMessages($lastId) {
    global $allowed_get;

    // Установка соединения с базой данных
    $db = new SQLite3(DATABASE_FILE);
    $stmt = $db->prepare("SELECT id, name, tripcode, message FROM b_posts WHERE parent = :parent AND id > :lastId ORDER BY id ASC");
    $stmt->bindValue(':parent', $allowed_get[$_GET['board']], SQLITE3_INTEGER);
    $stmt->bindValue(':lastId', $lastId, SQLITE3_INTEGER);
    $results = $stmt->execute();
    $newMessages = array();

    while ($row = $results->fetchArray()) {
        $message = htmlspecialchars($row['message'], ENT_QUOTES);
        $newMessages[] = '<div id="'  . $row['id'] . '" class="post">#'  . $row['id'] . ' ' . $row['name'] . ' <span class="name">' . $row['tripcode'] . '</span> <div class="p">' . $message . '</div></div>';
    }

    $db->close();
    
    return $newMessages;
}

function renderPage($content) {
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>claire</title>
    <style>
         body {
            font-family: monospace;
            background-color: #222;
            color:#fff;
            width:100%;
            height:100%;
            margin:0;
            padding:0;
            font-size: 1em;
         }
         a {
            text-decoration: none;
            color: #fff;
         }
         a:visited {
            color: #fff;
         }
         .loginform {
            margin: 20% auto;
            text-align: center;
            width:800px;
         }
         .feed {
            height: 80vh;
            overflow-y: auto;
         }
         .feed, .addpost {
            width:600px;
            margin:0 auto;
        }
        .name {
            color:red;
        }
         input[type='submit'], .abutton {
            cursor: pointer;
         }
         textarea{
            width: 60%;
            margin: 6px;
         }
         input,textarea, .abutton {
            font-family: monospace;
            background-color: #222;
            padding: 3px;
            border: 1px solid #666;
            border-radius: 2px;
            color: #fff;
         }
         .post {
            border: 1px #666 dotted;
            padding: 5px;
            margin: 5px;
         }
         .addpost {
            position:fixed;
            bottom:10%;
            left: 50%;
            text-align: ;transform: translateX(-50%);
         }
         ::-webkit-scrollbar {
          width: 10px;
        }
        ::-webkit-scrollbar-track {
          background-color: #333;
        }
        ::-webkit-scrollbar-thumb {
          background-color: #666;
        }
        .notify{
            position:absolute;
            left:50%;
            top:50%;   
            text-align: center;
            width:800px;
            text-align: center;
            text-align: ;transform: translateX(-50%);
        }
        .head {
            margin:8px;
        }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script>$(document).ready(function() {
  function applyColorToText() {
    $('.name').each(function() {
      const text = $(this).text();
      const color = '#' + stringHash(text).substr(0, 6);
      $(this).css('color', color);
    });
  }

  applyColorToText();

  var feed = $('.feed');
  feed.scrollTop(feed.prop('scrollHeight'));
  setInterval(function() {
    var lastId = $('.post:last').attr('id');
    $.ajax({
      url: window.location.href,
      type: 'POST',
      data: { action: 'getNewMessages', lastId: lastId },
      dataType: 'json',
      success: function(response) {
        if (response.messages.length > 0) {
          var newMessages = response.messages.join('');
          feed.append(newMessages);
          applyColorToText();
          feed.scrollTop(feed.prop('scrollHeight'));
        }
      }
    });
  }, 1000);
});

function stringHash(text) {
  let hash = 0;
  for (let i = 0; i < text.length; i++) {
    hash = ((hash << 5) - hash) + text.charCodeAt(i);
    hash |= 0;
  }
  return (hash >>> 0).toString(16);
}

$(document).ready(function() {
  $('#send').submit(function(event) {
    event.preventDefault();
    $('#send input[type="submit"]').attr('disabled', true);
    setTimeout(function() {
      $('#send input[type="submit"]').attr('disabled', false);
    }, 3000);
    $.ajax({
      url: $(this).attr('action'),
      type: $(this).attr('method'),
      data: $(this).serialize(),
      success: function(response) {
        $('#send input[type="text"]').val(''); // Очистить форму после успешного ответа от сервера
      },
      error: function(xhr, status, error) {
        // обработка ошибки при отправке запроса
      }
    });
  });
});</script>
</head>
<body>
    $content
</body>
</html>
HTML;
}

// Обработка AJAX-запросов
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'getNewMessages') {
        $lastId = $_POST['lastId'];
        $messages = getNewMessages($lastId);
        
        $response = array(
            'messages' => $messages
        );
        
        echo json_encode($response);
        exit;
    }
}

// ======================================================================================================= //

session_start();

$page_content = '';
if (!isset($_SESSION['username']) && !isset($_GET['reg']) ) {
    // пользователь не авторизован, но хочет войти
    $page_content = login();
}
elseif (isset($_GET['logout'])) {
    // пользователь хочет выйти,удаляем информацию о нем из сессии
    logout();
} elseif (isset($_GET['login'])) {
    // пользователь не авторизован, но хочет войти
    $page_content = login();
} elseif (isset($_GET['reg'])) {
    // пользователь не авторизован и хочет зарегистрироваться
    $page_content = registration();
    } elseif (isset($_SESSION['username']) && isset($_SESSION['role']) && $_SESSION['role'] == 99 && isset($_GET['board']) && array_key_exists($_GET['board'], $allowed_get)) {
        // пользователь хочет просмотреть определенную доску
        $page_content = home();
    } 
    elseif (isset($_SESSION['username'])) {
    header('Location: index.php?board=chat');
}
// выводим страницу
renderPage($page_content);
?>