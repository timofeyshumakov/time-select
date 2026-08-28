<?php

// Читаем содержимое существующего файла
$logs = json_decode(file_get_contents($file_path), true);

if(isset($_REQUEST["data"])){
  $data = [
      "event" => $_REQUEST["event"],
      'id' => $_REQUEST["data"]["FIELDS"]["ID"],
  ];

  // Открываем файл log.json для записи
  $file_path = __DIR__ . '/log.json';

  if (!file_exists($file_path)) {
      // Если файла ещё нет, создаём новый JSON-массив
      file_put_contents($file_path, json_encode([]));
  }

  // Добавляем новую запись в конец массива
  array_push($logs, $data);

  // Записываем обновленный массив обратно в файл
  file_put_contents($file_path, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}else{
  echo "<pre>";
  print_r($logs);
  echo "</pre>";
}

?>