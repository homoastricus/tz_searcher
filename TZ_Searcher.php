<?php
DEFINE('DS', DIRECTORY_SEPARATOR);

// заготовка класс-библиотеки TZ_Searcher для поиска подстрок в переданном удаленном или локальном файле,
//работает в тестовом режиме, требуется для выполнения технического задания

class TZ_Searcher
{
    private $default_max_size = "1000m";
    private $default_max_timeout = "30";
    private $default_min_free_space_alert = "100k";

    public $config_file = "config.php";
    public $tmp_dir = "__UPLOAD";

    public function __construct()
    {
        // установка конфига при запуске класса
        $this->setupConfig();
    }

    /** деструктор очищает временный файл за ненадобностью */
    public function __destruct()
    {
        if(!empty($this->new_local_file)
            AND
            substr_count($this->new_local_file, __DIR__ . DS . $this->tmp_dir)
        ){
            $this->removeLocalFile($this->new_local_file);
        }
    }

    /** настройки конфига библиотеки  */
    private function setupConfig()
    {
        $config_file = __DIR__ . DS . $this->config_file;
        if (!file_exists($config_file) OR !is_readable($config_file)) {
            $this->max_size = $this->default_max_size;
            $this->max_timeout = $this->default_max_timeout;
            $this->min_free_space_alert = $this->default_min_free_space_alert;
        } else {
            require_once($config_file);
            $this->max_size = $this->fileSizeConfig($max_file_size);
            $this->max_timeout = $this->fileUploadMaxTimeout($max_timeout);
            $this->min_free_space_alert = $this->minFreeSpaceConfig($min_free_space_alert);
        }
    }

    /** функция установка проверки максимального размера загружаемого файла */
    private function fileSizeConfig($max_file_size)
    {
        $max_file_size_val = str_replace("k", "*1000", $max_file_size);
        $max_file_size_val = str_replace("m", "*1000000", $max_file_size_val);
        if ((int)$max_file_size_val <= 1 OR (int)$max_file_size_val == 0) {
            $max_file_size_val = $this->default_max_size;
        }
        return $max_file_size_val;
    }

    /** функция установка проверки минимально допустимого свободного места при загрузке файла */
    private function minFreeSpaceConfig($min_free_space_alert)
    {
        $min_free_space_alert_val = str_replace("k", "*1000", $min_free_space_alert);
        $min_free_space_alert_val = str_replace("m", "*1000000", $min_free_space_alert_val);
        if ((int)$min_free_space_alert_val <= 1 OR (int)$min_free_space_alert_val == 0) {
            $min_free_space_alert_val = $this->default_min_free_space_alert;
        }
        return $min_free_space_alert_val;
    }

    /** функция установка таймаута при загрузке файла */
    private function fileUploadMaxTimeout($max_timeout)
    {
        if ((int)$max_timeout <= 1 OR (int)$max_timeout == 0) {
            $max_timeout = $this->default_max_timeout;
        }
        return $max_timeout;
    }

    private function error($text)
    {
        echo "произошла ошибка в работе библиотеки: " . $text .
            " дальнейшая работа прервана.";
    }

    /** подготовка к загрузке удаленного файла */
    private function preparing_downloading($url)
    {
        $tmp_dir = __DIR__ . DS . $this->tmp_dir;
        if (!is_dir($tmp_dir)) {
            if (!mkdir($tmp_dir, 755)) {
                $error = "невозможно создать директорию, видимо, недостаточно прав вашего пользователя в системе";
                $this->error($error);
                return;
            }
        }
        $free_space = disk_free_space(__DIR__);
        if ($this->remote_filesize($url) >= $free_space + (int)$this->min_free_space_alert) {
            $error = "Недостаточно свободного места в системе для загрузки требуемого файла";
            $this->error($error);
            return;
        }
    }

    /** размер удаленного файла, рассчет по метатегам */
    private function remote_filesize($url)
    {
        $fp = fopen($url, "r");
        $inf = stream_get_meta_data($fp);
        fclose($fp);
        foreach ($inf["wrapper_data"] as $v)
            if (stristr($v, "content-length")) {
                $v = explode(":", $v);
                return trim($v[1]);
            }
    }

    /** соединение с файлом  */
    public function download_file($url)
    {
        $this->preparing_downloading($url);
        $this->new_local_file = __DIR__ . DS . $this->tmp_dir . DS . $this->randomFile($url);
        $this->curl_download($url, $this->new_local_file);
    }

    /**очистка временного файла*/
    private function removeLocalFile($filename){
        if(file_exists($filename)){
            unlink($filename);
        }
    }

    /** генерация рандомного имени */
    public function randomFile($filename){
        return basename($filename) . time() . uniqid();
    }

    /** проверка удаленный файл или локальный */
    function isLocalFile($path)
    {
        return preg_match('~^(\w+:)?//~', $path) === 0;
    }

    /** установка файла для поиска подстроки */
    public function setFile($file)
    {
        if ($this->isLocalFile($file)) {
            $this->new_local_file = $file;
        } else {
            $this->download_file($file);
        }
    }

    /*curl download function*/
    private function curl_download($url, $file)
    {
        // открываем файл, на сервере, на запись
        $dest_file = @fopen($file, "w");
        // открываем cURL-сессию
        $resource = curl_init();
        // устанавливаем опцию удаленного файла
        curl_setopt($resource, CURLOPT_URL, $url);
        // устанавливаем место на сервере, куда будет скопирован удаленной файл
        curl_setopt($resource, CURLOPT_FILE, $dest_file);
        // заголовки нам не нужны
        curl_setopt($resource, CURLOPT_HEADER, 0);
        // выполняем операцию
        curl_exec($resource);
        // закрываем cURL-сессию
        curl_close($resource);
        // закрываем файл
        fclose($dest_file);
    }

    /** общая информация о файле */
    public function base_info()
    {
    }

    /** поиск подстроки в текущем файле */
    public function findSubString($substring)
    {
        $result_substrings = [];
        $handle = fopen($this->new_local_file, "rb");
        $new_file_total = fread($handle, filesize($this->new_local_file));
        $new_file_lines_array = explode(PHP_EOL, $new_file_total);
        $line_number = 0;
        foreach ($new_file_lines_array as $line_string){
            $line_number++;
            preg_match_all("/" . $substring . "/ui", $line_string, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches as $item){
                if(count($item)>0){
                    $result_substrings[]  = [
                        'line' => $line_number,
                        'position' => $item[0][1],
                    ];
                }
            }
        }
        fclose($handle);
        $this->printData($this->new_local_file, $result_substrings, $substring);
        return $result_substrings;
    }

    private function printData($file, $data, $substring){
        echo "<pre>";
        echo "Файл: <b>" . $file . "</b>" .  PHP_EOL;
        echo "Вы искали подстроку <b>" . $substring . "</b>" .  PHP_EOL;
        echo "Найдено вхождений <b>" . count($data) . "</b>" .  PHP_EOL;
        foreach ($data as $item){
            foreach ($item as $key => $value) {
                echo $key . "=> " . $value . " ";
            }
            echo PHP_EOL;
        }
    }

}